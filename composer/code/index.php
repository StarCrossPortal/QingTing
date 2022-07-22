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

    $codeBasePath = "/data/code";
    !file_exists($codeBasePath) && mkdir($codeBasePath, 0777, true);
// 循环读取状态值,直到执行完成
    while (true) {
        $result = Db::table('control')->where(['ability_name' => 'composer', 'status' => 1])->find();
        if (empty($result)) {
            sleep(15);
            continue;
        }
        addlog("composer 开始工作");

        //读取目标数据
        $targetArr = Db::table('target')->where('url', 'not null')->where('id', 'NOT IN', function ($query) {
            $query->table('scan_log')->where(['tool_name' => 'composer', 'target_name' => 'target'])->field('data_id');
        })->where(['scan_status'=>1])->limit(20)->select()->toArray();

        //调试代码
//        $targetArr = Db::table('target')->where(['id' => 8862])->limit(20)->select()->toArray();

        foreach ($targetArr as $k => $v) {
            $urlInfo = parse_url($v['url']);
            $prName = $urlInfo['path'] ?? md5($v['url']);
            $prName = str_replace("/", '_', trim($prName, '/'));
            $codePath = "$codeBasePath/{$prName}";

            //执行下载代码
            $result = downCode($codeBasePath, $prName, $v['url'], []);
            if (!$result) {
                sleep(5);
                continue;
            }

            //录入检测结果
            writeData($codePath, $v);

            //更新最后扫描的ID
            updateScanLog('composer', 'target', $v['id'] ?? 0);
        }


        // 修改插件的执行状态
        Db::table('control')->where(['ability_name' => 'composer'])->update(['status' => 0, 'end_time' => date('Y-m-d H:i:s')]);

        addlog("composer 执行完毕");
        sleep(20);
    }
}


function writeData(string $codePath, array $v)
{

    $fileArr = getFilePath($codePath, 'composer.lock');
    if (!$fileArr) {
        systemLog("rm -rf {$codePath}");
        addlog("[{$v['name']}]扫描composer依赖失败,composer.lock 依赖文件不存在:{$codePath}");
        return false;
    }

    foreach ($fileArr as $value) {
        $json = file_get_contents($value['file']);
        if (empty($json)) {
            addlog("项目文件内容为空:{$value['file']}");
            continue;
        }
        $json = str_replace('"require-dev"', '"require_dev"', $json);
        $json = str_replace('"notification-url"', '"notification_url"', $json);
        $arr = json_decode($json, true);
        $packages = $arr['packages'];

        foreach ($packages as &$val) {
            foreach ($val as $k => $temp) {
                $val[$k] = is_string($temp) ? $temp : json_encode($temp, JSON_UNESCAPED_UNICODE);
            }

            $val['tid'] = $v['id'];

            Db::name('composer')->strict(false)->extra("IGNORE")->insert($val);
        }
    }

    systemLog("rm -rf {$codePath}");
    return true;
}

function getFilePath($dir, $filename, $level = 1)
{
    static $files = [];
    if (!is_dir($dir)) {
        return $files;
    }
    if ($level > 3) {
        return $files;
    }

    foreach (scandir($dir) as &$file_name) {
        if ($file_name == '.' || $file_name == '..' || (file_exists($file_name) && $file_name != $filename)) {
            continue;
        }
        if ($file_name == $filename) {
            $files[] = [
                'filepath' => $dir,
                'filename' => $file_name,
                'file' => $dir . "/{$filename}"
            ];
        }
        if (is_dir($dir . DIRECTORY_SEPARATOR . $file_name)) {
            getFilePath($dir . DIRECTORY_SEPARATOR . $file_name, $filename, $level + 1);
        }
    }
    return $files;
}


