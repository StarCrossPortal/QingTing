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
foreach ($list as $key=>$val) {
    if($key > 10){
        break;
    }
    //定义配置信息
    $codeSavePath = "/data/code";
    $codeUrl = $val['url'];
    $prName = md5($codeUrl);

    $ret = execTool($codeUrl, $codeSavePath, $prName);
    if (!$ret) {
        print_r("代码下载失败:{$codeUrl}");
        continue;
    }

    $data[] = ['url' => $codeUrl, 'code_path' => "{$codeSavePath}/{$prName}"];
}
//将结果写入到指定位置,供蜻蜓平台导入数据
file_put_contents($outputFile, json_encode($data, JSON_UNESCAPED_UNICODE));


/**
 * @param string $codeSavePath
 * @param string $codeUrl
 * @return bool
 */
function execTool(string $codeUrl, string $codeSavePath, string $prName)
{
    $codePath = "{$codeSavePath}/{$prName}";
    if (file_exists($codePath)) {
        print_r("代码目录 {$codePath} 已存在,暂时跳过");
    }
    $cmd = "cd {$codeSavePath}/ && git clone --depth=1 {$codeUrl}  $prName";
    exec($cmd,$result);
    var_dump($result);
    $result = implode("\n", $result);
    if ($result && is_string($result) && !strstr('resolve', $result)) {
        print_r("拉取代码[{$codeUrl}] 失败,暂时跳过~");
        return false;
    }


    return true;
}