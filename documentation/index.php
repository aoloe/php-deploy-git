<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Git Deploy / Github: Documentation</title>
<link href='http://fonts.googleapis.com/css?family=Fira+Sans:300,400,300italic,400italic' rel='stylesheet' type='text/css'>
<link href='http://fonts.googleapis.com/css?family=Fira+Mono' rel='stylesheet' type='text/css'>
<style>
    .documentation {
        font-family: "Fira Sans", "Source Sans Pro", Helvetica, Arial, sans-serif;
        font-weight: 400;
    }
    .documentation h2 {
        color: #f80;
        font-size:1.125em;
        font-weight:normal;
    }
    .documentation p.signature {
        padding-top:0px;
        margin-top:13px;
        padding-bottom:0px;
        margin-bottom:0px;
        font: normal 0.875rem/1.5rem "Fira Mono", monospace;
    }
    .documentation p.signature span.modifier {
        color: #333;
    }
    .documentation p.signature  span.type {
        color: #693;
    }
    .documentation p.signature  span.name {
        color: #369;
    }
    .documentation p.description {
        padding-top:6px;
        margin-top:0px;
        padding-bottom:0px;
        margin-bottom:0px;
    }
</style>
</head>
<body>
<div class="documentation">
<?php
include('../vendor/autoload.php');

include('../src/GitHub.php');

$documentation = new Aoloe\Documentation('Aoloe\Deploy\GitHub');
$documentation->parse();
$documentation->render();
?>
</div>
</body>
</html>
