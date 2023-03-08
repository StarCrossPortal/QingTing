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
    $token = "<<quanke_token>>";

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://quake.360.cn/api/v3/search/quake_service",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => "{\"query\": \"domain: {$domain}\", \"start\": 0, \"size\": 10}",
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "X-QuakeToken: {$token}",
            "content-type: application/json"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        echo $response;
    }

    return json_decode($response, true);
}