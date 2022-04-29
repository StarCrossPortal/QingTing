<?php
include "./vendor/autoload.php";
include "./common.php";
$config = require('./config.php');

use think\facade\Db;

if (!file_exists("/tmp/init_lock.txt")) {
    sleep(20);
    file_put_contents("/tmp/init_lock.txt", 1);
}

Db::setConfig($config);

main();


function main()
{
    // 循环读取状态值,直到执行完成
    while (true) {
        $result = Db::table('control')->where(['ability_name' => 'rad', 'status' => 1])->find();
        if (empty($result)) {
            sleep(15);
            continue;
        }
        addlog("RAD 开始工作");
        //读取目标数据
        $lastId = Db::table('scan_log')->where(['tool_name' => 'rad', 'target_name' => 'target'])->value('data_id');
        $targetArr = Db::table('target')->where('id', '>', intval($lastId))->select()->toArray();

        foreach ($targetArr as $value) {
            //定义初始化变量
            list($url, $id, $user_id) = [$value['url'], $value['id'], $value['user_id']];
            $pathArr = getSavePath($url, "rad", $id);
            //初始化清理目录
            file_exists($pathArr['tool_result']) && @unlink($pathArr['tool_result']);
            //执行结果
            execRad($pathArr['tool_result'], $url);
            //写入结果
            writeRadData($pathArr, $id);
        }
        //更新最后扫描的ID
        updateScanLog('rad', 'target', $value['id'] ?? 0);

        // 修改插件的执行状态
        Db::table('control')->where(['ability_name' => 'rad'])->update(['status' => 0,'end_time' => date('Y-m-d H:i:s')]);

        addlog("RAD执行完毕");
        sleep(20);
    }
}


function writeRadData($pathArr, $id)
{
    //读取结果
    $urlList = json_decode(file_get_contents($pathArr['tool_result']), true);
    $urlList = filterUrl($urlList);
    //结果入库
    foreach ($urlList as $val) {
        $header = isset($val['Header']) ? json_encode($val['Header']) : "";
        $url = $val['URL'];
        addUrls($url, $id, $val['Method']);
        addRadUrl($url, $id, $val['Method'], $header);
    }

}

function filterUrl(array $urlList)
{

    foreach ($urlList as $key => $val) {
        $arr = parse_url($val['URL']);
        $blackExt = ['.js', '.css', '.json', '.png', '.jpg', '.jpeg', '.gif', '.mp3', '.mp4'];
        if (!isset($arr['query']) or (isset($arr['path']) && in_array_strpos($arr['path'], $blackExt)) or (strpos($arr['query'], '=') === false)) {
            addlog(["rad扫描跳过无意义URL", $val['URL']]);
            unset($urlList[$key]);
        }
    }

    return array_values($urlList);
}

function execRad($toolResult, $url)
{
    if (!file_exists($toolResult)) {
        $cmd = "cd /data/tools/rad && ./rad_linux_amd64 -t  \"{$url}\" -json {$toolResult}";
        addlog(["开始执行抓取URL地址命令", $cmd]);

        $result = [];
        execLog($cmd, $result);

        if (!file_exists($toolResult)) {
            addlog(["rad扫描失败,结果文件不存在", $toolResult]);
        }
    }
}

function addRadUrl($url, $tid, $method, $header)
{
    $data = ['url' => $url, 'tid' => $tid, 'method' => $method, 'header' => $header];
    Db::table('rad')->extra("IGNORE")->insert($data);
}

function addUrls($url, $tid, $method)
{
    $data = ['url' => $url, 'tid' => $tid, 'method' => $method];
    Db::table('urls')->extra("IGNORE")->insert($data);
}