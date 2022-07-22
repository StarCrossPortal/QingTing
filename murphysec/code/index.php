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
    $urlField = $params['url'];
    $urlField = 'data';
    $mf_token = $params['mf_token'];
    $sourceNodeId = $params['source_xflow_node_id'];

    addlog("murphysec 脚本 开始工作");

    //读取目标数据,排除已经扫描过的目标
    $list = Db::table($tableName)->where(['xflow_node_id' => $sourceNodeId])
        ->where('id', 'NOT IN', function ($query) use ($params) {
            $query->table('scan_log')->where(['tool_name' => $params['xflow_node_id'], 'target_name' => $params['tableName']])->field('id');
        })->limit(100)->select()->toArray();

    foreach ($list as $item) {
        updateScanLog($params['xflow_node_id'], $params['tableName'], $item['id'] ?? 0);
        $itemData = json_decode($item['raw_data'], true);

        if (empty($itemData)) {
            addLog("处理的数据为空");
            continue;
        }

        if (!isset($itemData[$urlField]) || empty($itemData[$urlField])) {
            addLog("处理的字段不存在", $itemData);
            continue;
        }

        $path = $itemData[$urlField];
        $data = execTool($path, $mf_token);

        $data['xflow_node_id'] = $params['xflow_node_id'];
        $data['hash'] = md5(json_encode($data));
        $bbb = Db::table('murphysec')->strict(false)->extra("IGNORE")->insert($data);
    }

    // 修改插件的执行状态
    Db::table('control')->where(['xflow_node_id' =>$params['xflow_node_id'],'task_version'=>$params['task_version']])->update(['status' => 0, 'end_time' => date('Y-m-d H:i:s')]);

    addlog("murphysec 脚本 执行完毕");
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

//将工具执行
function execTool(string $url, string $mf_token)
{
    $hash = md5($url);
    $resultPath = "/tmp/{$hash}/tool.json";
    !file_exists(dirname($resultPath)) && mkdir(dirname($resultPath), 0777, true);

    $result = [];
    addlog(["墨菲开始执行扫描任务", $url]);

    // 通过系统命令执行工具
    $cmd = "murphysec scan $url --token {$mf_token}  --json > /tmp/aa.json";
    exec($cmd, $result);
    $result = file_get_contents("/tmp/aa.json");

    return ['raw_data' => $result];
}
