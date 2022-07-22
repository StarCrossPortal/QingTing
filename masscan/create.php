<?php

//接收参数
$path = trim(`pwd`);
$action = $argv[1];
$config = json_decode(file_get_contents("{$path}/config.json"), true);
if (empty($config)) {
    die('配置文件无法解析');
}

//初始化参数
$name = $config['name'];
$token = $config['token'];
$path = dirname(trim(`pwd`));
$toolPath = "{$path}/{$name}";
$toolCodePath = "{$path}/{$name}/code";
$toolFilePath = "{$path}/{$name}/tools";
$serverAddr = "http://txy8g.songboy.site:10000/";

//根据行为调用不同命令
if ($action == 'copy') {
    exec("rm -rf $toolPath");
    copyCode($config, $toolPath, $path, $toolCodePath);
    build($config['docker_image'], $toolPath);
    addData($serverAddr, $token, $name);
} else if ($action == 'addData') {
    addData($serverAddr, $token, $name);
} else if ($action == 'build') {
    build($config['docker_image'], $toolPath);
}


function copyCode($config, $toolPath, $path, $toolCodePath)
{
    $name = $config['name'];
    if (file_exists($toolCodePath)) {
        die("你输入的项目已经存在");
    }

    //复制文件
    !file_exists($toolPath) && mkdir($toolPath, 0777, true);
    $cmd = "cp -r {$path}/developer/*  {$toolPath}";
    exec($cmd);

    $pwd = trim(`pwd`);
    $cmd = "cp -r {$pwd}/tools   {$toolPath}";
    exec($cmd);

    //替换内容
    $indexFile = "$toolCodePath/index.php";
    $codeStr = file_get_contents($indexFile);
    //替换关键词
    $codeStr = str_replace("webcrack", $name, $codeStr);
    //替换参数方式
    $codeStr = str_replace("##INPUT_TYPE##", $config['input_type'], $codeStr);
    //替换输出路径
    $cmd = str_replace("##OUTFILE##", "/tmp/tool_result.txt}", $config['cmd']);

    $codeStr = str_replace("##EXECTOOL##", $cmd, $codeStr);

    file_put_contents($indexFile, $codeStr);
}

function addData($serverAddr, $token, $name)
{
    if (strlen($token) != 32) {
        die("你提供的TOKEN不正确,应该是32位字符");
    }

    if (empty($token)) {
        echo '缺少token..';
        die;
    }
    //插入功能列表
    $url = "{$serverAddr}/user_api/addAbility.html?&token={$token}&name={$name}";
    $result = getServerData($url);

    var_dump($result);
}


function build($dockerImage, $toolPath)
{
    //编译dockerfile
    $cmdBase = "cd {$toolPath} && ";

    $cmd = "$cmdBase docker build -t {$dockerImage} .";
    exec($cmd);
}


function getServerData(string $url)
{
    $data = file_get_contents($url);

    var_dump($data);
    $data = json_decode($data, true);
    $data = $data['data'] ?? [];
    return $data;
}