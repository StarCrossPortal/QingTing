<?php
include "./vendor/autoload.php";
require "./functions.php";
require "./common.php";

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
    $toolPath = '/data/tools/';
    $codeBasePath = "/data/code";
    !file_exists($codeBasePath) && mkdir($codeBasePath, 0777, true);
// 循环读取状态值,直到执行完成
    while (true) {
        $result = Db::table('control')->where(['ability_name' => 'hema', 'status' => 1])->find();
        if (empty($result)) {
            sleep(15);
            continue;
        }
        addlog("hema 开始工作");
        
        
        //读取目标数据
        $targetArr = Db::table('target')->where('id', 'NOT IN', function ($query) {
            $query->table('scan_log')->where(['tool_name' => 'hema', 'target_name' => 'target'])->field('data_id');
        })->where(['scan_status'=>1])->limit(20)->select()->toArray();
        
        //调试代码
//        $targetArr = Db::table('target')->limit(20)->select()->toArray();
        foreach ($targetArr as $k => $v) {
            
            $urlInfo = parse_url($v['url']);
            $prName = $urlInfo['path'] ?? md5($v['url']);
            $prName = str_replace("/", '_', trim($prName, '/'));

            $outpath = "/tmp/{$prName}.json";
            $codePath = "$codeBasePath/{$prName}";

            //执行下载代码
            $result = downCode($codeBasePath, $prName, $v['url'], []);
            if (!$result) {
                sleep(5);
                continue;
            }
            //执行检测脚本
            execTool($codePath, $outpath);

            //录入检测结果
            writeData($codePath, $toolPath, $v['id']);

            //更新最后扫描的ID
            updateScanLog('hema', 'target', $v['id'] ?? 0);
        }


        // 修改插件的执行状态
        Db::table('control')->where(['ability_name' => 'hema'])->update(['status' => 0, 'end_time' => date('Y-m-d H:i:s')]);

        addlog("hema 执行完毕");
        sleep(20);
    }

}

function cleanString($name)
{

    return $name;
}

function execTool(string $codePath, string $outPath)
{
    $filename = $outPath . '/result.csv';
    systemLog("rm -rf $filename");


    $cmd = "cd /data/tools/ && ./hm scan {$codePath}";
    systemLog($cmd);

    systemLog("rm -rf {$codePath}");
    return true;

}

function writeData($codePath, string $toolPath, $tid = 0)
{

    $outPath = "{$toolPath}/result.csv";
    if (!file_exists($outPath)) {
        return false;
    }
    $result = readCsv($codePath, $outPath);
    var_dump($result);
    unset($result[0]);
    foreach ($result as $val) {
        $data = [
            'tid' => $tid,
            'create_time' => date('Y-m-d H:i:s', time()),
            'user_id' => 0,
            'type' => $val[1],
            'filename' => $val[2],
        ];
        Db::name('hema')->extra("IGNORE")->insert($data);
    }

    return true;
}

/**
 * [ReadCsv 读取CSV为数组]
 * @param string $uploadfile [文件路径]
 */
function readCsv($codeBasePath, $uploadfile = '')
{
    $file = fopen($uploadfile, "r");
    while (!feof($file)) {
        $data[] = fgetcsv($file);
    }
//    $data = eval('return ' . iconv_gbk_to_uft8(var_export($data, true)) . ';');
    foreach ($data as $key => &$value) {
        if (!$value) {
            unset($data[$key]);
        }
        $value[2] = str_replace("{$codeBasePath}/", "", $value[2]);
    }
    fclose($file);
    return $data;
}


