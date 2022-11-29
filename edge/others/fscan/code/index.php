<?php

//获取输入的参数
$inputPath = "/data/share/input_" . getenv("xflow_node_id") . ".json";
$outputPath = "/data/share/output_" . getenv("xflow_node_id") . ".json";if (!file_exists($inputPath)) {
    print_r("未找到必要的参数文件:{$inputPath}");
}
$list = json_decode(file_get_contents($inputPath), true);

//开始执行代码
$data = [];
foreach ($list as $key => $value) {
    $ip = $value['ip'];
    //执行fscan
    $tempList = execTool($ip);

    //开始执行
    array_merge($data, $tempList);
}

file_put_contents($outputPath, json_encode($data, JSON_UNESCAPED_UNICODE));


//将工具执行
function execTool($url)
{

    $outputFile = "/tmp/output.txt";
    $result = [];
    print_r(["fscan开始执行扫描任务", $url]);
    $path = "cd /data/tools/fscan/ && ";

    // 通过系统命令执行工具
    $cmd = "{$path} ./fscan_amd64 -h $url/32 -o {$outputFile} 2>&1  > /tmp/error.log";
    exec($cmd, $result);


    //读取命令写入文件的执行结果
    if (!file_exists($outputFile)) {
        print_r("fscan 扫描结果文件不存在:{$outputFile}");
    }
    //解析数据
    $cmdResult = file_exists($outputFile) ? file_get_contents($outputFile) : "";
    return [$cmdResult];

}