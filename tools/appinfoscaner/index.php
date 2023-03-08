<?php

//$inputFile = "/data/share/input_" . getenv("xflow_node_id") . ".json";
//$outputFile = "/data/share/output_" . getenv("xflow_node_id") . ".json";
$inputFile = "1.json";
$outputFile = "2.json";

//没有input,直接返回
if (!file_exists($inputFile)) {
    file_put_contents($inputFile, json_encode([]));
}
//读取上游数据
$list = json_decode(file_get_contents($inputFile), true);

//将工具执行
$data = [];
foreach ($list as $val) {
    $url = $val['url'];
    $tempList = execTool($url);
    $data = array_merge($data, $tempList);
}

file_put_contents($outputFile, json_encode($data, JSON_UNESCAPED_UNICODE));

//将工具执行
function execTool($url)
{
    $result = [];
    $path = "/data/tools/AppInfoScanner/";
    autoInstall($path);
    $path = "cd {$path} && ";

    // 通过系统命令执行工具
    $cmd = "{$path} python3 app.py web -i  '$url'  2>&1";
    exec($cmd, $result);
    $result = implode("\n", $result);
    print_r($result);
    return ['result' => $result];
}


function autoInstall($path)
{
    if (file_exists($path)) return true;


    $baseDir = dirname($path);
    if (!file_exists($baseDir)) mkdir($baseDir, 0777, true);
    $cmd = "cd {$baseDir} && git clone https://gitee.com/kelvin_ben/AppInfoScanner.git AppInfoScanner";
    exec($cmd, $result);
    print_r(implode("\n", $result));

    $cmd = "cd {$path} && python3 -m pip install -r requirements.txt";
    exec($cmd, $result);
    print_r(implode("\n", $result));
}
