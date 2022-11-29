<?php
//获取输入的参数
$inputPath = "/data/share/input_" . getenv("xflow_node_id") . ".json";
$outputPath = "/data/share/output_" . getenv("xflow_node_id") . ".json";
if (!file_exists($inputPath)) {
    print_r("未找到必要的参数文件:{$inputPath}");
}
$list = json_decode(file_get_contents($inputPath), true);

//开始执行代码
$data = [];
foreach ($list as $key => $value) {
    $url = $value['url'];
    $toolPath = "/data/tools/dirmap";
    //执行检测脚本
    execTool($url, $toolPath);

    //录入检测结果
    $tempList = writeData($toolPath, $url);
    //开始执行
    $data = array_merge($data, $tempList);
}

//将结果输出到文件
file_put_contents($outputPath, json_encode($data, JSON_UNESCAPED_UNICODE));


function writeData($file_path, $url)
{

    $host = parse_url($url)['host'];
    $port = parse_url($url)['port'] ?? null;
    $port = $port ? "_{$port}" : "";
    $filename = $file_path . '/' . "output/{$host}{$port}.txt";

    if (!file_exists($filename)) {
        return [];
    }
    //打开一个文件
    $file = fopen($filename, "r");
    //检测指正是否到达文件的末端
    $data = [];
    while (!feof($file)) {
        $result = fgets($file);
        if (empty($result)) {
            continue;
        }
        $arr = explode('http', $result);
        $regex = "/(?:\[)(.*?)(?:\])/i";
        preg_match_all($regex, trim($arr[0]), $acontent);
        $oneData = [
            'url' => trim('http' . $arr[1]),
            'code' => isset($acontent[1][0]) ? $acontent[1][0] : '',
            'type' => isset($acontent[1][1]) ? $acontent[1][1] : '',
            'size' => isset($acontent[1][2]) ? $acontent[1][2] : '',
        ];
        $data[] = $oneData;

    }
    //关闭被打开的文件
    fclose($file);

    @unlink($filename);

    return $data;
}

function execTool($url, $toolPath)
{
    $cmd = "cd $toolPath  && python3 ./dirmap.py -i {$url} -lcf";
    echo "开始执行命令: {$cmd}\n";
    exec($cmd);

    return true;
}
