<?php
include "./vendor/autoload.php";
include "./common.php";

use think\facade\Db;

$config = require('./config.php');
require('./model.php');

//sleep(15);
Db::setConfig($config);

$serverAddr = getenv('serverAddr');
$token = getenv('token');
$nodeId = getenv('nodeId');
$usceId = $taskId = getenv('taskId');
$concurrent = getenv('concurrent');

$action = $argv[1];

//根据参数执行不同指令
if ($action == 'init') {
    //初始化数据
    initData($serverAddr, $usceId, $token);
} else if ($action == 'syncTarget') {
    //同步扫描目标
    insertTarget($serverAddr, $token, $usceId);
} else if ($action == 'down_action') {
    //同步扫描目标
    downAction($serverAddr, $token, $taskId);
} else if ($action == 'controlStatus') {
    //控制容器状态
    controlStatus($concurrent);
} else if ($action == 'uploadData') {
    //上传数据
    uploadData($serverAddr, $usceId, $token);
} else if ($action == 'heartbeat') {
    //心跳维持
    heartbeat($serverAddr, $nodeId, $token, $taskId);
} else {
    addlog("请输入相对应的指令");
}
