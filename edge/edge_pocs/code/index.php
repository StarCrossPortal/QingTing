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
    $urlField = $params['source_field'] ?? 'data';
    $xflowNodeId = $params['xflow_node_id'];


    addlog("edge_pocs 脚本 开始工作");

    //读取目标数据,排除已经扫描过的目标
    $list = Db::table($tableName)->where(['xflow_node_id' => $params['source_xflow_node_id']])->where('id', 'NOT IN', function ($query) use ($params) {
        $query->table('scan_log')->where(['tool_name' => $params['xflow_node_id'], 'target_name' => $params['tableName']])->field('id');
    })->limit(100)->select()->toArray();
    foreach ($list as $item) {
        //更新最后扫描的ID
        updateScanLog($params['xflow_node_id'], $params['tableName'], $item['id'] ?? 0);
        $itemData = json_decode($item['raw_data'], true);


        $data = execTool($itemData[$urlField], $params);

        if (empty($data['raw_data'])) {
            addLog(["edge_pocs扫描目标后返回结果为空", $data['raw_data']]);
            continue;
        }

        $data['xflow_node_id'] = $xflowNodeId;
        $data['hash'] = md5(json_encode($data));
        $bbb = Db::table('edge_pocs')->strict(false)->extra("IGNORE")->insert($data);
        var_dump($bbb);
    }

    //读取目标数据,排除已经扫描过的目标


    // 修改插件的执行状态
    Db::table('control')->where(['xflow_node_id' => $params['xflow_node_id'], 'task_version' => $params['task_version']])->update(['status' => 0, 'end_time' => date('Y-m-d H:i:s')]);

    addlog("edge_pocs 脚本 执行完毕");
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
function execTool($url, array $params)
{
    $hash = md5($url);
    $resultPath = "/tmp/{$hash}/tool.json";
    !file_exists(dirname($resultPath)) && mkdir(dirname($resultPath), 0777, true);
    !file_exists(dirname("/tmp/edge_pocs/")) && mkdir(dirname("/tmp/edge_pocs/"), 0777, true);

    $result = [];
    addlog(["XRAY开始执行扫描任务", $url]);
    $path = "cd /data/tools/edge_pocs/ && ";

    // 通过系统命令执行工具
    $pocfile = "/tmp/edge_pocs/bbb.yaml";
    if (!file_exists(dirname($pocfile))) mkdir(dirname($pocfile), 0777, true);
    file_put_contents($pocfile, $params['poc_code']);
    $cmd = "{$path} ./xray_linux_amd64 webscan --plugins phantasm --poc {$pocfile} --url \"{$url}\"  --json-output  {$resultPath}";
    exec($cmd, $result);


    $result = implode("\n", $result);
    $toolResult = file_exists($resultPath) ? file_get_contents($resultPath) : '';
    return ['cmd_result' => $result, 'raw_data' => $toolResult];
}