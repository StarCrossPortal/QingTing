<?php


$inputFile = "/data/share/input_" . getenv("xflow_node_id") . ".json";
$outputFile = "/data/share/output_" . getenv("xflow_node_id") . ".json";
$mf_token = getenv('mf_token');

installTool();

//没有input,直接返回
if (!file_exists($inputFile)) {
    file_put_contents($outputFile, json_encode(['code' => 0, 'msg' => "{$inputFile}文件不存在", 'data' => []], JSON_UNESCAPED_UNICODE));
    return 0;
}
//读取上游数据
$list = json_decode(file_get_contents($inputFile), true);
//将工具执行
$data = [];
foreach ($list as $val) {
    $codepath = $val['code_path'];
    $data = array_merge($data, execTool($codepath, $mf_token));
}


//将结果写入到指定位置,供蜻蜓平台导入数据
file_put_contents($outputFile, json_encode($data, JSON_UNESCAPED_UNICODE));


//将工具执行
function execTool(string $codepath, string $mf_token)
{
    if (!file_exists($codepath)) {
        print_r("要扫码的目录不存在:$codepath");
        return [];
    }

    $result = [];
    // 通过系统命令执行工具
    $cmd = "murphysec scan $codepath --token {$mf_token}  --json > /tmp/aa.json";
    print_r($cmd);
    exec($cmd, $result);
    $result = file_get_contents("/tmp/aa.json");
    $result = json_decode($result, true);
    print_r($result);
    $result = $result['comps'] ?? [];
    return $result;
}

function installTool()
{

    $cmd = "which murphysec";
    exec($cmd, $result);

    if (empty($result)) {
        $cmd = "wget -q https://s.murphysec.com/install.sh -O - | /bin/bash";
        system($cmd);
        print_r("安装murphysec完成");
    }
}