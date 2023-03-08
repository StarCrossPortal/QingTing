<?php


$inputFile = "/data/share/input_".getenv("xflow_node_id").".json";
$outputFile = "/data/share/output_".getenv("xflow_node_id").".json";

//没有input,直接返回
if (!file_exists($inputFile)) {
    print_r("未找到必要的参数文件:{$inputFile}");
    file_put_contents($inputFile, json_encode([]));
}
//读取上游数据
$list = json_decode(file_get_contents($inputFile), true);

//将工具执行
$data = [];
foreach ($list as $val) {
    $url = $val['url'];
    $data = array_merge($data,execTool($url));
}


//将结果写入到指定位置,供蜻蜓平台导入数据
file_put_contents($outputFile, json_encode($data, JSON_UNESCAPED_UNICODE));


//将工具执行
function execTool($url)
{
    $ouput_result_path='/tmp/nuclei.txt';
    $toolPath = "/data/tools/nuclei/";
    if (!file_exists($toolPath)) die("rad 工具目录不存在:{$toolPath}");
    $path = "cd $toolPath && ";
    // 通过系统命令执行工具
    $cmd = "{$path} ./nuclei -u $url -o {$ouput_result_path}";
    exec($cmd);
    $urlList = file_get_contents($ouput_result_path);
    $str_arr=explode("\n",$urlList);
    return $str_arr;
}
