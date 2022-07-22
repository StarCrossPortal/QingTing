<?php
include "./vendor/autoload.php";
include "./common.php";

use app\model\PluginModel;
use think\facade\Db;
$config = require('./config.php');
if (!file_exists("/tmp/init_lock.txt")) {
//    sleep(1000);
    file_put_contents("/tmp/init_lock.txt", 1);
}
Db::setConfig($config);


main();

function main()
{
    $toolPath = '/data/tools/dirmap/';
    $filename = '/tmp/dirmap.json';
// 循环读取状态值,直到执行完成
    while (true) {
        $result = Db::table('control')->where(['ability_name' => 'dirmap', 'status' => 1])->find();
        if (empty($result)) {
            sleep(15);
            continue;
        }
        addlog("dirmap 开始工作");

        //读取目标数据
        $targetArr = Db::table('target')->where('id', 'NOT IN', function ($query) {
            $query->table('scan_log')->where(['tool_name' => 'dirmap', 'target_name' => 'target'])->field('data_id');
        })->where(['scan_status'=>1])->limit(20)->select()->toArray();

        foreach ($targetArr as $k => $v) {
            //执行检测脚本
            execTool($v, $toolPath);

            //录入检测结果
            writeData($toolPath, $v);

            //更新最后扫描的ID
            updateScanLog('dirmap', 'target', $v['user_id'] ?? 0);
        }


        // 修改插件的执行状态
        Db::table('control')->where(['ability_name' => 'dirmap'])->update(['status' => 0,'end_time' => date('Y-m-d H:i:s')]);

        addlog("dirmap 执行完毕");
        sleep(20);
    }

}


function writeData($file_path, $v)
{

    $host = parse_url($v['url'])['host'];
    $port = parse_url($v['url'])['port'] ?? null;
    $port = $port ? "_{$port}" : "";
    $filename = $file_path . "output/{$host}{$port}.txt";
    if (!file_exists($filename)) {
        addlog(["dirmap扫描结果文件不存在:{$filename}", $v]);
        return false;
    }
    //打开一个文件
    $file = fopen($filename, "r");
    //检测指正是否到达文件的未端
    $data = [];
    while (!feof($file)) {
        $result = fgets($file);
        if (empty($result)) {
            addlog(["dirmap 扫描目标结果为空", $v['url']]);
            continue;
        }
        $arr = explode('http', $result);
        $regex = "/(?:\[)(.*?)(?:\])/i";
        preg_match_all($regex, trim($arr[0]), $acontent);
        $data = [
            'url' => trim('http' . $arr[1]),
            'code' => isset($acontent[1][0]) ? $acontent[1][0] : '',
            'type' => isset($acontent[1][1]) ? $acontent[1][1] : '',
            'size' => isset($acontent[1][2]) ? $acontent[1][2] : '',
            'tid' => $v['id'],
            'user_id' => $v['user_id'],
        ];

        Db::name('dirmap')->extra('IGNORE')->insert($data);

        //插入到漏洞表中
//        Db::table('urls')->strict(false)->extra('IGNORE')->insert($data);

    }
    //关闭被打开的文件
    fclose($file);

    @unlink($filename);

    addlog(["dirmap 扫描成功数据已写入：", $v['url']]);
}

function execTool($v, $toolPath)
{
    $cmd = "cd {$toolPath}  && python3 ./dirmap.py -i {$v['url']} -lcf";
    systemLog($cmd);

    return true;
}
