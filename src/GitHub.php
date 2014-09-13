<?php
// error_reporting(E_ALL);
// ini_set('display_errors', '1');

namespace Aoloe\Deploy;

/**
 * Simple PHP tool listening to the Github webhooks and keeping the
 * files in sync between a website and  the corresponding Github repository
 *
 * TODO:
 * - implement the secret
 * - implemente the logging of the commits
 * - log all requests (or at least what should have been done **and** what has been done)
 * - check if it's possible that a same file is in delete and modify
 *   (and then just delete it)
 * - add an optional check, if it can write the file that it needs (deploy target, log file)
 * - ignore the files that are under $github_ignore_path (or only consider one path)
 * - eventually, only delete a file if it's not in the git repository
 * - protect paths that are not on github from deletes (vendor/, cache/)
 */
class GitHub {

    private $payload = null;

    private $github_ignore_path = array();
    private $github_base_path = '';
    /** @param string $path */
    public function set_github_base_path($path = '') {$this->github_base_path = $path;}
    private $deployed_base_path = '';
    /** @param string $path */
    public function set_deployed_base_path($path = '') {$this->deployed_base_path = $path;}
    private $log_file = null;
    /** @param string $file */
    public function set_log_file($file) {$this->log_file = $file;}

    /** @param array $request */
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
        } else {
            // TODO: eventually, show the log
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
        foreach ($this->get_files_to_get() as $item) {
            $url = $this->get_raw_url($this->get_user(), $this->get_repository(), $this->get_branch()).$this->github_base_path.$item;
            // echo('<pre>url: '.print_r($url, 1).'</pre>');
            $file_content = $this->get_url_content($url);
            // echo('<pre>file_content: '.print_r($file_content, 1).'</pre>');
            $path = $this->get_deploy_path($item);
            $this->ensure_file_writable($path);
            file_put_contents($path, $file_content);
        }

        foreach ($this->get_files_to_delete() as $item) {
            $filename = $this->get_deploy_path($item);
            if (file_exists($filename)) {
                unlink($filename);
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
        $result = (empty($this->deployed_base_path) ? '' : rtrim($this->deployed_base_path, '/').'/').$path;
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

                if ($file === $this->get_filename_sanitized($file)) {
                    $result[] = $file;
                }
            }
        }
        return $result;
    }

    private function get_file_in_github_basepath($filename) {
        $result = null;
        $base_path = trim($this->github_base_path, '/').'/';
        if ($base_path == '/') {
            $result = $filename;
        } elseif ($base_path === substr($filename, 0, strlen($base_path))) {
            $result = substr($filename, strlen($base_path));
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
