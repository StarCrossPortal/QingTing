<?php

$inputFile = "/data/share/input_" . getenv("xflow_node_id") . ".json";
$outputFile = "/data/share/output_" . getenv("xflow_node_id") . ".json";


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
    $searchKey = $val['fofa_keyword'];
    $email = $val['fofa_email'];
    $key = $val['fofa_token'];
    print_r("正在处理目标:{$searchKey}");
    $str = urlencode(base64_encode($searchKey));
    $htmlContent = file_get_contents("https://fofa.info/api/v1/search/all?email={$email}&key={$key}&qbase64={$str}&fields=protocol,ip,port,domain");
    $result = json_decode($htmlContent, true);

    $tempData = [];
    foreach ($result['results'] as $value) {
        $host = empty($value[3]) ? $value[1] : $value[3];
        $tempData[] = [
            'protocol' => $value[0],
            'ip' => $value[1],
            'port' => $value[2],
            'domain' => $value[3],
            "url" => "{$value[0]}://{$host}:{$value[2]}/"
        ];
    }
    print_r("搜索结果:" . json_encode($tempData));
    $data = array_merge($data, $tempData);

}
//将结果写入到指定位置,供蜻蜓平台导入数据
file_put_contents($outputFile, json_encode($data, JSON_UNESCAPED_UNICODE));
return 0;
