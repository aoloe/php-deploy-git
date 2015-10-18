<?php

/**
 * this is, for now, a draft of the way Deploy\GitHub should create and use a queue, to make sure that
 * no change gets lost.
 * 
 * this code has never been run and has not been tested.
 */


error_reporting(E_ALL);
ini_set('display_errors', '1');

// if php < 5.5.0
if (!function_exists('password_hash')) {
    function password_hash($password) {
        return crypt($password);
    }
    function password_verify($password, $hash) {
        return $hash === crypt($string);
    }
}

echo(password_hash('secret_string'));

// TODO: file_get_contents('config.json');
$configuration = <<<EOT
{
    "username" : "aoloe",
    "repository" : "htdocs-test",
}
EOT;


// echo('<pre>'.print_r($_REQUEST, 1).'</pre>');
if (array_key_exists('payload', $_REQUEST)) {
    file_put_contents('request.json', $_REQUEST['payload']);
} else {
    echo("<pre>request:\n".print_r(json_decode(file_get_contents('request.json'), true), 1)."</pre>\n");
    echo("<pre>log:\n".print_r(json_decode(file_get_contents('log.json'), true), 1)."</pre>\n");
}

include_once('vendor/autoload.php');
include_once('vendor/aoloe/php-deploy-git/src/GitHub.php');

// $git_deploy = new Aoloe\Deploy\Github();
$git_deploy = new Github();
$git_deploy->set_configuration($configuration);

$git_deploy->read_pending_queue();

if (array_key_exists('payload', $_REQUEST)) {
    // TODO: if there is a queue: should it synchronize or just enqueue the request?
    $git_deploy->set_payload_from_request($_REQUEST);
    if ($git_deploy->has_payload() && $git_deploy->is_valid_request()) {
        $git_deploy->add_payload_to_queue();
    }
}

if ($this->has_queue()) {
    $git_deploy->synchronize();
} else {
    // TODO: show the content of the queue
    echo($git_deploy->get_queue_rendered());
}

$queue = <<<EOT
{
    "commit" : [
    ]
}
EOT;

class Github {
    private $configuration = null;
    private $configuration_default = array (
        // 'username' : '',
        // 'repository' : '',
        'secret' => '', // password_hash('secret string');
        'branch' => 'master', // only commits to this branch will be retained
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
            die();
        }
    }

    public function read_pending_queue() {
        if (file_exists($this->configuration['queue_file'])) {
            $this->queue = json_decode(file_get_contents($this->configuration['queue_file']));
        }
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

    public function is_valid_request() {
        // TODO: check if username, repository and branch in config and payload match
        return $this->get_user_from_payload() === $this->configuration['username'] &&
            $this->get_repository_from_payload() === $this->configuration['repository'] &&
            $this->get_branch_from_payload() === $this->config['branch'];
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
                    $this->queue[$iitem]['action'] = 'download';
                }
            }
        }
    }

    public function synchronize() {
        foreach ($this->queue[] as $key => $value) {
            // TODO: correctly define success
            $success = true;
            $file = $this->get_file_from_github($url, $path);
            if ($success) {
                unset($this->queue[$key]);
                // TODO: store the current queue
            }
        }
    }

    private function download_file_from_github($url, $path) {
        // TODO: ensure that the target path exists (or that it will be created)
    }

    private function delete_file($path) {
        // TODO: what appens if one tries to unlink a file that does not exist (anymore)?
        // TODO: ensure that empty paths are removed
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
        if (($info['http_code'] == '404') || ($info['http_code'] == '403')) {
            $result = '';
        }
        curl_close($ch);
        // echo('<pre>result: '.print_r($result, 1).'</pre>');
        return $result;
    }
}

// this is the old code that is being moved up in the new one

$git_deploy = new Aoloe\Deploy\Github();
// $git_deploy->load_config($config);
// $git_deploy->set_ignore();
$git_deploy->set_github_base_path('htdocs');
$git_deploy->read($_REQUEST);
if($git_deploy->is_valid('aoloe', 'htdocs-bc_oberurdorf')) {
    $git_deploy->synchronize();
    // echo("<pre>log:\n".print_r(json_decode($git_deploy->get_log(), true), 1)."</pre>\n");
    file_put_contents('log.json', $git_deploy->get_log());
}

