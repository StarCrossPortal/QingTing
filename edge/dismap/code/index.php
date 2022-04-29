<?php
include "./vendor/autoload.php";
include "./common.php";

use app\model\PluginModel;
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
    $toolPath = '/data/tools/dismap/';
    $filename = '/tmp/dismap.json';
// 循环读取状态值,直到执行完成
    while (true) {
        $result = Db::table('control')->where(['ability_name' => 'dismap', 'status' => 1])->find();
        if (empty($result)) {
            sleep(15);
            continue;
        }
        addlog("dismap 开始工作");

        //读取目标数据
        $lastId = Db::table('scan_log')->where(['tool_name' => 'dismap', 'target_name' => 'target'])->value('data_id');
        $targetArr = Db::table('target')->where('id', '>', intval($lastId))->select()->toArray();


        foreach ($targetArr as $k => $v) {
            //执行检测脚本
            execTool($v, $toolPath);

            //录入检测结果
            writeData($toolPath, $v);

            //更新最后扫描的ID
            updateScanLog('dismap', 'target', $v['id'] ?? 0);
        }



        // 修改插件的执行状态
        Db::table('control')->where(['ability_name' => 'dismap'])->update(['status' => 0,'end_time' => date('Y-m-d H:i:s')]);

        addlog("dismap 执行完毕");
        sleep(20);
    }

}


function writeData($toolPath, $v)
{
    $filename = $toolPath . 'result.json';
    if (!file_exists($filename)) {
        addlog(["dismap扫描失败，url:{$v['url']}"]);
        return false;
    }
    //打开一个文件
    $result = file_get_contents($filename);
    if (empty($result) ) {
        addlog(["dismap 扫描目标结果为空", $v['url']]);
        return false;
    }
    $result = json_decode($result, true);


    //检测指正是否到达文件的未端
    foreach ($result as $arr) {
        //改变之前的key
        foreach($arr as $key => $val){
            if(strstr($key,".")){
                $nkey = str_replace(".","_",$key);
                $arr[$nkey] = $val;
                unset($arr[$key]);
            }
        }
        $data = ['tid' => $v['id'], 'user_id' => $v['user_id']];
        $data = array_merge($data, $arr);

        $id = Db::name('dismap')->strict(false)->insertGetId($data);

        //插入到漏洞表中
        $newData['name'] = 'dismap';
        $newData['plugin_id'] = $id;
        Db::table('target_feature')->strict(false)->extra('IGNORE')->insert($newData);

    }


}

function execTool($v, $toolPath)
{

    $filename = $toolPath . 'dismap.txt';
    $filenameJson = $toolPath . 'result.json';
    @unlink($filename);
    @unlink($filenameJson);
    $cmd = "cd $toolPath && ./dismap -u {$v['url']} -j {$filenameJson}";
    systemLog($cmd);

    return true;
}
