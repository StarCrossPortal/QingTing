<?php
$inputPath = "/data/share/input_" . getenv("xflow_node_id") . ".json";
$outputPath = "/data/share/output_" . getenv("xflow_node_id") . ".json";if (!file_exists($inputPath)) {
    print_r("未找到必要的参数文件:{$inputPath}");
}
$list = json_decode(file_get_contents($inputPath), true);


//开始执行代码
$data = [];
foreach ($list as $key => $value) {
    $codePath = $value['codePath'];

    $result = execTool($codePath);

    //开始执行
    $data = array_merge($data, $result);
}

//将结果输出到文件
file_put_contents($outputPath, json_encode($data, JSON_UNESCAPED_UNICODE));

//将工具执行
function execTool($filePath)
{
    $hash = md5($filePath);
    $resultPath = "/tmp/{$hash}/tool.json";
    !file_exists(dirname($resultPath)) && mkdir(dirname($resultPath), 0777, true);

    $result = [];
    $path = "cd /data/tools/apktool/ && ";

    // 通过系统命令执行工具
    $cmd = "{$path} ./apktool d {$filePath} -o output_file";
    exec($cmd, $result);


    $result = implode("\n", $result);
    $toolResult = file_exists($resultPath) ? file_get_contents($resultPath) : '';
    return ['cmd_result' => $result, 'raw_data' => $toolResult];
}
