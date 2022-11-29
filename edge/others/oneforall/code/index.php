<?php


$inputFile = "/data/share/input_".getenv("xflow_node_id").".json";
$outputFile = "/data/share/output_".getenv("xflow_node_id").".json";

//没有input,直接返回
if (!file_exists($inputFile)) {
    file_put_contents($outputFile, json_encode(['code' => 0, 'msg' => "{$inputFile}文件不存在", 'data' => []], JSON_UNESCAPED_UNICODE));
    return 0;
}
//读取上游数据
$list = json_decode(file_get_contents($inputFile), true);

//将工具执行
$data = [];
foreach ($list as $val) {
    $host = $val['host'];
    $data=array_merge($data, execTool($host));
}


//将结果写入到指定位置,供蜻蜓平台导入数据
file_put_contents($outputFile, json_encode(['code' => 0, 'msg' => '处理完成', 'data' => $data], JSON_UNESCAPED_UNICODE));



function execTool($host)
{

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        file_put_contents("/tmp/error.log", "此地址不是域名:{$host}\n");
        return false;
    }
    $host_arr = explode('.', $host);
    unset($host_arr[0]);

    $pwd = "/data/tools/OneForAll";
    $cmd = "cd {$pwd} && python3 ./oneforall.py --target {$host}  --fmt=json run  2>&1  > /tmp/error.log";

    exec($cmd,$result);
    var_dump($result);
    return $result;

}



