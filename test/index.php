<?php

/**
 * being tested on ww.xox.ch/deploy/ [laptop]
 */

include('../vendor/autoload.php');
include('../src/Deploy/GitHub.php');

$test = new Aoloe\Test();

$configuration_minimal = array (
    'username' => 'aoloe',
    'repository' => 'repository_test',
    'branch' => 'master',
);

$configuration_github = array (
    'username' => 'aoloe',
    'repository' => 'php-deploy-git',
    'branch' => 'master',
    'repository_base_path' => 'test/data/',
    'deployment_base_path' => 'data/download/',
);

$payload_base = array (
    'repository' => array (
        'full_name' => 'aoloe/repository_test',
    ),
    'ref' => 'refs/heads/master',
    'commits' => array(),
);

$test->start("Import the GitHub deploy source");
$test->assert_identical('GitHub class loaded', class_exists('Aoloe\Deploy\GitHub'), true);
$deploy = new Aoloe\Deploy\GitHub();
$test->assert_identical('deploy object created', is_a($deploy, 'Aoloe\Deploy\GitHub'), true);
$test->stop();
unset($deploy);

$test->start("Read the pending queue");
$deploy = new Aoloe\Deploy\GitHub();
$test->assert_false("no file read if no queue file defined", $deploy->read_queue_from_file());
$deploy->set_configuration($configuration_minimal + array('queue_file' => 'queue_not_exists.json'));
$test->assert_false("no file read if wrong queue file defined", $deploy->read_queue_from_file());
$deploy->set_configuration($configuration_minimal + array('queue_file' => 'queue_empty.json'));
$deploy->read_queue_from_file();
$test->assert_identical("read empty queue gives empty queue", $test->access_property($deploy, 'queue'), array());
$deploy->set_configuration($configuration_minimal + array('queue_file' => 'queue_one_add.json'));
$deploy->read_queue_from_file();
$test->assert_identical("read queue with one download", $test->access_property($deploy, 'queue'), array(array("author" => "ale", "message" => "test commit", "action" => "download", "file" => "test.txt")));
unset($deploy);
        

$test->start('Read the request\'s basic information');
$deploy = new Aoloe\Deploy\GitHub();
$deploy->set_configuration($configuration_minimal);
$deploy->set_payload_from_request(array('payload' => json_encode($payload_base)));
$test->assert_identical('read user from simple request', $test->call_method($deploy, 'get_user_from_payload'), 'aoloe');
$test->assert_identical('read repository  simple request', $test->call_method($deploy, 'get_repository_from_payload'), 'repository_test');
$test->assert_identical('read branch from simple request', $test->call_method($deploy, 'get_branch_from_payload'), 'master');
$test->assert_true('is simple request valid', $deploy->is_valid_request());
unset($deploy);
$test->stop();

$test->start('Filename sanitization');
$deploy = new Aoloe\Deploy\GitHub();
$test->assert_identical('filename sanitized matches', $test->call_method($deploy, 'get_filename_sanitized', 'test.txt'), 'test.txt');
$test->assert_identical('filename sanitized removes ..', $test->call_method($deploy, 'get_filename_sanitized', 'test..txt'), 'test.txt');
$test->assert_identical('filename sanitized keeps /-_', $test->call_method($deploy, 'get_filename_sanitized', 'content/test_abc-def.txt'), 'content/test_abc-def.txt');
$test->assert_identical('filename sanitized keeps numbers', $test->call_method($deploy, 'get_filename_sanitized', 'content/test_0123.txt'), 'content/test_0123.txt');
$test->assert_identical('filename keeps common "accented" characters', $test->call_method($deploy, 'get_filename_sanitized', 'éàèöäüçÄÉÒ.txt'), 'éàèöäüçÄÉÒ.txt');
$test->assert_identical('filename refuses encoded unicode characters', $test->call_method($deploy, 'get_filename_sanitized', "Pr\u00eat-\u00e0-porter.txt"), 'Pru00eat-u00e0-porter.txt');
unset($deploy);
$test->stop();

$test->start('Read request adding content/test3.txt');
$deploy = new Aoloe\Deploy\GitHub();
$deploy->set_configuration($configuration_minimal + array('queue_file' => 'data/queue_from_test.json'));
$request = array('payload' => str_replace('\"', '"', file_get_contents('data/github_request_add_test3.json')));
// echo("<pre>request:\n".print_r($request, 1)."</pre>");
$deploy->set_payload_from_request($request);
$deploy->add_payload_to_queue();
$test->assert_identical("read queue with one download", $test->access_property($deploy, 'queue'), array(array("author" => "ale rimoldi", "message" => "Create test3.txt", "action" => "download", "file" => "content/test3.txt")));
$deploy->write_queue_to_file();
$test->assert_identical("write queue to file", file_get_contents('data/queue_from_test.json'), '[{"author":"ale rimoldi","message":"Create test3.txt","action":"download","file":"content\/test3.txt"}]');
$test->assert_identical("pull commit from queue", $test->call_method($deploy, 'pull_commit_from_queue'), array("author" => "ale rimoldi", "message" => "Create test3.txt", "action" => "download", "file" => "content/test3.txt"));
$test->assert_identical("after pull, queue is empty", $test->access_property($deploy, 'queue'), array());
$deploy->write_queue_to_file();
$test->assert_identical("queue file is an empty array after writing", file_get_contents('data/queue_from_test.json'), '[]');
unlink('data/queue_from_test.json');

$test->start('get file from github');
$test->assert_identical('get raw url', $test->call_method($deploy, 'get_raw_url_for_github', $configuration_minimal['username'], $configuration_minimal['repository'], $configuration_minimal['branch']), 'https://raw.githubusercontent.com/aoloe/repository_test/master/');
$url_base = $test->call_method($deploy, 'get_raw_url_for_github', $configuration_minimal['username'], 'scribus-newsletter');
$test->assert_false('get newsletter README', $test->call_method($deploy, 'get_url_content', $url_base.'README.md') == '');
$test->stop();

$test->start('get content/test3.txt');
$deploy = new Aoloe\Deploy\GitHub();
$deploy->set_configuration($configuration_minimal + array('queue_file' => 'data/queue_with_text3_to_add.json'));
$deploy->read_queue_from_file();
$test->assert_identical("queue with one download from test file", $test->access_property($deploy, 'queue'), array(array("author" => "ale rimoldi", "message" => "Create test3.txt", "action" => "download", "file" => "content/test3.txt")));
$test->assert_identical('pull commit', $test->call_method($deploy, 'pull_commit_from_queue'), array("author" => "ale rimoldi", "message" => "Create test3.txt", "action" => "download", "file" => "content/test3.txt"));
$test->assert_identical('empty pull commit on empty queue', $test->call_method($deploy, 'pull_commit_from_queue'), null);

unset($deploy);
$test->stop();

$test->start('synchronise with github');
$deploy = new Aoloe\Deploy\GitHub();
$configuration = $configuration_github + array('queue_file' => 'data/queue_with_text_from_github.json');
$deploy->set_configuration($configuration);
// Aoloe\debug('configuration', $configuration);
$deploy->read_queue_from_file() || debug('could not read the queue file');
$queue = $test->access_property($deploy, 'queue');
// Aoloe\debug('queue', $queue);
$target_file = $test->call_method($deploy, 'get_deployment_path', $queue[0]['file']);
$test->assert_false('tear up ensures that test.txt does not exist', file_exists($target_file));
$deploy->synchronize();
$test->assert_true('test.txt has been downloaded', file_exists($target_file));
!empty($target_file) && unlink($target_file);
$test->assert_false('tear down ensures that test.txt been deleted', file_exists($target_file));
// create a config file that gets a file inside of php-deploy-git/test/data
unset($deploy);
$test->stop();

$test->start('synchronise with github');
$deploy = new Aoloe\Deploy\GitHub();
$configuration = $configuration_github;
$deploy->set_configuration($configuration);
$deploy->add_all_to_queue();
$queue = $test->access_property($deploy, 'queue');
// Aoloe\debug('queue', $queue);
