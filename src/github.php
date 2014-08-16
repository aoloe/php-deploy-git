<?php
// error_reporting(E_ALL);
// ini_set('display_errors', '1');
$secret='imfromgithub';
// echo('<pre>'.print_r($_REQUEST, 1).'</pre>');

$branch = 'master';
$github_base_bath = '';
$deployed_base_bath = 'content/';
$log_file = 'log.txt';

$payload = null;
if (array_key_exists('payload', $_REQUEST)) {
    file_put_contents('latest', $_REQUEST['payload']);
    $payload = json_decode($_REQUEST['payload'], true);
} elseif (empty($_REQUEST)) {
    if (file_exists('latest')) {
        $latest = file_get_contents('latest');
        if (true) { // set to true for reading latest instead of the github request
            $payload = json_decode($latest, true);
        } else {
            // echo('<pre>latest: '.$latest.'</pre>');
            echo('<pre>'.print_r(json_decode($latest, true), 1).'</pre>');
            die();
        }
    }
    // echo('<pre>'.$latest.'</pre>');
    // echo('<pre>'.print_r(json_decode($latest, true), 1).'</pre>');
}

// echo('<pre>payload: '.print_r($payload, 1).'</pre>');

$github_user = null;
$github_repository = null;
$github_branch = null;

if (isset($payload)) {
    if (array_key_exists('repository', $payload)) {
        list($github_user, $github_repository) = explode('/', $payload['repository']['full_name']);
    }
    if (array_key_exists('ref', $payload)) {
        $github_branch = array_slice(explode('/', $payload['ref']), 2, 1);
        $github_branch = reset($github_branch);
    }
}

// echo('<pre>user: '.print_r($github_user, 1).'</pre>');
// echo('<pre>repository: '.print_r($github_repository, 1).'</pre>');
// echo('<pre>branch: '.print_r($github_branch, 1).'</pre>');

// echo('<pre>'.print_r($payload, 1).'</pre>');
if (isset($payload)) {
    if ($github_branch != $branch) {
        unset($payload);
    }
}

// echo('<pre>'.print_r($payload, 1).'</pre>');

$upload = array();
$remove = array();
if (isset($payload)) {
    if (array_key_exists('commits', $payload) && !empty($payload['commits'])) {
        foreach ($payload['commits'] as $item) {
            foreach ($item['added'] as $iitem) {
                if (!empty($iitem) && ($iitem === get_filename_sanitized($iitem))) {
                    $upload[] = $iitem;
                }
            }
            foreach ($item['modified'] as $iitem) {
                if (!empty($iitem) && ($iitem === get_filename_sanitized($iitem))) {
                    $upload[] = $iitem;
                }
            }
            foreach ($item['removed'] as $iitem) {
                if (!empty($iitem) && ($iitem === get_filename_sanitized($iitem))) {
                    $remove[] = $iitem;
                }
            }
        }
        if (isset($log_file) && !empty($log_file)) {
            // file_put_contents($log_file, $item['message']."\n", FILE_APPEND);
            $fp = fopen($log_file, 'a');
            fputcsv($fp, array(date('Y-m-d'), $item['message'], (empty($upload) ? '' : '+'.implode('+', $upload)) . (empty($remove) ? '' : '-'.implode('-', $remove))));
            fclose($fp);
        }
    }
}

// echo('<pre>upload: '.print_r($upload, 1).'</pre>');
// echo('<pre>remove: '.print_r($remove, 1).'</pre>');

foreach ($upload as $item) {
    $url = get_raw_url($github_user, $github_repository, $github_branch).$item;
    // echo('<pre>url: '.print_r($url, 1).'</pre>');
    $file_content = get_url_content($url);
    // echo('<pre>file_content: '.print_r($file_content, 1).'</pre>');
    file_put_contents($deployed_base_bath.$item, $file_content);
}

foreach ($remove as $item) {
    $filename = $deployed_base_bath.$item;
    if (file_exists($filename)) {
        unlink($filename);
    }
}


function get_url_content($url) {
    $result = '';
    // debug('url', $url);
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
    // debug('info', $info);
    if (($info['http_code'] == '404') || ($info['http_code'] == '403')) {
        // debug('get_gitapi_raw: invalid url', $url);
        $result = '';
    }
    curl_close($ch);
    // debug('result', $result);
    // echo('<pre>result: '.print_r($result, 1).'</pre>');
    return $result;
}

function get_raw_url($user, $repository, $branch = null) {
    return strtr(
        'https://raw.githubusercontent.com/$user/$repository/$branch/',
        array(
            '$user' => $user,
            '$repository' => $repository,
            '$branch' => isset($branch) ? $branch : 'master',
        )
    );
}

function get_filename_sanitized($filename) {
    //echo('<pre>filename: '.print_r($filename, 1).'</pre>');
    $result = preg_replace("([^\w\s\d\-_~,;:\[\]\(\].]|[\.]{2,})", '', $filename);
    //echo('<pre>result: '.print_r($result, 1).'</pre>');
    return $result;
}
