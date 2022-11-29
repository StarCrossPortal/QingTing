<?php

include "./vendor/autoload.php";
include "./common.php";
require('./model.php');

use think\facade\Db;

//数据库配置信息,无需改动
$config = require('./config.php');
Db::setConfig($config);


$info = getParams();

$serverAddr = $info['serverAddr'];
$token = $info['token'];
$nodeId = $info['node_id'];
$usceId = $info['xflow_id'];
$concurrent = getenv('concurrent');

$action = $argv[1];

//根据参数执行不同指令
if ($action == 'init') {
    //初始化数据
    initData($serverAddr, $usceId, $token);
}else if ($action == 'Clog') {
    //日志收集
    $path='clog.py';
    $cmd = "cd /root/code && python3 {$path}";
    $output = exec($cmd, $output);
} else if ($action == 'uploadData') {
    //上传数据
//    uploadData($serverAddr, $usceId, $token);
    $path='mqtt_upload_data.py';
    $cmd = "cd /root/code && python3 {$path} {$usceId} {$token}";
    $output = exec($cmd, $output);
} else if ($action == 'test_received') {
    //心跳维持
    received($usceId, $token);
} else if ($action == 'heartbeat') {
    //心跳维持
    heartbeat($serverAddr, $nodeId, $token, $usceId);
}  else if ($action == 'Db_test_connect') {
    //测试数据库连接
    Db_test_connect($usceId);
}  else if ($action == 'readResult') {
    //测试数据库连接
    readResult();
} else {
    addlog("请输入相对应的指令");
}
