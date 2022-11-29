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
print_r($inputFile);
print_r($list);
$data = [];
//处理数据
foreach ($list as $val) {
    $url = $val['url'];
    $toolPath = "/data/tools/sqlmap/";

    print_r("开始扫描URL:{$url}".PHP_EOL);
    execTool($url, $toolPath);

    //录入检测结果
    $tempList = writeData($toolPath, $url);
    print_r("扫描URL:{$url}完成".PHP_EOL);
    print_r($tempList);
    $data = array_merge($data, $tempList);
}

print_r($data);
//将结果写入到指定位置,供蜻蜓平台导入数据
file_put_contents($outputFile, json_encode($data, JSON_UNESCAPED_UNICODE));


function writeData($toolPath, $url)
{

    $arr = parse_url($url);
    $file_path = $toolPath . 'result/';
    $host = $arr['host'];
    $outdir = $file_path . "{$host}/";
    $outfilename = "{$outdir}/log";

    //sqlmap输出异常
    if (!is_dir($outdir) or !file_exists($outfilename) or !filesize($outfilename)) {
        print_r("sqlmap没有找到注入点: $url");
        return [];
    }
    $ddd = file_get_contents($outfilename);
    print_r($ddd);

    exec("rm -rf $outdir");

    return [["raw" => $ddd]];
}

function execTool($v, $toolPath)
{

    $arr = parse_url($v);
    $blackExt = ['.js', '.css', '.json', '.png', '.jpg', '.jpeg', '.gif', '.mp3', '.mp4'];
    //没有可以注入的参数
    if (!isset($arr['query']) or in_array_strpos($arr['path'], $blackExt) or (strpos($arr['query'], '=') === false)) {
        print_r(["URL地址不存在可以注入的参数".PHP_EOL, $v]);
        return false;
    }
    $file_path = $toolPath . 'result/';
    $cmd = "cd {$toolPath}  && python3 ./sqlmap.py -u '{$v}' --batch  --random-agent --output-dir={$file_path}";
    exec($cmd);
    return true;
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