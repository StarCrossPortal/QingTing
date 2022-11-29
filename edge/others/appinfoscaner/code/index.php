<?php

$inputFile = "/data/share/input_".getenv("xflow_node_id").".json";
$outputFile = "/data/share/output_".getenv("xflow_node_id").".json";

//没有input,直接返回
if (!file_exists($inputFile)) {
    file_put_contents($outputFile, json_encode(['code' => 0, 'msg' => "{$inputFile}文件不存在", 'data' => []], JSON_UNESCAPED_UNICODE));
    return 0;
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
    $path = "cd /data/tools/AppInfoScanner/ && ";

    // 通过系统命令执行工具
    $cmd = "{$path} python3 app.py web -i  '$url'  2>&1";
    exec($cmd, $result);
    $result = implode("\n", $result);
    return ['result'=>$result];
}



