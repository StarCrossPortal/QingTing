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

$list = [['url' => "http://123.58.224.8:48911/"]];
print_r($list);
//将工具执行
$data = [];
foreach ($list as $val) {
    $url = $val['url'];
    print_r("开始扫描URL:{$url}\n");
    $tempList = execTool($url);
    print_r("完成扫描URL:{$url}\n");
    $data = array_merge($data, $tempList);
}

//将结果写入到指定位置,供蜻蜓平台导入数据
print_r($data);
file_put_contents($outputFile, json_encode($data, JSON_UNESCAPED_UNICODE));


//将工具执行
function execTool($url)
{
    $result = [];

    $toolPath = "/data/share/tools/TideFinger";
    autoDownTool($toolPath);
    if (!file_exists($toolPath)) die("TideFinger 工具目录不存在:{$toolPath}");

    $path = "cd $toolPath && ";
    // 通过系统命令执行工具
    $cmd = "{$path} python3 ./python3/TideFinger.py -u \"{$url}\" ";
    echo $cmd;
    exec($cmd, $result);

    $data = [];
    foreach ($result as $val) {
        $valArr = explode(': ', $val);
        $valArr = array_map('trim', array_filter($valArr));

        if (count($valArr) < 2) continue;
//        if (!in_array(match_chinese($valArr[0]), ['Banner', 'CMS_finger'])) continue;
        $data[match_chinese($valArr[0])] = match_chinese($valArr[1]);
    }
    $data['url'] = $url;
    return [$data];
}

function autoDownTool($toolPath)
{
    if (!file_exists($toolPath)) {
        $dirName = dirname($toolPath);
        !file_exists($dirName) && mkdir($dirName, 0777, true);

        $cmd = "cd {$dirName} && git clone --depth=1 https://gitee.com/songboy/TideFinger.git  && chmod -R 777 TideFinger";
        echo "正在下载工具 $cmd " . PHP_EOL;
        exec($cmd);
    }
    $cmd = "cd {$toolPath}/python3 && pip install -r requirements.txt -i https://mirror.baidu.com/pypi/simple";
    echo $cmd . PHP_EOL;
    exec($cmd);
}

function match_chinese($chars, $encoding = 'utf8')
{
    $pattern = ($encoding == 'utf8') ? '/[a-zA-Z0-9_|]/u' : '/[\x80-\xFF]/';
    preg_match_all($pattern, $chars, $result);
    $temp = join('', $result[0]);
    $temp = str_replace('0m132m', '', $temp);
    $temp = str_replace('131m', '', $temp);
    $temp = rtrim($temp, '0m');

    return $temp;
}