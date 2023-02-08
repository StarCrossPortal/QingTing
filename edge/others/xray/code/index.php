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

//$list = [['url' =>"http://10.1.1.140:8989/home/index.php"]];
print_r($list);
//将工具执行
$data = [];
foreach ($list as $val) {
    $url = $val['url'];
    print_r("开始扫描URL:{$url}\n");
    $tempList = execTool($url);
    print_r("完成扫描URL:{$url}\n");
    $data = array_merge($data, $tempList);
}


//将结果写入到指定位置,供蜻蜓平台导入数据
file_put_contents($outputFile, json_encode($data, JSON_UNESCAPED_UNICODE));


//将工具执行
function execTool($url)
{

    $hash = md5($url.rand());
    $resultPath = "/tmp/{$hash}/tool.json";
    //清理之上一轮的结果
    if (file_exists($resultPath)) unlink($resultPath);
    //创建文件夹
    if (!file_exists(dirname($resultPath))) {
        mkdir(dirname($resultPath), 0777, true);
    }

    $result = [];

    $toolPath = "/data/share/tools/xray";
    autoDownTool($toolPath);
    if (!file_exists($toolPath)) die("xray 工具目录不存在:{$toolPath}");

    $path = "cd $toolPath && ";
    // 通过系统命令执行工具
    $cmd = "{$path} ./xray_linux_amd64 webscan --url \"{$url}\" --json-output {$resultPath}";
    echo $cmd;
    exec($cmd, $result);

    $toolResult = file_exists($resultPath) ? file_get_contents($resultPath) : '[]';
    $toolResult = json_decode($toolResult, true);
    print_r($toolResult);
    return $toolResult;
}

function autoDownTool($toolPath)
{
    if (file_exists($toolPath)) {
        return true;
    }
    $dirName = dirname($toolPath);
    !file_exists($dirName) && mkdir($dirName, 0777, true);

    $cmd = "cd {$dirName} && git clone --depth=1 https://gitee.com/songboy/xray.git  && chmod -R 777 xray";
    echo "正在下载工具 $cmd " . PHP_EOL;
    exec($cmd);

}