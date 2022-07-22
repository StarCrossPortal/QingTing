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
        $result = Db::table('control')->where(['ability_name' => 'nmap', 'status' => 1])->find();
        if (empty($result)) {
            sleep(15);
            continue;
        }
        addlog("nmap 开始工作");

        //读取目标数据,排除已经扫描过的目标
        $targetArr = Db::table('ports')->where('id', 'NOT IN', function ($query) {
            $query->table('scan_log')->where(['tool_name' => 'nmap', 'target_name' => 'ports'])->field('data_id');
        })->where(['scan_status'=>1])->limit(20)->select()->toArray();
        //
        foreach ($targetArr as $value) {
            //定义变量
            list($id, $user_id, $tid, $port) = [$value['id'], $value['user_id'], $value['id'], $value['port'],];
            $url = Db::table('target')->where(['id' => $value['tid']])->value('url');
            if (empty($url)) {
                addlog("通过TID没有找到URL,$tid",);
                continue;
            }
            $pathArr = getSavePath($url, "nmap", $id);
            $inputType = "domain";
            $url = getTargetByUrl($url, $inputType);

            //执行nmap
            execTool($id, $url, $tid, $pathArr, $port);
            //将数据写入到数据库
            writeData($pathArr, $url, $value);
        }
        //更新最后扫描的ID
        updateScanLog('nmap', 'ports', $value['id'] ?? 0);

        // 修改插件的执行状态
        Db::table('control')->where(['ability_name' => 'nmap'])->update(['status' => 0, 'end_time' => date('Y-m-d H:i:s')]);

        addlog("nmap执行完毕");
        sleep(20);
    }

}

//将工具执行
function execTool($id, $url, $tid, $pathArr, $port)
{
    $result = [];
    addlog(["nmap开始执行扫描任务", $id, $url]);
    $path = "cd /data/tools/nmap/ && ";

    // 通过系统命令执行工具
    $cmd = "{$path} nmap -p {$port} -sS -Pn -T4  $url | grep open | grep -v Discovered |grep -v grep  2>&1";
    execLog($cmd, $result);


    $result = implode("\n", $result);
    addlog(["nmap漏洞扫描结束", $tid, $url, $cmd, base64_encode($result)]);
    $result = file_put_contents($pathArr['cmd_result'], $result);
    if ($result == false) {
        addlog(["nmap写入执行结果失败", base64_encode($pathArr['cmd_result'])]);
    }
}


//写入数据到数据库
function writeData($pathArr, $url, $value)
{
    //读取命令终端返回结果
    if (!file_exists($pathArr['tool_result'])) {
        addlog("nmap 扫描结果文件不存在:{$pathArr['tool_result']},URL {$url}");
    }
    //读取命令写入文件的执行结果
    if (!file_exists($pathArr['cmd_result'])) {
        addlog("nmap 扫描结果文件不存在:{$pathArr['cmd_result']},URL {$url}");
    }

    //解析数据
    $toolResult = file_exists($pathArr['tool_result']) ? file_get_contents($pathArr['tool_result']) : "";
    $cmdResult = file_exists($pathArr['cmd_result']) ? file_get_contents($pathArr['cmd_result']) : "";

    //通过遍历数组方式,将结果保存到数据库
    $newData = [
        'tid' => $value['tid'],
        'port_id' => $value['id'],
        'cmd_raw' => $cmdResult,
        'tool_raw' => $toolResult,
    ];

    Db::table('nmap')->extra('IGNORE')->insert($newData);

}

