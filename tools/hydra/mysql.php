<?php
//获取输入的参数
$inputFile = "/data/share/input_" . getenv("xflow_node_id") . ".json";
$outputFile = "/data/share/output_" . getenv("xflow_node_id") . ".json";

//没有input,直接返回
if (!file_exists($inputFile)) {
    file_put_contents($inputFile, json_encode([]));
}
//读取上游数据
$list = json_decode(file_get_contents($inputFile), true);

$list = [['ip' => '192.168.3.21', 'port' => 22]];
$data = [];
//处理数据
foreach ($list as $val) {
    //执行检测脚本
    $tempList = execTool($val['ip'], $val['port']);

    $data = array_merge($data, $tempList);
}
//将结果写入到指定位置,供蜻蜓平台导入数据
file_put_contents($outputFile, json_encode($data, JSON_UNESCAPED_UNICODE));


//将工具执行
function execTool($ip, $port)
{
    $toolPath = "/data/tools/hydra/";
    autoDownTool($toolPath);

    $result = [];
    print_r("hydra开始执行扫描任务", $ip);
    $path = "cd $toolPath && ";

    // 通过系统命令执行工具
    $cmd = "{$path} hydra -L ./user.txt -P ./password.txt -t 2 -e n -f -v {$ip} -s {$port} mysql";
    exec($cmd, $result);


    return [['result' => implode("\n", array_filter($result))]];
}

function autoDownTool($toolPath)
{
    $cmd = "which hydra";
    exec($cmd, $result);

    if (empty($result)) {
        $cmd = "apt update -y && apt install hydra -y";
        echo $cmd . PHP_EOL;
        exec($cmd);

        mkdir($toolPath, true);
        $userDic = "root\nadmin\nzhangsan";
        $passwordDic = "root\n123\npassword";

        file_put_contents("{$toolPath}/user.txt", $userDic);
        file_put_contents("{$toolPath}/password.txt", $passwordDic);
    }
}