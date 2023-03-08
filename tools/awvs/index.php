<?php
//获取输入的参数
$inputPath = "/data/share/input_" . getenv("xflow_node_id") . ".json";
$outputPath = "/data/share/output_" . getenv("xflow_node_id") . ".json";
if (!file_exists($inputPath)) {
    print_r("未找到必要的参数文件:{$inputPath}");
    file_put_contents($inputPath, json_encode([]));
}

$list = json_decode(file_get_contents($inputPath), true);

$data = [];
foreach ($list as $key => $value) {

    $temp = execTool($value);
    //开始执行
    $data = array_merge($data, $temp);
}


//将结果输出到文件
file_put_contents($outputPath, json_encode($data, JSON_UNESCAPED_UNICODE));


function execTool($item)
{
    $url = $item['url'];
    $awvs_url = $item['awvs_address'];
    $awvs_token = $item['awvs_token'];

    $awvs_url = rtrim($awvs_url, '/');

    //1. 添加目标
    $appInfo = addTarget($url, $awvs_url, $awvs_token);

    $targetId = $appInfo['target_id'];

    $scanInfo = startScan($targetId, $awvs_url, $awvs_token);
    $scanId = $scanInfo['scan_id'];

    //2. 等待执行结果
    $scanStatus = false;

    while ($scanStatus === false) {
        $scanStatus = getScanStatus($targetId, $awvs_url, $awvs_token);
        sleep(3);
    }

    $scanSessionId = $scanStatus['last_scan_session_id'];
    //3. 返回结果
    $vulList = getVulnList($scanId, $scanSessionId, $awvs_url, $awvs_token);

    //获取漏洞详情
    $result = [];
    foreach ($vulList['vulnerabilities'] as $item) {
        $result[] = getDetail($scanId, $scanSessionId, $item['vuln_id'], $awvs_url, $awvs_token);
    }

    return $result;
}

function addlog($msg)
{

    print_r($msg);
    print_r(PHP_EOL);
}


function addTarget($url, $awvs_url, $awvs_token)
{
    addlog("开始生成新任务");
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $awvs_url . "/api/v1/targets");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "{\"address\": \"{$url}\",\"description\": \"xxxx\",\"criticality\": \"10\"}");
    $headers = array();
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'X-Auth: ' . $awvs_token;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
        return false;
    }
    curl_close($ch);
    $appInfo = json_decode($result, true);

    return $appInfo;
}


function getScanStatus($targetId, $awvs_url, $awvs_token)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $awvs_url . '/api/v1/targets/' . $targetId);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $headers = array();
    $headers[] = 'X-Auth: ' . $awvs_token;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        addlog(["获取AWVS扫描状态失败", curl_error($ch)]);
    }
    curl_close($ch);

    $retArr = json_decode($result, true);

    if (isset($retArr['code']) && $retArr['code'] == 404) {
        addlog(["未在AWVS中找到此目标ID", $targetId]);
        return false;
    }
    //API未授权
    if (isset($retArr['code']) && $retArr['code'] == 401) {
        $errMsg = ["AWVS未授权,请参照文档配置地址和token...", $awvs_url];
        addlog($errMsg);
        return false;
    }

    //判断目标扫描状态
    if (!isset($retArr['last_scan_session_status']) or !in_array($retArr['last_scan_session_status'], ['aborted', 'completed', 'failed'])) {
        addlog("目标[{$targetId}]还未扫描完成,请耐心等待...");
        return false;
    }

    return $retArr;
}


function getVulnList($scanId, $scanSessionId, $awvs_url, $awvs_token)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $awvs_url . "/api/v1/scans/{$scanId}/results/{$scanSessionId}/vulnerabilities?l=1000&s=severity:desc");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $headers = array();
    $headers[] = 'X-Auth: ' . $awvs_token;
    $headers[] = 'Content-Type: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }
    curl_close($ch);

    return json_decode($result, true);

}

function getDetail($scanId, $scanSessionId, $vulnId, $awvs_url, $awvs_token)
{
    $ch = curl_init();

    $url = $awvs_url . "/api/v1/scans/{$scanId}/results/{$scanSessionId}/vulnerabilities/{$vulnId}";

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $headers = array();
    $headers[] = 'X-Auth: ' . $awvs_token;
    $headers[] = 'Content-Type: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }
    curl_close($ch);

    return json_decode($result, true);
}

function startScan($targetId, $awvs_url, $awvs_token)
{
    $postData = "{\"profile_id\":\"11111111-1111-1111-1111-111111111119\",\"schedule\":{\"disable\":false,\"start_date\":null,\"time_sensitive\":false},\"target_id\":\"{$targetId}\"}";
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $awvs_url . '/api/v1/scans');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

    $headers = array();
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'X-Auth: ' . $awvs_token;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }
    curl_close($ch);


    return json_decode($result, true);
}
