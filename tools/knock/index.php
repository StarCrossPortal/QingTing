<?php

//$inputFile = "/data/share/input_" . getenv("xflow_node_id") . ".json";
//$outputFile = "/data/share/output_" . getenv("xflow_node_id") . ".json";
$inputFile = "1.json";
$outputFile = "2.json";


//没有input,直接返回
if (!file_exists($inputFile)) {
    print_r("未找到必要的参数文件:{$inputFile}");
    file_put_contents($inputFile, json_encode([]));
}
//读取上游数据
$list = json_decode(file_get_contents($inputFile), true);

//将工具执行
$data = [];
foreach ($list as $val) {
    $url = $val['domain'];
    $data = array_merge($data, execTool($url));
}


//将结果写入到指定位置,供蜻蜓平台导入数据
file_put_contents($outputFile, json_encode($data, JSON_UNESCAPED_UNICODE));


function execTool($url)
{
    $result = [];
    // 通过系统命令执行工具
    $cmd = "docker run -it --rm secsi/knockpy $url  2>&1";
    exec($cmd, $result);

    $data = [];
    foreach ($result as $item) {
        if (intval($item) == 0) continue;
        $data[] = ['raw' => $item];
    }
    return $data;
}