<?php
//获取输入的参数
$inputFile = "/data/share/input_".getenv("xflow_node_id").".json";
$outputFile = "/data/share/output_".getenv("xflow_node_id").".json";

//没有input,直接返回
if (!file_exists($inputFile)) {
    file_put_contents($outputFile, json_encode([]));
    return 0;
}
//读取上游数据
$list = json_decode(file_get_contents($inputFile), true);
$data = [];
//处理数据
foreach ($list as $val) {

    $data = array_merge($data, execTool($val['ip'],$val['port']));
}
//将结果写入到指定位置,供蜻蜓平台导入数据
file_put_contents($outputFile, json_encode($data, JSON_UNESCAPED_UNICODE));


//将工具执行
function execTool($ip, $port)
{
    $result = [];
    $path = "cd /data/tools/nmap/ && ";

    // 通过系统命令执行工具
    $cmd = "{$path} nmap -p {$port} -sS -Pn -T4  $ip | grep open | grep -v Discovered |grep -v grep  2>&1";
    exec($cmd, $result);


    $result = implode("\n", $result);
    return [$result];

}




