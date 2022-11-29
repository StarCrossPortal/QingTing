<?php
$inputFile = "/data/share/input_".getenv("xflow_node_id").".json";
$outputFile = "/data/share/output_".getenv("xflow_node_id").".json";
//从文件读取数据
$data = json_decode(file_get_contents($inputFile), true);
//自定义中间处理事件
$result = [];
foreach ($data as $item) {
    $result[]['url'] = $item['url'];
}

//将执行结果写入到文件中
file_put_contents($outputFile, json_encode($result, true));
