<?php
include "./vendor/autoload.php";
include "./common.php";

use think\facade\Db;

$config = require('./config.php');
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
        $result = Db::table('control')->where(['ability_name' => 'xray', 'status' => 1])->find();
        if (empty($result)) {
            sleep(15);
            continue;
        }
        addlog("XRAY 开始工作");

        //读取目标数据
        $targetArr = Db::table('target')->where('id', 'NOT IN', function ($query) {
            $query->table('scan_log')->where(['tool_name' => 'xray', 'target_name' => 'target'])->field('data_id');
        })->limit(20)->select()->toArray();
        foreach ($targetArr as $value) {
            //定义变量
            list($url, $id, $user_id, $tid) = [$value['url'], $value['id'], $value['user_id'], $value['id'],];
            $pathArr = getSavePath($url, "xray", $id);

            //执行xray
            execXray($id, $url, $tid, $pathArr);
            //将数据写入到数据库
            writeData($pathArr, $url, $value, $user_id);
        }
        //更新最后扫描的ID
        updateScanLog('xray', 'target', $value['id'] ?? 0);

        // 修改插件的执行状态
        Db::table('control')->where(['ability_name' => 'xray'])->update(['status' => 0, 'end_time' => date('Y-m-d H:i:s')]);

        addlog("XRAY执行完毕");
        sleep(20);
    }

}


function writeData($pathArr, $url, $value, $user_id)
{
    //读取结果
    if (!file_exists($pathArr['tool_result'])) {
        addlog("xray扫描结果文件不存在:{$pathArr['tool_result']},扫描URL失败: {$url}");
        return false;
    }
    $data = json_decode(file_get_contents($pathArr['tool_result']), true);
    if (empty($data)) {
        addlog(["xray 结果解析失败", $data, file_get_contents($pathArr['tool_result'])]);
        return false;
    }
    //将结果保存到数据库
    foreach ($data as $val) {
        $newData = [
            'tid' => $value['id'],
            'create_time' => date('Y-m-d H:i:s', substr($val['create_time'], 0, 10)),
            'detail' => json_encode($val['detail'], JSON_UNESCAPED_UNICODE),
            'plugin' => json_encode($val['plugin'], JSON_UNESCAPED_UNICODE),
            'target' => json_encode($val['target'], JSON_UNESCAPED_UNICODE),
            'url' => $val['detail']['addr'],
            'url_id' => 0,
            'user_id' => $user_id,
            'poc' => $val['detail']['payload']
        ];
        echo "xray添加漏洞结果:" . json_encode($newData, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        Db::table('xray')->extra('IGNORE')->insert($newData);

        //插入到漏洞表中
        $newData['tool_name'] = 'xray';
        $newData['vul_type'] = $newData['plugin'];
        Db::table('bugs')->strict(false)->extra('IGNORE')->insert($newData);
    }
}

function execXray($id, $url, $tid, $pathArr)
{

    //初始化清理目录
//        file_exists($pathArr['tool_result']) && @unlink($pathArr['tool_result']);

    addlog(["XRAY开始执行扫描任务", $id, $url]);
    $path = "cd /data/tools/xray/ && ";

    //初始化清理目录
//    file_exists($pathArr['tool_result']) && unlink($pathArr['tool_result']);
//    file_exists($pathArr['cmd_result']) && unlink($pathArr['cmd_result']);
//    if (file_exists($pathArr['tool_result'])) {
//        addlog("{$pathArr['tool_result']} 结果文件已存在");
//        return true;
//    }

    // 设置代理
    $cmd = "{$path} ./xray_linux_amd64 webscan --url \"{$url}\"  --json-output  {$pathArr['tool_result']}";
    $result = [];
    execLog($cmd, $result);
    $result = implode("\n", $result);
    addlog(["xray漏洞扫描结束", $tid, $url, $cmd, base64_encode($result)]);
    $result = file_put_contents($pathArr['cmd_result'], $result);
    if ($result == false) {
        addlog(["xray写入执行结果失败", base64_encode($pathArr['cmd_result'])]);
    }

}
