<?php

$inputFile = "/data/share/input_" . getenv("xflow_node_id") . ".json";
$outputFile = "/data/share/output_" . getenv("xflow_node_id") . ".json";


//没有input,直接返回
if (!file_exists($inputFile)) {
    file_put_contents($outputFile, json_encode(['code' => 0, 'msg' => "{$inputFile}文件不存在", 'data' => []], JSON_UNESCAPED_UNICODE));
}
//读取上游数据
$list = json_decode(file_get_contents($inputFile), true);

//$list = [['host' => "10.1.1.140"]];
//将工具执行
$data = [];
foreach ($list as $val) {
    $url = $val['ip'];
    $data = array_merge($data, execTool($url));
}

var_dump($data);
//将结果写入到指定位置,供蜻蜓平台导入数据
file_put_contents($outputFile, json_encode($data, JSON_UNESCAPED_UNICODE));


//将工具执行
function execTool($url)
{
    autoDownTool();
    $path = "cd ./ && ";
    // 通过系统命令执行工具
    $portStr = "21,22,23,25,53,80,81,110,111,123,135,137,139,161,389,443,445,465,500,515,520,523,548,623,636,873,902,1080,1099,1433,1521,1604,1645,1701,1883,1900,2049,2181,2375,2379,2425,3128,3306,3389,4730,5060,5222,5351,5353,5432,5555,5601,5672,5683,5900,5938,5984,6000,6379,7001,7077,8080,8081,8443,8545,8686,9000,9001,9042,9092,9100,9200,9418,9999,11211,27017,37777,50000,50070,61616";
    $cmd = "{$path} masscan --ports {$portStr} {$url}  --max-rate 5000 --wait  5 |grep Discovered  ";

    $data = [];
    exec($cmd, $result);
    foreach ($result as $val) {
        $valArr = explode(" ", $val);
        $valArr = array_values(array_filter(array_map('intval', $valArr)));
        $data[] = ['port'=>$valArr[0],'ip'=>$url];
    }
    var_dump($data);
    return $data;

}

function autoDownTool()
{
    $cmd = "which masscan";
    exec($cmd, $result);

    if (empty($result)) {
        $cmd = "apt install masscan -y";
        echo $cmd . PHP_EOL;
        exec($cmd);
    }
}
