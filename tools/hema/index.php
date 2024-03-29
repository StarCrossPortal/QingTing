<?php
//获取输入的参数
//$inputPath = "/data/share/input_" . getenv("xflow_node_id") . ".json";
//$outputPath = "/data/share/output_" . getenv("xflow_node_id") . ".json";
$inputPath = "1.json";
$outputPath = "2.json";
if (!file_exists($inputPath)) {
    print_r("未找到必要的参数文件:{$inputPath}");
    file_put_contents($inputPath, json_encode([]));
}
$list = json_decode(file_get_contents($inputPath), true);
//安装工具
$toolPath = "/data/share/hema/";
autoDownTool($toolPath);

//开始执行代码
$data = [];
foreach ($list as $key => $value) {
    $codePath = $value['code_path'];
    //执行检测脚本
    execTool($toolPath, $codePath);

    //录入检测结果
    $tempList = writeData($codePath, $toolPath);

    //开始执行
    $data = array_merge($data, $tempList);
}

//将结果输出到文件
file_put_contents($outputPath, json_encode($data, JSON_UNESCAPED_UNICODE));


function execTool(string $toolPath, string $codePath)
{

    $cmd = "cd {$toolPath} && ./hm scan {$codePath}";
    echo $cmd . PHP_EOL;
    exec($cmd);
    return true;

}

function writeData($codePath, string $toolPath)
{

    $outPath = "{$toolPath}/result.csv";
    if (!file_exists($outPath)) {
        print_r("没有找到结果文件:{$outPath}\n");
        return [];
    }
    $result = readCsv($codePath, $outPath);
    //去掉表头
    if (isset($result[0])) unset($result[0]);
    $data = [];
    foreach ($result as $val) {
        $oneData = [
            'type' => $val[1],
            'filename' => $val[2],
        ];
        $data[] = $oneData;
    }
    return $data;
}

/**
 * [ReadCsv 读取CSV为数组]
 * @param string $uploadfile [文件路径]
 */
function readCsv($codeBasePath, $uploadfile = '')
{
    $file = fopen($uploadfile, "r");
    while (!feof($file)) {
        $data[] = fgetcsv($file);
    }
    foreach ($data as $key => &$value) {
        if (!$value) {
            unset($data[$key]);
        }
//        $value[2] = str_replace("{$codeBasePath}/", "", $value[2]);
    }
    fclose($file);
    return $data;
}


function autoDownTool($toolPath)
{
    if (file_exists($toolPath)) {
        return true;
    }
    $dirName = dirname($toolPath);
    !file_exists($dirName) && mkdir($dirName, 0777, true);

    $cmd = "cd {$dirName} && git clone --depth=1 https://gitee.com/songboy/hema.git  && chmod -R 777 hema";
    echo "正在下载工具 $cmd " . PHP_EOL;
    exec($cmd);

    $cmd = "cd {$toolPath} && tar -zxvf hm-linux-amd64.tgz";
    exec($cmd);
}
