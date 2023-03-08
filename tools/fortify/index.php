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
var_dump($list);
//开始执行代码
$data = [];
foreach ($list as $value) {

    if (!isset($value['code_path'])) {

        print_r("code_path 字段不存在\n");
        continue;
    }
    $codePath = $value['code_path'];

    //执行检测脚本
    $outpath = rtrim($codePath, '/') . "/" . md5($codePath);
    $result = execTool($codePath, $outpath);

    if ($result !== true) continue;
    //录入检测结果
    $tempList = writeData($outpath);
    $data = array_merge($data, $tempList);
}
if (isset($data[0])) print_r(implode(',', array_keys($data[0])));
//将结果输出到文件
file_put_contents($outputPath, json_encode($data, JSON_UNESCAPED_UNICODE));


//写入数据到数据库
function writeData($outpath)
{
    $xmlFile = "{$outpath}.xml";

    if (file_exists($xmlFile) === false) {
        print_r("fortify的XML文件不存在:{$xmlFile}");
        return [];
    }

    return getFortifData($xmlFile);

}


function execTool($codePath, $outPath)
{
    $buildId = md5($codePath);

    if (file_exists($outPath)) {
        chmod($outPath, 0777);
    }

    $fortifyPath = "/data/share/fortify";

    if (!file_exists($fortifyPath)) die("fortify 代码扫描器不存在:{$fortifyPath}");

    $base = "cd {$fortifyPath}/bin && ";
    if (file_exists("{$outPath}.fpr") == false) {
        $cmd = $base . "./sourceanalyzer -b {$buildId} -clean";
        system($cmd);
        $cmd = $base . "./sourceanalyzer -b {$buildId} -Xmx4096M -Xms2048M -Xss48M     -source 1.8 -machine-output   {$codePath}";
        system($cmd);
        $cmd = $base . "./sourceanalyzer -b {$buildId} -scan -format fpr    -f {$outPath}.fpr -machine-output ";
//        $cmd .= " -no-default-rules  -rules  {$fortifyPath}/Core/config/rules/core_php.bin";
        system($cmd);
    } else {
        print_r("fortify扫描文件 {$outPath}.fpr 已存在,不再重新扫描\n");
    }

    if (file_exists("{$outPath}.xml") == false) {
        $cmd = $base . "./ReportGenerator  -format xml -f {$outPath}.xml -source {$outPath}.fpr -template DeveloperWorkbook.xml";
        system($cmd);
    }
    return true;
}

function getFortifData($xmlPath)
{

    $str = file_get_contents($xmlPath);

    $obj = simplexml_load_string($str, "SimpleXMLElement", LIBXML_NOCDATA);
    $test = json_decode(json_encode($obj), true);

    if (!isset($test['ReportSection'][2])) {
        echo "{$xmlPath} 数据为空";
        return [];
    }

    $list = $test['ReportSection'][2]['SubSection']['IssueListing']['Chart']['GroupingSection'] ?? [];

    $list = isset($list['Issue']) ? [$list] : $list;

    $data = [];
    foreach ($list as &$value) {
        unset($value['MajorAttributeSummary']);
        $value = isset($value['Issue'][0]) ? $value['Issue'] : [$value['Issue']];
        foreach ($value as &$val) {
            unset($val['@attributes']);
            foreach ($val as &$v) {
                $v = is_string($v) ? $v : json_encode($v);
            }
            $data[] = $val;
        }
    }


    return $data;
}


