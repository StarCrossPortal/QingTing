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
    //首次运行延时20秒,等待MySQL初始化完成,再执行主程序
    if (!file_exists("/tmp/init_lock.txt")) {
        sleep(20);
        file_put_contents("/tmp/init_lock.txt", 1);
    }
    //数据库配置信息,无需改动
    Db::setConfig($config);
}

function main()
{
// 循环读取状态值,直到执行完成
    while (true) {
        //程序是否执行存放与状态控制表中,如果可以执行才执行,否则休眠。
        $result = Db::table('control')->where(['ability_name' => 'webcrack', 'status' => 1])->find();
        if (empty($result)) {
            sleep(15);
            continue;
        }
        addlog("webcrack 开始工作");

        //读取目标数据,排除已经扫描过的目标
        $targetArr = Db::table('target')->where('id', 'NOT IN', function ($query) {
            $query->table('scan_log')->where(['tool_name' => 'webcrack', 'target_name' => 'target'])->field('data_id');
        })->where(['scan_status'=>1])->limit(20)->select()->toArray();
        //
        foreach ($targetArr as $value) {
            //定义变量
            list($url, $id, $user_id, $tid) = [$value['url'], $value['id'], $value['user_id'], $value['id'],];
            //
            $pathArr = getSavePath($url, "webcrack", $id);
            $inputType = "##INPUT_TYPE##";
            $url = getTargetByUrl($url, $inputType);

            //执行webcrack
            execTool($id, $url, $tid, $pathArr);
            //将数据写入到数据库
            writeData($pathArr, $url, $value, $user_id);
        }
        //更新最后扫描的ID
        updateScanLog('webcrack', 'target', $value['id'] ?? 0);

        // 修改插件的执行状态
        Db::table('control')->where(['ability_name' => 'webcrack'])->update(['status' => 0, 'end_time' => date('Y-m-d H:i:s')]);

        addlog("webcrack执行完毕");
        sleep(20);
    }

}

//将工具执行
function execTool($id, $url, $tid, $pathArr)
{
    $result = [];
    addlog(["webcrack开始执行扫描任务", $id, $url]);
    $path = "cd /data/tools/webcrack/ && ";

    // 通过系统命令执行工具
    $cmd = "{$path} ##EXECTOOL##  2>&1";
    execLog($cmd, $result);


    $result = implode("\n", $result);
    addlog(["webcrack漏洞扫描结束", $tid, $url, $cmd, base64_encode($result)]);
    $result = file_put_contents($pathArr['cmd_result'], $result);
    if ($result == false) {
        addlog(["webcrack写入执行结果失败", base64_encode($pathArr['cmd_result'])]);
    }
}


//写入数据到数据库
function writeData($pathArr, $url, $value)
{
    //读取命令终端返回结果
    if (!file_exists($pathArr['tool_result'])) {
        addlog("webcrack 扫描结果文件不存在:{$pathArr['tool_result']},URL {$url}");
    }
    //读取命令写入文件的执行结果
    if (!file_exists($pathArr['cmd_result'])) {
        addlog("webcrack 扫描结果文件不存在:{$pathArr['cmd_result']},URL {$url}");
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

    Db::table('webcrack')->extra('IGNORE')->insert($newData);

}

