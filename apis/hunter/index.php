<?php
$inputFile = "/data/share/input_" . getenv("xflow_node_id") . ".json";
$outputFile = "/data/share/output_" . getenv("xflow_node_id") . ".json";

//没有input,直接返回
if (!file_exists($inputFile)) {
    file_put_contents($inputFile, []);
}
//读取上游数据
$list = json_decode(file_get_contents($inputFile), true);
$data = [];
//处理数据
foreach ($list as $val) {
    $tempData = execTool($val);
    $data = array_merge($data, $tempData);
}
//将结果写入到指定位置,供蜻蜓平台导入数据
file_put_contents($outputFile, json_encode($data, JSON_UNESCAPED_UNICODE));


function execTool($keyword)
{
    $url = "https://hunter.qianxin.com/openApi/search?api-key=03a8bf7cd5eedcb53b0dbc93a10434a113f5f1dbf9f3d8ddad7604e74d91a545&search=aXA9IjEyMy4yNDkuNi4xMzki&page=1&page_size=20&is_web=3&port_filter=false&start_time=2022-02-20&end_time=2023-02-19";
    $headerArray = array("Content-type:application/json;", "Accept:application/json");
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
    $output = curl_exec($ch);
    curl_close($ch);
    $output = json_decode($output, true);

    $result = $output['data'];

    print_r(explode(",", array_key($result[0])));


}