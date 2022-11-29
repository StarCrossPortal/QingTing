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
    array_merge($data, execTool($url));
}


//将结果写入到指定位置,供蜻蜓平台导入数据
file_put_contents($outputFile, json_encode(['code' => 0, 'msg' => '处理完成', 'data' => $data], JSON_UNESCAPED_UNICODE));


//将工具执行
function execTool($url)
{
    $path = "cd /data/tools/masscan/ && ";
    // 通过系统命令执行工具
    $cmd = "{$path} masscan --ports 1-10000 {$url}  --max-rate 50000 --wait  5 |grep Discovered  2>&1  > /tmp/error.log";
    exec($cmd, $result);
    return $result;

}


