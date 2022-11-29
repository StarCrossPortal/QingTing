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

$data = [];
//处理数据
foreach ($list as $val) {
    $codePath = $val['code_path'];
    $ret = execTool($codePath);

    if ($ret === false) {
        print_r("semgrep 扫描失败:{$codePath}");
        continue;
    }

    $data = array_merge($data, $ret);
}
//将结果写入到指定位置,供蜻蜓平台导入数据
file_put_contents($outputFile, json_encode($data, JSON_UNESCAPED_UNICODE));

function execTool(string $codePath)
{
    $hash = md5($codePath);
    $outFile = "/data/share/{$hash}.json";
    print_r("开始扫描|{$codePath}|{$outFile}");
    $cmd = "semgrep --config auto  {$codePath} --json  -o {$outFile}";
    exec($cmd,$result);
    print_r($result);


    if (!file_exists($outFile)) {
        print_r("没有找到扫描结果文件:{$outFile}");
    }
    $temp = json_decode(file_get_contents($outFile), true);
    $list = $temp['results'] ?? [];

    return $list;
}