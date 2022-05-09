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
    $toolPath = '/data/tools/sqlmap/';
// 循环读取状态值,直到执行完成
    while (true) {
        $result = Db::table('control')->where(['ability_name' => 'sqlmap', 'status' => 1])->find();
        if (empty($result)) {
            sleep(15);
            continue;
        }
        addlog("sqlmap 开始工作");

        //读取目标数据
        $targetArr = Db::table('urls')->where('id', 'NOT IN', function ($query) {
            $query->table('scan_log')->where(['tool_name' => 'sqlmap', 'target_name' => 'urls'])->field('data_id');
        })->limit(20)->select()->toArray();

        foreach ($targetArr as $k => $v) {
            //执行检测脚本
            execTool($v, $toolPath);

            //录入检测结果
            writeData($toolPath, $v);

            //更新最后扫描的ID
            updateScanLog('sqlmap', 'urls', $v['id'] ?? 0, $v['tid'] ?? 0);
        }

        // 修改插件的执行状态
        Db::table('control')->where(['ability_name' => 'sqlmap'])->update(['status' => 0, 'end_time' => date('Y-m-d H:i:s')]);

        addlog("sqlmap 执行完毕");
        sleep(20);
    }

}


function writeData($toolPath, $v)
{

    $arr = parse_url($v['url']);
    $file_path = $toolPath . 'result/';
    $host = $arr['host'];
    $outdir = $file_path . "{$host}/";
    $outfilename = "{$outdir}/log";

    //sqlmap输出异常
    if (!is_dir($outdir) or !file_exists($outfilename) or !filesize($outfilename)) {
        addlog(["sqlmap没有找到注入点", $v['url']]);
        return false;
    }
    $ddd = file_get_contents($outfilename);
    $arr = explode("\n", $ddd);

    $data = [];
    foreach ($arr as $tmp) {
        $tempv2 = explode(":", $tmp);
        if (count($tempv2) == 2) {
            $data[trim($tempv2[0])][] = trim($tempv2[1]);
        }
    }

    $bbb = [
        'system' => isset($data['web server operating system']) ? $data['web server operating system'][0] : '',
        'application' => isset($data['web application technology']) ? $data['web application technology'][0] : '',
        'dbms' => isset($data['back-end DBMS']) ? $data['back-end DBMS'][0] : '',
        'urls_id' => $v['id'],
        'tid' => $v['tid'],
        'user_id' => $v['user_id'],
    ];
    foreach ($data['Payload'] as $key => $value) {
        $bbb['tid'] = $v['tid'];
        $bbb['payload'] = $value;
        $bbb['title'] = $data['Title'][$key];
        $bbb['type'] = $data['Type'][$key];
        Db::name('sqlmap')->insert($bbb);

        //插入到漏洞表中
        $bbb['tool_name'] = 'sqlmap';
        $bbb['vul_type'] = "SQL注入";
        $bbb['detail'] = $bbb['payload'];

        Db::table('bugs')->strict(false)->extra('IGNORE')->insert($bbb);
    }
    addlog(["sqlmap扫描成功数据已写入：", $v['url']]);
    systemLog("rm -rf $outdir");
}

function execTool($v, $toolPath)
{

    $arr = parse_url($v['url']);
    $blackExt = ['.js', '.css', '.json', '.png', '.jpg', '.jpeg', '.gif', '.mp3', '.mp4'];
    //没有可以注入的参数
    if (!isset($arr['query']) or in_array_strpos($arr['path'], $blackExt) or (strpos($arr['query'], '=') === false)) {
        addlog(["URL地址不存在可以注入的参数", $v['url']]);
        return false;
    }
    $file_path = $toolPath . 'result/';
    $cmd = "cd {$toolPath}  && python3 ./sqlmap.py -u '{$v['url']}' --batch  --random-agent --output-dir={$file_path}";
    systemLog($cmd);

    return true;
}
