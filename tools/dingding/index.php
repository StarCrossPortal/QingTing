<?php
//获取输入的参数
$inputPath = "/data/share/input_" . getenv("xflow_node_id") . ".json";
$outputPath = "/data/share/output_" . getenv("xflow_node_id") . ".json";
//$inputPath = "1.json";
//$outputPath = "2.json";
$ddToken = '<<dd_webhook>>';
if (!file_exists($inputPath)) {
    print_r("未找到必要的参数文件:{$inputPath}");
    file_put_contents($inputPath, json_encode([]));
}
$list = json_decode(file_get_contents($inputPath), true);
//开始执行代码
$data = [];
foreach ($list as $key => $value) {
    $ddToken = $value['dd_webhook'] ?? $ddToken;
    $tempList = sendDingDing($ddToken, $value);
    //开始执行
    $data = array_merge($data, $tempList);
}

//将结果输出到文件
file_put_contents($outputPath, json_encode($data, JSON_UNESCAPED_UNICODE));


function sendDingDing($dd_webhook, $rawData)
{
    $webhook = $dd_webhook;

    $message = json_encode($rawData, JSON_UNESCAPED_UNICODE);

    $message = substr($message, 0, 1980);
    $data = array('msgtype' => 'markdown', 'markdown' => [
        'title' => '蜻蜓安全工作台提醒',
        'text' => $message,
    ]);
    $data_string = json_encode($data);
    $result = request_by_curl($webhook, $data_string);
    if ($result['errcode'] > 0) {
        print_r(["给用户发送钉钉通知失败", $result, $data, $webhook]);
    }

    return [$result];
}


function request_by_curl($remote_server, $post_string)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $remote_server);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json;charset=utf-8'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // 线下环境不用开启curl证书验证, 未调通情况可尝试添加该代码
    // curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
    // curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $data = curl_exec($ch);
    curl_close($ch);
    return json_decode($data, true);
}