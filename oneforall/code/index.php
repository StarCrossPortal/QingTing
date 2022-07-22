<?php
include "./vendor/autoload.php";
include "./common.php";

use app\model\AppModel;
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
    $toolPath = '/OneForAll/';
// 循环读取状态值,直到执行完成
    while (true) {
        $result = Db::table('control')->where(['ability_name' => 'oneforall', 'status' => 1])->find();
        if (empty($result)) {
            sleep(20);
            continue;
        }
        addlog("oneforall 开始工作");

        //读取目标数据
        $targetArr = Db::table('target')->where('id', 'NOT IN', function ($query) {
            $query->table('scan_log')->where(['tool_name' => 'oneforall', 'target_name' => 'target'])->field('data_id');
        })->where(['scan_status'=>1])->limit(20)->select()->toArray();

        foreach ($targetArr as $k => $v) {
            //执行检测脚本
            execTool($v, $toolPath);

            //录入检测结果
            writeData($toolPath, $v);

            //更新最后扫描的ID
            updateScanLog('oneforall', 'target', $v['id'] ?? 0);
        }

        // 修改插件的执行状态
        Db::table('control')->where(['ability_name' => 'oneforall'])->update(['status' => 0,'end_time' => date('Y-m-d H:i:s')]);

        addlog("oneforall 执行完毕");
        sleep(20);
    }

}


function writeData($toolPath, $v)
{
    $domain = parse_url($v['url'])['host'];
    $file_path = $toolPath . 'results/';
    $filename = getSavePathaa($domain, $file_path);
    if (!$filename) {
        return false;
    }
    $list = json_decode(file_get_contents($filename), true);
    if (empty($list)) {
        addlog(["OneForAll子域名扫描,内容获取失败:{$filename}"]);
        return false;
    }


    foreach ($list as $key => $val) {
        unset($val['id']);
        $val['tid'] = $v['id'];
        $val['user_id'] = $v['user_id'];


        Db::name('oneforall')->strict(false)->extra('IGNORE')->insert($val);

        //插入到漏洞表中
        Db::table('target_subdomain')->strict(false)->extra('IGNORE')->insert($val);
    }

    @unlink($filename);

    addlog(["oneforall 扫描成功数据已写入：", $v['url']]);
}

function execTool($v, $toolPath)
{

    $host = parse_url($v['url'])['host'];
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        addlog(["此地址不是域名:{$v['url']}"]);
        return false;
    }
    $host_arr = explode('.', $host);
    unset($host_arr[0]);

    $cmd = "cd {$toolPath}  && python3 ./oneforall.py --target {$host}  --fmt=json run";
    systemLog($cmd);

    return true;
}


function getSavePathaa($domain, $path)
{
    $arr = explode(".", $domain);
    //默认用二级主域名判断文件是否存在
    $resultPath = $path . $arr[count($arr) - 2] . "." . $arr[count($arr) - 1] . ".json";
    //如果不是二级主域名，用三级主域名
    if (!file_exists($resultPath) && (count($arr) >= 3)) {
        addlog(["OneForAll 扫描无结果", $domain, $resultPath]);
        $resultPath = $path . $arr[count($arr) - 3] . "." . $arr[count($arr) - 2] . "." . $arr[count($arr) - 1] . ".json";
    }

    //如果不是二级主域名，用三级主域名
    if (!file_exists($resultPath) && (count($arr) >= 4)) {
        addlog(["OneForAll 扫描无结果", $domain, $resultPath]);
        $resultPath = $path . $arr[count($arr) - 4] . "." . $arr[count($arr) - 3] . "." . $arr[count($arr) - 2] . "." . $arr[count($arr) - 1] . ".json";
    }

    //如果还不存在,则返回false
    if (!file_exists($resultPath)) {
        addlog(["OneForAll 扫描无结果", $domain, $resultPath]);
        return false;
    }

    return $resultPath;

}