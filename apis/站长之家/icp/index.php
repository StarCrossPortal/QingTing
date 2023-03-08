<?php


$xflowNodeId = getenv("xflow_node_id");
$path = empty($xflowNodeId) ? "./" : "/data/share/";
$inputFile = "{$path}input_{$xflowNodeId}.json";
$outputFile = "{$path}output_{$xflowNodeId}.json";

//没有input,直接返回
if (!file_exists($inputFile)) {
    file_put_contents($inputFile, []);
}
//读取上游数据
$list = json_decode(file_get_contents($inputFile), true);
//$list = [['fofa_keyword' => 'domain="songboy.site"', 'fofa_email' => '78273343@qq.com', 'fofa_token' => 'f4e431fb3xxxx9f7df34184']];
$data = [];
//处理数据
foreach ($list as $val) {
    $tempData = execTool($val);
    $data = array_merge($data, $tempData);

}
//将结果写入到指定位置,供蜻蜓平台导入数据
file_put_contents($outputFile, json_encode($data, JSON_UNESCAPED_UNICODE));


function execTool($item)
{
    $domain = $item['domain'];
    $token = 'apiuser_quantity_1b3ce90389f3e35a9277a743bf7b56de_4ddf273ba4584562a787093e28283ecc';
    $url = "https://openapi.chinaz.net/v1/1001/icp?APIKey={$token}&ChinazVer=1.0&domain={$domain}";
    $responseJson = file_get_contents($url);

    return json_decode($responseJson, true);
}