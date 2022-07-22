<?php
include "./vendor/autoload.php";
include "./common.php";

use think\facade\Db;

//初始化操作
init();
//主程序
main();

function init()
{
    $config = require('./config.php');
    //数据库配置信息,无需改动
    Db::setConfig($config);
}

function main()
{
    $params = getParams();

    $tableName = $params['tableName'];
    $ddToken = $params['dd_token'];

    addlog("dingding 开始工作");

    //读取目标表数据
    $list = Db::table($tableName)->where(['xflow_node_id' => $params['source_xflow_node_id']])
        ->where('id', 'NOT IN', function ($query) use ($params) {
            $query->table('scan_log')->where(['tool_name' => $params['xflow_node_id'], 'target_name' => $params['tableName']])->field('id');
        })->limit(100)->select()->toArray();
    //遍历处理数据
    foreach ($list as $item) {
        //更新最后扫描的ID
        updateScanLog($params['xflow_node_id'], $params['tableName'], $item['id'] ?? 0);
        sendDingDing($ddToken, json_decode($item['raw_data'], true), $params);
        sleep(4);
    }


    // 修改插件的执行状态
    Db::table('control')->where(['xflow_node_id' => $params['xflow_node_id'], 'task_version' => $params['task_version']])->update(['status' => 0, 'end_time' => date('Y-m-d H:i:s')]);

    addlog("dingding执行完毕");
    sleep(20);
}

function sendDingDing($dd_token, array $rawData, array $info)
{
    $webhook = "https://oapi.dingtalk.com/robot/send?access_token={$dd_token}";

    $message = json_encode($rawData, JSON_UNESCAPED_UNICODE);

    $message = substr($message, 0, 1980);
    $data = array('msgtype' => 'markdown', 'markdown' => [
        'title' => '蜻蜓安全工作台提醒',
        'text' => $message,
    ]);
    $data_string = json_encode($data);

    $result = request_by_curl($webhook, $data_string);
    if ($result['errcode'] > 0) {
        addLog(["给用户发送钉钉通知失败", $result, $data, $webhook], false);
    }

    //将钉钉返回的数据存起来
    $data = ['raw_data' => json_encode($result, JSON_UNESCAPED_UNICODE), 'xflow_node_id' => $info['xflow_node_id']];
    Db::table('dingding')->strict(false)->extra('IGNORE')->insert($data);
}


function request_by_curl($remote_server, $post_string)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $remote_server);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json;charset=utf-8'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // 线下环境不用开启curl证书验证, 未调通情况可尝试添加该代码
    // curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
    // curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $data = curl_exec($ch);
    curl_close($ch);
    return json_decode($data, true);
}

function getParams()
{
    $params = getenv('params');
    if (empty($params)) {
        addlog("readurl 没有获取到环境变量");
        return false;
    }
    return json_decode(base64_decode($params), true);
}
