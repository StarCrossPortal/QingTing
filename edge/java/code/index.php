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
    $codePath = "";
    $toolPath = '/data/tools/semgrep/';
    $filename = '/tmp/semgrep.json';
// 循环读取状态值,直到执行完成
    while (true) {
        $result = Db::table('control')->where(['ability_name' => 'semgrep', 'status' => 1])->find();
        if (empty($result)) {
            sleep(15);
            continue;
        }
        addlog("semgrep 开始工作");

        //读取目标数据
        $targetArr = Db::table('target')->where('url', 'not null')->where('id', 'NOT IN', function ($query) {
            $query->table('scan_log')->where(['tool_name' => 'java', 'target_name' => 'target'])->field('data_id');
        })->limit(20)->select()->toArray();
        foreach ($targetArr as $k => $v) {

            //执行下载代码
            downCode($codePath, md5($v['name']), $v['url'], $authInfo);

            //执行检测脚本
            execTool($toolPath, $v);

            //录入检测结果
            writeData($toolPath, $v);

            //更新最后扫描的ID
            updateScanLog('semgrep', 'target', $v['id'] ?? 0);
        }


        // 修改插件的执行状态
        Db::table('control')->where(['ability_name' => 'semgrep'])->update(['status' => 0, 'end_time' => date('Y-m-d H:i:s')]);

        addlog("semgrep 执行完毕");
        sleep(20);
    }

}

function execTool(string $codePath, string $outPath)
{
    $cmd = "semgrep -f /data/tools/semgrep/rules.yaml {$codePath} --json  -o {$outPath}";

    $result = systemLog($cmd);

    return true;

}

function writeData(int $codeId, string $jsonPath, $user_id = 0)
{
    $data = json_decode(file_get_contents($jsonPath), true);

    foreach ($data['results'] as $v1) {
        $data = [];
        foreach ($v1 as $k2 => $v2) {
            if (is_array($v2)) {
                foreach ($v2 as $k3 => $v3) {
                    $data["{$k2}_{$k3}"] = is_string($v3) ? $v3 : json_encode($v3, JSON_UNESCAPED_UNICODE);
                }
            } else {
                $data[$k2] = $v2;
            }
        }
        $data['code_id'] = $codeId;
        $data['user_id'] = $user_id;
        Db::table('semgrep')->insert($data);
    }

    return true;
}


