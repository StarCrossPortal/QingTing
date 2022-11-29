<?php

$inputFile = "/data/share/input_".getenv("xflow_node_id").".json";
$outputFile = "/data/share/output_".getenv("xflow_node_id").".json";
//自定义环境变量
$email = getenv('email');
$key = getenv('key');


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
    $searchKey = $val['raw'];
    print_r("正在处理目标:{$searchKey}");
    $str = urlencode(base64_encode($searchKey));
    $htmlContent = file_get_contents("https://fofa.info/api/v1/search/all?email={$email}&key={$key}&qbase64={$str}");
    $result = json_decode($htmlContent, true);
    print_r("搜索结果:" . json_encode($result));
    $data = array_merge($data, $result['results'] ?? []);

}
//将结果写入到指定位置,供蜻蜓平台导入数据
file_put_contents($outputFile, json_encode(['code' => 0, 'msg' => '处理完成', 'data' => $data], JSON_UNESCAPED_UNICODE));
return 0;
