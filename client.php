#!/usr/bin/env php
<?php
error_reporting(E_ERROR);
require_once 'SwooleClient.php';

$server        = new \SwooleClient();
$server->_host = '127.0.0.1';
$server->_port = 9501;
try {
    $server->connect();
} catch (\Exception $e) {
    echo $e->getMessage();
    exit();
}

$test = json_encode(['class_name' => 'Tool', 'action_name' => 'index', 'request' => []]);
$server->send($test);
$server->close();
