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
    $toolPath = '/data/tools/vulmap/';
// 循环读取状态值,直到执行完成
    while (true) {
        $result = Db::table('control')->where(['ability_name' => 'vulmap', 'status' => 1])->find();
        if (empty($result)) {
            sleep(3);
            continue;
        }
        addlog("vulmap 开始工作");

        //读取目标数据
        $targetArr = Db::table('target')->where('id', 'NOT IN', function ($query) {
            $query->table('scan_log')->where(['tool_name' => 'vulmap', 'target_name' => 'target'])->field('data_id');
        })->limit(20)->select()->toArray();

        foreach ($targetArr as $k => $v) {
            //执行检测脚本
            execTool($v, $toolPath);

            //录入检测结果
            writeData($toolPath, $v);

            //更新最后扫描的ID
            updateScanLog('vulmap', 'target', $v['id'] ?? 0);
        }


        // 修改插件的执行状态
        Db::table('control')->where(['ability_name' => 'vulmap'])->update(['status' => 0,'end_time' => date('Y-m-d H:i:s')]);

        addlog("vulmap 执行完毕");
        sleep(20);
    }

}


function writeData($toolPath, $v)
{
    $filename = '/tmp/vulmap.json';
    if (!file_exists($filename)) {
        addlog(["vulmap扫描完成,没有发现漏洞，url:{$v['url']}"]);
        return false;
    }
    $arr = json_decode(file_get_contents($filename), true);
    if (!$arr) {
        addlog(["{$v['url']}文件内容不存在:{$filename}"]);
        return false;
    }
    foreach ($arr as $val) {
        $data = [
            'tid' => $v['id'],
            'user_id' => $v['user_id'],
            'author' => $val['detail']['author'],
            'description' => $val['detail']['description'],
            'host' => $val['detail']['host'],
            'port' => $val['detail']['port'],
            'param' => json_encode($val['detail']['param']),
            'request' => $val['detail']['request'],
            'payload' => $val['detail']['payload'],
            'response' => $val['detail']['response'],
            'url' => $val['detail']['url'],
            'plugin' => $val['plugin'],
            'target' => json_encode($val['target']),
            'vuln_class' => $val['vuln_class'],
            'create_time' => substr($val['create_time'], 0, 10),
        ];
        if (!Db::name('vulmap')->insert($data)) {
            addlog(["vulmap数据写入失败:" . json_encode($data)]);
        };

        Db::name('vulmap')->extra("IGNORE")->insert($data);

        //插入到漏洞表中
        $data['tool_name'] = 'vulmap';
        $data['vul_type'] = $val['plugin'];
        Db::table('bugs')->strict(false)->extra('IGNORE')->insert($data);
    }
    addlog(["vulmap扫描成功数据已写入：", $v['url']]);
}

function execTool($v, $toolPath)
{
    $filename = '/tmp/vulmap.json';
    @unlink($filename);
    $cmd = "cd $toolPath && python3 vulmap.py -u {$v['url']} --output-json {$filename}";
    systemLog($cmd);

    return true;
}
