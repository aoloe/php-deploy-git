<?php

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

$config = array (
    'my_repo' => // name as user/repository
        'secret' => 'secret string', // password_hash('secret string');
        'branch' => 'master', // only commits to this branch will be retained
        'repository_base_path' => '', // path to be removed from the filename
        'deployment_base_path' => '', // path to be prefixed to the filename
        'log_file' => 'log.txt', // list of the commits
        'ignore' => array
        ),
);

// echo('<pre>'.print_r($_REQUEST, 1).'</pre>');
if (array_key_exists('payload', $_REQUEST)) {
    file_put_contents('request.json', $_REQUEST['payload']);
} else {
    echo("<pre>request:\n".print_r(json_decode(file_get_contents('request.json'), true), 1)."</pre>\n");
    echo("<pre>log:\n".print_r(json_decode(file_get_contents('log.json'), true), 1)."</pre>\n");
}

include_once('vendor/autoload.php');
include_once('vendor/aoloe/php-deploy-git/src/GitHub.php');

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

