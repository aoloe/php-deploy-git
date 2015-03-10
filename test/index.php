<?php

/**
 * being tested on ww.xox.ch/deploy/ [laptop]
 */

include('../vendor/autoload.php');
include('../src/GitHub.php');

$test = new Aoloe\Test();

$configuration_minimal = array (
    'username' => 'aoloe',
    'repository' => 'repository_test',
    'branch' => 'master',
);

$payload_base = array (
    'repository' => array (
        'full_name' => 'aoloe/repository_test',
    ),
    'ref' => 'refs/heads/master',
    'commit' => array(),
);
/*
$commit_base = array (
    'author' => array (
        'name' => 'ale',
    ),
    'timestamp' => date('Y-m-d\TH:i:sT'),
    'message' => '',
    'added' => array(),
    'modified' => array(),
    'removed' => array(),
);
*/

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
$request = array('payload' => str_replace('\"', '"', file_get_contents('github_request_add_test3.json')));
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

$test->start('get content/test3.txt');
$deploy = new Aoloe\Deploy\GitHub();
$deploy->set_configuration($configuration_minimal + array('queue_file' => 'data/queue_from_test.json'));
$request = array('payload' => str_replace('\"', '"', file_get_contents('github_request_add_test3.json')));


$test->assert_identical('files to get', $test->call_method($deploy, 'get_files_to_get'), array('content/test3.txt'));
$test->assert_identical('files to delete', $test->call_method($deploy, 'get_files_to_delete'), array());
$test->assert_identical('commit descriptions', $test->call_method($deploy, 'get_commit_description'), json_decode('["06.09.2014 13:06, ale rimoldi: Create test3.txt"]'));
unset($deploy);
$test->stop();

$test->start('Add and remove the files');
$filename = 'content/test2.md';
if (file_exists($filename)) {
    unlink($filename);
}
$github = new Aoloe\Deploy\GitHub();
$request = array('payload' => json_encode(
    array_merge(
        $payload_base,
        array ('commits' => array(array_merge($commit_base, array('added' => array('content/test2.md')))))
    )
));
$github->read($request);
$github->synchronize();
$test->assert_true('got test2.md file ', file_exists($filename));
$test->assert_identical('check test2.md content ', file_get_contents($filename), "# second test\n");
$test->assert_identical('check add in log', $github->get_log(), '["+ content\/test2.md"]');
$github->clear_log();
$github->set_deployed_base_path('test');
$github->synchronize();
$test->assert_true('got test2.md file into deployed_base_path', file_exists('test/'.$filename));
$test->assert_identical('check add in log', $github->get_log(), '["+ test\/content\/test2.md"]');
$github->clear_log();
$github->set_deployed_base_path();
// echo("<pre>request:\n".print_r($request, 1)."</pre>");
$request = array('payload' => json_encode(
    array_merge(
        $payload_base,
        array ('commits' => array(array_merge($commit_base, array('removed' => array('content/test2.md')))))
    )
));
$github->read($request);
$github->synchronize();
$test->assert_true('deleted test2.md file', !file_exists($filename));
$test->assert_identical('check delete in log', $github->get_log(), '["- content\/test2.md"]');
$github->set_deployed_base_path('test');
$github->synchronize();
$test->assert_true('delete test2.md file from deployed_base_path', !file_exists('test/'.$filename));
$github->set_deployed_base_path();
if (file_exists('test/content')) {
    rmdir('test/content');
}
$test->stop();

$test->start('Rewrite URLs');
$github = new Aoloe\Deploy\GitHub();
$test->assert_identical('don\'t remove no base path', $test->call_method($github, 'get_file_in_github_basepath', 'htdocs/test2.md'), 'htdocs/test2.md');
$github->set_github_base_path('htdocs');
$test->assert_identical('remove base path', $test->call_method($github, 'get_file_in_github_basepath', 'htdocs/test2.md'), 'test2.md');
$test->assert_identical('ignore file outside of base path', $test->call_method($github, 'get_file_in_github_basepath', 'content/test2.md'), null);

$base_path = 'content/';
$filename = 'test/test.md';
if (file_exists($filename)) {
    unlink($deploy_path.$filename);
}
$github = new Aoloe\Deploy\GitHub();
$github->set_github_base_path($base_path);
// $github->set_deployed_base_path($deploy_path);
$request = array('payload' => json_encode(
    array_merge(
        $payload_base,
        array ('commits' => array(array_merge($commit_base, array('added' => array($base_path.$filename)))))
    )
));
$github->read($request);
$github->synchronize();

$test->assert_true('get content/test/test.md into test/test.md ', file_exists($filename));
// echo("<pre>filename content:\n".print_r(file_get_contents($filename), 1)."</pre>");
$test->assert_identical('check content of test/test.md ', file_get_contents($filename), "this is also a test...\n");
if (file_exists($filename)) {
    unlink($filename);
}
$test->stop();
