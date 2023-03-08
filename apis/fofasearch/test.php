<?php
$xflowNodeId = getenv("xflow_node_id");
$path = empty($xflowNodeId) ? "./" : "/data/share/";
$inputFile = "{$path}input_$xflowNodeId.json";
$outputFile = "{$path}output_$xflowNodeId.json";
$email = "<<fofa_email>>";
$key = "<<fofa_token>>";
$email = "78778443@qq.com";
$key = "f4e431fb3438583a2995869f7df34184";

//没有input,直接返回
if (!file_exists($inputFile)) {
    file_put_contents($inputFile, json_encode([]));
}
//读取上游数据
$list = json_decode(file_get_contents($inputFile), true);
$data = [];
//处理数据
foreach ($list as $val) {
    if (!filter_var($val['host'], FILTER_VALIDATE_IP)) {
        $data[] = $val['host'];
        continue;
    }
    $ip = $val['host'];
    $searchKey = "ip=\"{$ip}\"";

    print_r("正在处理目标:{$searchKey}");
    $str = urlencode(base64_encode($searchKey));
    $htmlContent = file_get_contents("https://fofa.info/api/v1/search/all?email={$email}&key={$key}&qbase64={$str}&fields=protocol,ip,port,domain");
    $result = json_decode($htmlContent, true);

    $tempData = [];
    foreach ($result['results'] as $value) {
        if (empty($value[3])) continue;
        $data[] = $value[3];
    }

}
$data = array_values(array_unique($data));

foreach ($data as $key => $value) {
    $value = getTopHost($value);
}

//将结果写入到指定位置,供蜻蜓平台导入数据
file_put_contents($outputFile, json_encode($data, JSON_UNESCAPED_UNICODE));
function getTopHost($host)
{
    //查看是几级域名
    $data = explode('.', $host);
    $n = count($data);
    //判断是否是双后缀
    $preg = '/[\w].+\.(com|net|org|gov|edu)\.cn$/';
    if (($n > 2) && preg_match($preg, $host)) {
        //双后缀取后3位
        $host = $data[$n - 3] . '.' . $data[$n - 2] . '.' . $data[$n - 1];
    } else {
        //非双后缀取后两位
        $host = $data[$n - 2] . '.' . $data[$n - 1];
    }
    return $host;
}