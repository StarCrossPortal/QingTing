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
$data = [];
//处理数据
foreach ($list as $val) {
    $url = $val['url'];
    //执行检测脚本
    $tempList = execTool($url);

    $data = array_merge($data, $tempList);
}
//将结果写入到指定位置,供蜻蜓平台导入数据
file_put_contents($outputFile, json_encode($data, JSON_UNESCAPED_UNICODE));


//将工具执行
function execTool($url)
{
    $toolPath = "/data/tools/WebCrack";
    isDownloadTools($toolPath);

    $result = [];
    print_r("webcrack开始执行扫描任务", $url);
    $path = "cd $toolPath && ";

    // 通过系统命令执行工具
    $cmd = "{$path} python3 webcrack.py '$url'  2>&1";
    exec($cmd, $result);


    return array_filter($result);
}

function isDownloadTools($toolPath)
{
    if (file_exists($toolPath)) {
        return true;
    }
    $dirName = dirname($toolPath);
    !file_exists($dirName) && mkdir($dirName, 0777, true);

    $cmd = "cd {$dirName} && git clone --depth=1 https://gitee.com/songboy/WebCrack.git";
    echo "正在下载工具 WebCrack $cmd " . PHP_EOL;
    exec($cmd);

    $cmd = "cd {$toolPath} && pip install -r requirements.txt";
    echo $cmd . PHP_EOL;
    exec($cmd);

    $codeText = file_get_contents("{$toolPath}/webcrack.py");
    $codeText = str_replace("input('File or Url:\\n')", "sys.argv[1]", $codeText);
    $codeText = str_replace("import datetime", "import datetime\nimport sys", $codeText);
    file_put_contents("{$toolPath}/webcrack.py", $codeText);

}