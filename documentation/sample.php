<?php

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

$git_deploy = new Aoloe\Deploy\Github();
$git_deploy->load_config($config);
$git_deploy->set_ignore();
$git_deploy->read_request($_REQUEST);
if($git_deploy->has_valid_request()) { // check payload, user, repository and branch
    $git_deploy->read_commit_files();
    $git_deploy->fetch_modified_files();
    $git_deploy->remove_deleted_files();
    $git_deploy->append_to_log_file();
} else {
    echo('<pre>'.print_r($git_deploy->get_recent(), 1).'</pre>');
}

