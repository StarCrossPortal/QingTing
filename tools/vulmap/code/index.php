<?php
//获取输入的参数
$inputFile = "/data/share/input_".getenv("xflow_node_id").".json";
$outputFile = "/data/share/output_".getenv("xflow_node_id").".json";

//没有input,直接返回
if (!file_exists($inputFile)) {
    file_put_contents($outputFile, json_encode([]));
    return 0;
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


function execTool($url)
{

    $toolPath = "/data/tools/vulmap";
    if (!file_exists($toolPath)) die("vulmap 工具目录不存在:{$toolPath}");


    $filename = '/tmp/vulmap.json';
    @unlink($filename);
    $cmd = "cd $toolPath && python3 vulmap.py -u '{$url}' --output-json {$filename}";
//    echo $cmd . PHP_EOL;
    exec($cmd);


    if (!file_exists($filename)) {
        print_r("vulmap扫描完成,没有发现漏洞，url:{$url}");
        return ['result'=>"vulmap扫描完成,没有发现漏洞，url:{$url}"];
    }
    $arr = json_decode(file_get_contents($filename), true);
    if (!$arr) {
        print_r("{$url}文件内容不存在:{$filename}");
        return [];
    }
    $data = [];
    foreach ($arr as $val) {
        $oneData = [
            'author' => $val['detail']['author'],
            'description' => $val['detail']['description'],
            'host' => $val['detail']['host'],
            'port' => $val['detail']['port'],
            'param' => json_encode($val['detail']['param']),
            'request' => $val['detail']['request'],
            'payload' => $val['detail']['payload'],
            'response' => $val['detail']['response'],
            'url' => $val['detail']['url'],
            'plugin' => $val['plugin'],
            'target' => json_encode($val['target']),
            'vuln_class' => $val['vuln_class'],
        ];
        $data[] = $oneData;
    }

    return $data;
}