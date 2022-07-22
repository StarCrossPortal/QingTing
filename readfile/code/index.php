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
//    sleep(1000);
    $config = require('./config.php');
    //数据库配置信息,无需改动
    Db::setConfig($config);
}

function main()
{
    addlog("readfile 开始工作");
    $params = getParams();
    //读取目标数据,排除已经扫描过的目标
    $targetArr = Db::table('target')->where('id', 'NOT IN', function ($query) {
        $query->table('scan_log')->where(['tool_name' => 'readfile', 'target_name' => 'target'])->field('data_id');
    })->where(['scan_status' => 1])->limit(20)->select()->toArray();
    //
    foreach ($targetArr as $value) {
        //定义变量
        list($url, $id, $user_id, $tid) = [$value['url'], $value['id'], $value['user_id'], $value['id'],];
        //
        $pathArr = getSavePath($url, "readfile", $id);
        $inputType = "";
        $url = getTargetByUrl($url, $inputType);

        //执行readfile
        execTool($id, $url, $tid, $pathArr);
        //将数据写入到数据库
        writeData($pathArr, $url, $value, $user_id);
    }
    //更新最后扫描的ID
    updateScanLog('readfile', 'target', $value['id'] ?? 0);

    // 修改插件的执行状态
    Db::table('control')->where(['xflow_node_id' =>$params['xflow_node_id'],'task_version'=>$params['task_version']])->update(['status' => 0, 'end_time' => date('Y-m-d H:i:s')]);

    addlog("readfile执行完毕");


}

//将工具执行
function execTool($id, $url, $tid, $pathArr)
{
    $result = [];
    addlog(["readfile开始执行扫描任务", $id, $url]);
    $path = "cd /data/tools/readfile/ && ";

    // 通过系统命令执行工具
    $cmd = "{$path}   2>&1";
    execLog($cmd, $result);


    $result = implode("\n", $result);
    addlog(["readfile漏洞扫描结束", $tid, $url, $cmd, base64_encode($result)]);
    $result = file_put_contents($pathArr['cmd_result'], $result);
    if ($result == false) {
        addlog(["readfile写入执行结果失败", base64_encode($pathArr['cmd_result'])]);
    }
}


//写入数据到数据库
function writeData($pathArr, $url, $value)
{
    //读取命令终端返回结果
    if (!file_exists($pathArr['tool_result'])) {
        addlog("readfile 扫描结果文件不存在:{$pathArr['tool_result']},URL {$url}");
    }
    //读取命令写入文件的执行结果
    if (!file_exists($pathArr['cmd_result'])) {
        addlog("readfile 扫描结果文件不存在:{$pathArr['cmd_result']},URL {$url}");
    }

    //解析数据
    $toolResult = file_exists($pathArr['tool_result']) ? file_get_contents($pathArr['tool_result']) : "";
    $cmdResult = file_exists($pathArr['cmd_result']) ? file_get_contents($pathArr['cmd_result']) : "";


    //通过遍历数组方式,将结果保存到数据库
    $newData = [
        'tid' => $value['id'],
        'cmd_raw' => $cmdResult,
        'tool_raw' => $toolResult,
    ];

    Db::table('readfile')->extra('IGNORE')->insert($newData);

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
