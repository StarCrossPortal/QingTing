<?php
//获取输入的参数
//$inputPath = "/data/share/input_" . getenv("xflow_node_id") . ".json";
//$outputPath = "/data/share/output_" . getenv("xflow_node_id") . ".json";
$inputPath = "1.json";
$outputPath = "2.json";
if (!file_exists($inputPath)) {
    print_r("未找到必要的参数文件:{$inputPath}");
    file_put_contents($inputPath, json_encode([]));
}
$list = json_decode(file_get_contents($inputPath), true);

//开始执行代码
$data = [];
$toolPath = "/data/tools/dismap/";
isDownloadTools($toolPath);
foreach ($list as $key => $value) {
    $url = $value['url'];

    //执行检测脚本
    execTool($url, $toolPath);

    //录入检测结果
    $tempList = writeData($toolPath, $url);
    //开始执行
    $data = array_merge($data, $tempList);
}

//将结果输出到文件
file_put_contents($outputPath, json_encode($data, JSON_UNESCAPED_UNICODE));

//-------------------------------------------------------

function writeData($toolPath, $url)
{
    $filename = $toolPath . 'result.json';
    if (!file_exists($filename)) {
        print_r(["dismap扫描失败，url:{$url}"]);
        return false;
    }
    //打开一个文件
    $result = file_get_contents($filename);
    if (empty($result)) {
        print_r(["dismap 扫描目标结果为空", $url]);
        return false;
    }
    $result = json_decode($result, true);

    return [$result];


}

function execTool($url, $toolPath)
{


    $filename = $toolPath . 'dismap.txt';
    $filenameJson = $toolPath . 'result.json';
    @unlink($filename);
    @unlink($filenameJson);
    $cmd = "cd $toolPath && ./dismap-0.4-linux-amd64 -u {$url} -j {$filenameJson}";
    exec($cmd);

    return true;
}

function isDownloadTools($toolPath)
{
    $dirName = dirname($toolPath);
    !file_exists($dirName) && mkdir($dirName, 0777, true);

    if (!file_exists($toolPath)) {
        $cmd = "cd {$dirName} && git clone --depth=1 https://gitee.com/songboy/dismap.git dismap && chmod 777 -R *";
        echo $cmd . PHP_EOL;
        exec($cmd, $ret);
        print_r(implode("\n", $ret));
    }
}