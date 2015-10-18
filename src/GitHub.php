<?php
/**
 * TODO: move most of the private functions to own files
 */
// error_reporting(E_ALL);
// ini_set('display_errors', '1');



namespace Aoloe\Deploy;

use function \Aoloe\Php\startsWith as startsWith;
use function \Aoloe\debug as debug;

// new \Aoloe\Debug(); // force import

class Github {
    private $configuration = null;
    private $configuration_default = array (
        // 'username' => '',
        // 'repository' => '',
        'branch' => 'master', // only commits to this branch will be retained
        'secret' => '', // password_hash('secret string');
        /*
         * repository_base_path is the base path from the Git repository:
         * all changes to files that are not under this path are ignored.
         * The path is removed from the deployment targets.
         *
         * If you set it to `content`, only the files under the `content/` directory will be considered and
         * the file `content/test/test_file.md` will be deployed as `test/test_file.md`.
         */
        'repository_base_path' => '', // path to be removed from the filename
        'deployment_base_path' => '', // path to be prefixed to the filename
        'queue_file' => 'queue.json', // list of the commits
        'log_file' => 'log.txt', // list of the commits
        'ignore' => array (),
    );
    private $payload = array();
    private $queue = array();

    /** @param array $config it must be an array */
    public function set_configuration($config) {
        if (is_array($config)) {
            $this->configuration = $config + $this->configuration_default; // TODO: check the order
        }
        if (is_null($this->configuration) || !array_key_exists('username', $this->configuration) || !array_key_exists('repository', $this->configuration)) {
            // TODO: write to log
            die("wrong configuration");
        }
    }

    public function read_queue_from_file() {
        $result = false;
        if (is_array($this->configuration) && array_key_exists('queue_file', $this->configuration) && file_exists($this->configuration['queue_file'])) {
            // debug('queue file', file_get_contents($this->configuration['queue_file']));
            $this->queue = json_decode(file_get_contents($this->configuration['queue_file']), true);
            $result = true;
        }
        return $result;
    }

    public function write_queue_to_file() {
        $result = false;
        if (is_array($this->configuration) && array_key_exists('queue_file', $this->configuration) && (!file_exists($this->configuration['queue_file']) || is_writable($this->configuration['queue_file']))) {
            $result = file_put_contents($this->configuration['queue_file'], json_encode($this->queue));
        }
        return $result;
    }

    /** @param array $request by default, you will pass $_REQUEST */
    public function set_payload_from_request($request) {
        if (array_key_exists('payload', $request)) {
            $payload = $request['payload'];
            if (get_magic_quotes_gpc()) {
                $payload = str_replace('\"', '"', $payload);
            }
            // file_put_contents('latest', $request['payload']);
            $this->payload = json_decode($request['payload'], true);
        }
        // TODO: check if username, repository, and branch are the same as defined in the configuration
        // if not, log the incident and die.
    }

    /**
     * check if username, repository and branch in config and payload match
     */
    public function is_valid_request() {
        return $this->get_user_from_payload() === $this->configuration['username'] &&
            $this->get_repository_from_payload() === $this->configuration['repository'] &&
            $this->get_branch_from_payload() === $this->configuration['branch'];
    }

    /**
     * add each added, modified or removed file from the payload to the queue.
     * if the file is already in the queue override the preview action.
     */
    public function add_payload_to_queue() {
        foreach ($this->payload['commits'] as $item) {
            foreach (array_merge($item['added'] + $item['modified']) as $iitem) {
                // TODO: make sure that iitem cannot be pointing outside of the target basepath
                if (!array_key_exists($iitem, $this->queue)) {
                    $this->queue[] = array (
                        'author' => $item['author']['name'],
                        'message' => $item['message'],
                        'action' => 'download',
                        'file' => $iitem,
                    );
                } else {
                    $this->queue[$iitem]['action'] = 'download';
                }
            }
            foreach ($item['removed'] as $iitem) {
                // TODO: make sure that iitem cannot be pointing outside of the target basepath
                if (!array_key_exists($iitem, $this->queue)) {
                    $this->queue[] = array (
                        'author' => $item['author']['name'],
                        'message' => $item['message'],
                        'action' => 'remove',
                        'file' => $iitem,
                    );
                } else {
                    $this->queue[$iitem]['action'] = 'remove';
                }
            }
        }
    }

    public function synchronize() {
        while ($commit = $this->pull_commit_from_queue()) {
            // \Aoloe\debug('commit', $commit);
            if (!empty($this->configuration['repository_base_path']) && !startsWith($commit['file'], $this->configuration['repository_base_path'])) {
                // ignore files that are outside of repository_base_path
                continue;
            }
            if ($commit['action'] === 'download') {
                if (!$this->download_file_from_github($commit['file'])) {
                    debug('could not download the file', $commit['file']);
                    // TODO: log the failed download
                    return false;
                }
            } elseif ($commit['action'] === 'download') {
                $this->delete_file($path);
            }
        }
        return true;
    }

    private function pull_commit_from_queue() {
        return array_shift($this->queue);
    }

    private function download_file_from_github($path) {
        $result = true;
        // TODO: ensure that the target path exists (or that it will be created)
        $url = $this->get_raw_url_for_github($this->configuration['username'], $this->configuration['repository'], $this->configuration['branch']);
        $content = $this->get_url_content($url.$path);
        if ($content === false)  {
            $result = false;
        } else {
            // debug('content', $content);
            $deployment_path = $this->get_deployment_path($path);
            file_put_contents($deployment_path, $content);
        }
        return $result;
    }

    private function get_deployment_path($path) {
        $result = $path;
        if (!empty($this->configuration['repository_base_path'])) {
            $result = substr($result, strlen($this->configuration['repository_base_path']));
        }
        $result = $this->configuration['deployment_base_path'].$result;
        return $result;
    }

    private function delete_file($path) {
        // TODO: how to make sure that somebody cannot remove random files? is the secret enough?
        // TODO: what appens if one tries to unlink a file that does not exist (anymore)?
        // TODO: ensure that empty paths get removed
        $deployment_path = $this->get_deployment_path($path);
        unlink($path);
    }

    private function get_user_from_payload() {
        return(current(explode('/', $this->payload['repository']['full_name'])));
    }

    private function get_repository_from_payload() {
        return(current(array_slice(explode('/', $this->payload['repository']['full_name']), 1, 1)));
    }

    private function get_branch_from_payload() {
        return array_key_exists('ref', $this->payload) ? current(array_slice(explode('/', $this->payload['ref']), 2, 1)) : '';
    }

    private function get_filename_sanitized($filename) {
        $result = preg_replace("([\.]{2,})", '.', $filename);
            

        $result = preg_replace("([^\w\s\déèëêÉÈËÊáàäâåÁÀÄÂÅóòöôÓÒÖÔíìïîÍÌÏÎúùüûÚÙÜÛýÿÝøØœŒÆçÇ\+\-_~,;:\[\]\(\]\/.])", '', $result);
        return $result;
    }

    private function get_raw_url_for_github($user, $repository, $branch = null) {
        return strtr(
            'https://raw.githubusercontent.com/$user/$repository/$branch/',
            array(
                '$user' => $user,
                '$repository' => $repository,
                '$branch' => isset($branch) ? $branch : 'master',
            )
        );
    }

    private function get_url_content($url) {
        $result = '';
        // echo('<pre>url: '.print_r($url, 1).'</pre>');
        // $this->log[] = '>> '.$url;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'aoloe/git-diploy');
        // curl_setopt($ch, CURLOPT_HEADER, true);
        // curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $result = curl_exec($ch);
        $info = curl_getinfo($ch);
        // \Aoloe\debug('info', $info);
        if (($info['http_code'] == '404') || ($info['http_code'] == '403')) {
            $result = false;
        }
        curl_close($ch);
        // echo('<pre>result: '.print_r($result, 1).'</pre>');
        return $result;
    }
}

/**
 * Simple PHP tool listening to the Github webhooks and keeping the
 * files in sync between a website and  the corresponding Github repository
 *
 * TODO:
 * - implement the secret
 * - allow to also log all requests, even if discareded
 * - check if it's possible that a same file is in delete and modify
 *   (and then just delete it)
 * - add an optional check, if it can write the file that it needs (deploy target, log file)
 * - ignore the files that are under any of $github_ignore_path.
 * - protect paths that are (not) on github from malicious deletes or updates (vendor/, cache/)
 * - eventually implement an asyncronous mode: the request writes a list of tasks and tries to
 *   run them. if it fails (time out?) it does not remove the tasks and a cron job could retry them, one task
 *   after the other.
 */
class GitHub_old {

    private $payload = null;

    private $github_ignore_path = array();

    /**
     * Set the base path from the Git repository: all changes to files that are not under this path
     * are ignored. The path is removed from the deployment targets.
     *
     * If you set it to `content`, only the files under the `content/` directory will be considered and
     * the file `content/test/test_file.md` will be deployed as `test/test_file.md`.
     *
     * @param string $path
     */
    public function set_github_base_path($path = '') {$this->github_base_path = trim($path, '/').($path == '' ? '' : '/');}
    private $github_base_path = '';

    /**
     * Set it to the path where the files are deployed (relative to the path of the file loading this class).
     *
     * @param string $path
     */
    public function set_deploy_base_path($path = '') {$this->deploy_base_path = $path;}
    private $deploy_base_path = '';

    /** @param string $file */
    public function set_log_file($file) {$this->log_file = $file;}
    private $log_file = null;

    /**
     * List of the performed actions as a json string.
     *
     * @return string $file
     */
    public function get_log() {return json_encode($this->log);}
    public function clear_log() {$this->log = array();}
    private $log = array();

    /** @param array $request If not set, $_REQUEST will be used */
    public function read($request = null) {
        if (is_null($request)) {
            $request = $_REQUEST;
        }
        if (array_key_exists('payload', $request)) {
            $payload = $request['payload'];
            if (get_magic_quotes_gpc()) {
                $payload = str_replace('\"', '"', $payload);
            }
            // file_put_contents('latest', $request['payload']);
            $this->payload = json_decode($request['payload'], true);
        }
    }

    /**
     * For debugging purposes. Allows the caller to store the payload on disk.
     * @return array
     */
    public function get_payload() {
        return $this->payload;
    }

    /** @return bool */
    public function is_valid($user, $repository, $branch = 'master') {
        return ($this->get_user() == $user) && ($this->get_repository() == $repository) && ($this->get_branch() == $branch);
    }

    public function synchronize() {
        $this->log[] = date('Y-m-d');
        foreach ($this->get_commit_message() as $item) {
            $this->log[] = '> '.$item;
        }
        foreach ($this->get_files_to_get() as $item) {
            $url = $this->get_raw_url($this->get_user(), $this->get_repository(), $this->get_branch()).$this->github_base_path.$item;
            // echo('<pre>url: '.print_r($url, 1).'</pre>');
            $file_content = $this->get_url_content($url);
            // echo('<pre>file_content: '.print_r($file_content, 1).'</pre>');
            $path = $this->get_deploy_path($item);
            $this->ensure_file_writable($path);
            file_put_contents($path, $file_content);
            $this->log[] = '+ '.$path;
        }

        foreach ($this->get_files_to_delete() as $item) {
            $filename = $this->get_deploy_path($item);
            // $this->log[] = '>> '.$filename;
            if (file_exists($filename)) {
                unlink($filename);
                $this->log[] = '- '.$filename;
            }
        }

    }

    /** @param string $filename */
    private function ensure_file_writable($filename) {
        $path = pathinfo($filename, PATHINFO_DIRNAME);
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }
    
    /** @return string */
    private function get_deploy_path($path) {
        $result = (empty($this->deploy_base_path) ? '' : rtrim($this->deploy_base_path, '/').'/').$path;
        // echo('<pre>result: '.print_r($result, 1).'</pre>');
        return $result;
    }

    private function get_user() {
        return(current(explode('/', $this->payload['repository']['full_name'])));
    }

    private function get_repository() {
        return(current(array_slice(explode('/', $this->payload['repository']['full_name']), 1, 1)));
    }

    private function get_branch() {
        return array_key_exists('ref', $this->payload) ? current(array_slice(explode('/', $this->payload['ref']), 2, 1)) : '';
    }

    private function get_commit_message() {
        $result = array();
        foreach ($this->get_commits() as $commit) {
            $result[] = $commmit['message'];
        }
    }

    private function get_files_to_get() {
        $result = array();
        foreach ($this->get_commits() as $commit) {
            // echo("<pre>commit:\n".print_r($commit, 1)."</pre>");
            foreach ($commit['added'] + $commit['modified'] as $file) {
                // echo("<pre>file:\n".print_r($file, 1)."</pre>");
                $file = $this->get_file_in_github_basepath($file);
                if (isset($file) && ($file === $this->get_filename_sanitized($file))) {
                    // echo("<pre>file:\n".print_r($file, 1)."</pre>");
                    $result[] = $file;
                }
            }
        }
        return $result;
    }

    private function get_files_to_delete() {
        $result = array();
        foreach ($this->get_commits() as $commit) {
            foreach ($commit['removed'] as $file) {
                $file = $this->get_file_in_github_basepath($file);
                if (isset($file) && ($file === $this->get_filename_sanitized($file))) {
                    $result[] = $file;
                }
            }
        }
        return $result;
    }

    private function get_file_in_github_basepath($filename) {
        $result = null;
        if ($this->github_base_path == '/') {
            $result = $filename;
        } elseif ($this->github_base_path === substr($filename, 0, strlen($this->github_base_path))) {
            $result = substr($filename, strlen($this->github_base_path));
        }
        return $result;
    }


    private function write_log() {
        if (isset($log_file) && !empty($log_file)) {
            /*
            // file_put_contents($log_file, $item['message']."\n", FILE_APPEND);
            $fp = fopen($log_file, 'a');
            fputcsv($fp, array(date('Y-m-d'), $item['message'], (empty($upload) ? '' : '+'.implode('+', $upload)) . (empty($remove) ? '' : '-'.implode('-', $remove))));
            fclose($fp);
            */
        }
    }

    private function get_commit_description() {
        $result = array();
        foreach ($this->get_commits() as $commit) {
            $date = \DateTime::createFromFormat('Y-m-d\TH:i:sT',$commit['timestamp'] )->format('d.m.Y H:i');
            $result[] = $date.', '.$commit['author']['name'].': '.$commit['message'];
        }
        return $result;
    }

    private function get_commits() {
        $result = array();
        // echo("<pre>get_commits():\n".print_r($this->payload['commits'], 1)."</pre>");
        if (array_key_exists('commits', $this->payload)) {
            $result = $this->payload['commits'];
        }
        return $result;
    }

    private function get_filename_sanitized($filename) {
        $result = preg_replace("([\.]{2,})", '.', $filename);
            

        $result = preg_replace("([^\w\s\déèëêÉÈËÊáàäâåÁÀÄÂÅóòöôÓÒÖÔíìïîÍÌÏÎúùüûÚÙÜÛýÿÝøØœŒÆçÇ\+\-_~,;:\[\]\(\]\/.])", '', $result);
        return $result;
    }

    private function get_raw_url($user, $repository, $branch = null) {
        return strtr(
            'https://raw.githubusercontent.com/$user/$repository/$branch/',
            array(
                '$user' => $user,
                '$repository' => $repository,
                '$branch' => isset($branch) ? $branch : 'master',
            )
        );
    }

    private function get_url_content($url) {
        $result = '';
        // echo('<pre>url: '.print_r($url, 1).'</pre>');
        // $this->log[] = '>> '.$url;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'aoloe/git-diploy');
        // curl_setopt($ch, CURLOPT_HEADER, true);
        // curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $result = curl_exec($ch);
        $info = curl_getinfo($ch);
        if (($info['http_code'] == '404') || ($info['http_code'] == '403')) {
            $result = '';
        }
        curl_close($ch);
        // echo('<pre>result: '.print_r($result, 1).'</pre>');
        return $result;
    }
}
