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
$list = [['url' => 'http://www.songboy.site']];
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
//    $text = file_get_contents($url);
    $text = "sdfsdf  1231231@qq.com 13267424581 sdfsd";
    $result = matchEmail($text);


    return array_filter($result);
}


function matchEmail($text)
{
    $pattern = "/[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})/";
    preg_match($pattern, $text, $matches);
    var_dump($matches);  //输出匹配结果

    $pattern = "/1[34578]\d{9}/";
    preg_match($pattern, $text, $matches);
    var_dump($matches);  //输出匹配结果

    return $matches;
}
