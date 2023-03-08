<?php

//获取输入的参数
$inputPath = "/data/share/input_" . getenv("xflow_node_id") . ".json";
$outputPath = "/data/share/output_" . getenv("xflow_node_id") . ".json";
//$inputPath = "1.json";
//$outputPath = "2.json";

if (!file_exists($inputPath)) {
    print_r("未找到必要的参数文件:{$inputPath}");
    file_put_contents($inputPath, json_encode([]));
}
$list = json_decode(file_get_contents($inputPath), true);

//开始执行代码
$data = [];
foreach ($list as $key => $value) {
    $ip = $value['ip'];
    //执行fscan
    $tempList = execTool($ip);

    //开始执行
    $data = array_merge($data, $tempList);
}

file_put_contents($outputPath, json_encode($data, JSON_UNESCAPED_UNICODE));


//将工具执行
function execTool($url)
{
    $result = [];
    $path = "/data/tools/fscan/";
    autoDownTool($path);
    $path = "cd /data/tools/fscan/ && ";

    // 通过系统命令执行工具
    $cmd = "{$path} ./fscan_amd64 -h $url/32 ";
    exec($cmd, $result);
    var_dump($result);

    $data = [];
    foreach ($result as $value) {
        $data[] = ['raw' => $value];
    }

    return $data;

}

function autoDownTool($toolPath)
{
    if (!file_exists($toolPath)) {
        $dirName = dirname($toolPath);
        !file_exists($dirName) && mkdir($dirName, 0777, true);

        $cmd = "cd {$dirName} && git clone --depth=1 https://gitee.com/songboy/fscan.git  && chmod -R 777 fscan";
        echo "正在下载工具 $cmd " . PHP_EOL;
        exec($cmd);
    }
}