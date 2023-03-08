<?php
//获取输入的参数
$inputFile = "/data/share/input_" . getenv("xflow_node_id") . ".json";
$outputFile = "/data/share/output_" . getenv("xflow_node_id") . ".json";

//没有input,直接返回
if (!file_exists($inputFile)) {
    file_put_contents($inputFile, json_encode([]));
}
//读取上游数据
$list = json_decode(file_get_contents($inputFile), true);

//$list = [['ip' => "192.168.3.1", 'port' => 80]];
print_r($list);
//将工具执行
$data = [];
foreach ($list as $val) {

    print_r("开始扫描:{$val['ip']}\n");
    $tempList = execTool($val);
    print_r("完成扫描:{$val['ip']}\n");
    $data = array_merge($data, $tempList);
}


//将结果写入到指定位置,供蜻蜓平台导入数据
file_put_contents($outputFile, json_encode($data, JSON_UNESCAPED_UNICODE));


//将工具执行

function execTool($val)
{
    $ip = $val['ip'];
    $port = $val['port'];
    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_set_nonblock($sock);
    socket_connect($sock, $ip, $port);
    socket_set_block($sock);
    $r = array($sock);
    $w = array($sock);
    $f = array($sock);
    $return = @socket_select($r, $w, $f, 3);
    socket_close($sock);
    $data = [];
    switch ($return) {
        case 2:
            echo "$ip:$port 关闭\n";
            break;
        case 1:
            echo "$ip:$port 打开\n";
            $data[] = $val;
            break;
        case 0:
            echo "$ip:$port 超时\n";
            break;
    }

    return $data;
}