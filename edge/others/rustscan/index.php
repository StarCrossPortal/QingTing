<?php
$inputFile = "/data/share/input_".getenv("xflow_node_id").".json";
$outputFile = "/data/share/output_".getenv("xflow_node_id").".json";

$rustscan = "docker run -it --rm --name rustscan registry.cn-beijing.aliyuncs.com/daxia/qingting:rustscan_1.10";


//从文件读取数据
$data = json_decode(file_get_contents($inputFile), true);


//自定义中间处理事件
$result = [];
$cmd = "{$rustscan} {$data['host']} -t 500 -b 65535 -- -A";
exec($cmd, $result);

//过滤数据
$result = array_filter($result, function ($item) {
    return $item[0] == 'O';
}, ARRAY_FILTER_USE_BOTH);
$result = array_values($result);


//将执行结果写入到文件中
file_put_contents($outputFile, json_encode($result , true));
