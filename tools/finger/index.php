<?php
$xflowNodeId = getenv("xflow_node_id");
$path = empty($xflowNodeId) ? "./" : "/data/share/";
$inputFile = "{$path}input_{$xflowNodeId}.json";
$outputFile = "{$path}output_{$xflowNodeId}.json";

//没有input,直接返回
if (!file_exists($inputFile)) {
    file_put_contents($inputFile, []);
}
//读取上游数据
$list = json_decode(file_get_contents($inputFile), true);

$data = [];
$toolPath = "/data/share/tool/Finger";
autoInstall($toolPath);
//处理数据
foreach ($list as $val) {
    $tempData = execTool($val);
    $data = array_merge($data, $tempData);

}
//将结果写入到指定位置,供蜻蜓平台导入数据
file_put_contents($outputFile, json_encode($data, JSON_UNESCAPED_UNICODE));


function execTool($item)
{

    return [];
}

function autoInstall($toolPath)
{
    $dirName = dirname($toolPath);
    if (file_exists($toolPath)) return true;

    !file_exists(dirname($toolPath)) && mkdir(dirname($toolPath), 0777, true);

    $cmd = "cd {$dirName} && git clone --depth=1 https://gitee.com/songboy/Finger.git Finger";
    exec($cmd, $result);
    $cmd = "cd {$dirName} && pip3 install -r requirements.txt";
    exec($cmd, $result);
}