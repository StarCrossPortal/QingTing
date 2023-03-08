<?php
$inputFile = "/data/share/input_" . getenv("xflow_node_id") . ".json";
$outputFile = "/data/share/output_" . getenv("xflow_node_id") . ".json";


//从文件读取数据
$data = json_decode(file_get_contents($inputFile), true);
print_r(['得到参数', $data]);

//自定义代码区域
$result = [];
foreach ($data as $key=>$item) {
    if($key > 1){
        break;
    }

    $temp = check($item);
    $result = array_merge($result, $temp);
}
print_r(['输出结果', $result]);

//将执行结果写入到文件中
file_put_contents($outputFile, json_encode($result, true));


function check($item)
{
    $url = $item['url'];
    $url = str_replace("docker", 'http', $url);

    $htmlContent = file_get_contents($url);


    $data = [];
//    if (strpos($htmlContent, 'Version') !== false) {
        $item['raw'] = $htmlContent;
        $data[] = $item;
//        var_dump($item);
//    }

    return $data;

}