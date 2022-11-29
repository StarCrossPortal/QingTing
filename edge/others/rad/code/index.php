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
print_r(["RAD上游信息:", $list]);
$data = [];
//处理数据
foreach ($list as $val) {
    $url = $val['url'];
    $toolResult = "/tmp/rad_output.json";
    //执行结果
    print_r("开始扫描URL:{$url}");
    $tempList = execRad($toolResult, $url);
    print_r("完成扫描URL:{$url}");
    $data = array_merge($data, $tempList);
}
//将结果写入到指定位置,供蜻蜓平台导入数据
file_put_contents($outputFile, json_encode($data, JSON_UNESCAPED_UNICODE));


function filterUrl(array $urlList)
{

    foreach ($urlList as $key => $val) {
        $arr = parse_url($val['url']);
        $blackExt = ['.js', '.css', '.png', '.jpg', '.jpeg', '.gif', '.mp3', '.mp4'];
        if (!isset($arr['query']) or in_array_strpos($arr['path'] ?? '', $blackExt)) {
            print_r("rad扫描跳过无意义URL:{$val['url']}" . PHP_EOL);
            unset($urlList[$key]);
        }
    }

    return array_values($urlList);
}

function execRad($toolResult, $url)
{
    if (file_exists($toolResult)) unlink($toolResult);

    $toolPath = "/data/tools/rad/";
    if (!file_exists($toolPath)) die("rad 工具目录不存在:{$toolPath}");

    $path = "cd $toolPath && ";

    $cmd = "$path ./rad_linux_amd64 -t  \"{$url}\" -json {$toolResult}";
    print_r("开始执行抓取URL地址命令", $cmd);

    $result = [];
    exec($cmd, $result);

    if (!file_exists($toolResult)) {
        print_r("rad扫描失败,结果文件不存在", $toolResult);
        return [];
    }
    $urlList = json_decode(file_get_contents($toolResult), true);


    if (empty($urlList)) {
        print_r($urlList);
        die;
    }

    foreach ($urlList as &$value) {
        $value['url'] = $value['URL'];
        unset($value['URL']);
    }

    return filterUrl($urlList);
}

function in_array_strpos($word, $array)
{
    foreach ($array as $v) {
        if (strpos($word, $v) !== false) {
            return true;
        }
    }
    return false;
}