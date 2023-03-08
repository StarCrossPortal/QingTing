<?php

$hostArr = ['local' => 'local.qingting.starcross.cn','dev' => '49.232.77.154', 'online' => '123.249.16.91'];
//定义要编译的功能
$action = $argv[1] ?? 'build';
$host = isset($argv[3]) ? $hostArr[$argv[3]] : $hostArr['dev'];

$default = [];
$list = isset($argv[2]) ? [$argv[2]] : $default;


if ($action == 'build') {
    dockerBuild($list);
} else {
    dockerPush($list, $host);
}


function dockerPush($list, $host)
{
    foreach ($list as $name) {
        echo "开始推送镜像 $name" . PHP_EOL;

        //推送镜像
        $imageName = "daxia/qingting:{$name}_latest";
        $version = date('YmdHi');
        $newimageName = "daxia/qingting:{$name}_{$version}";
//        $cmd = "docker push {$imageName}";
//        echo $cmd . PHP_EOL;
//        exec($cmd);


        //TAG加上版本号
        $cmd = "docker tag registry.cn-beijing.aliyuncs.com/{$imageName} registry.cn-beijing.aliyuncs.com/{$newimageName}";
        echo $cmd . PHP_EOL;
        exec($cmd);

        //推送到阿里云
        $imageName = "registry.cn-beijing.aliyuncs.com/{$newimageName}";
        $cmd = "docker push {$imageName}";
        echo $cmd . PHP_EOL;
        exec($cmd);

        //修改最新版本号
        $url = "http://{$host}/api/login/ability_update/name/{$name}/version/{$version}";
        echo $url . PHP_EOL;
        file_get_contents($url);
    }
}

//编译
function dockerBuild($list)
{

    foreach ($list as $name) {
        echo "开始编译镜像 $name" . PHP_EOL;
        //编译镜像
        $path = exec("pwd");
        $imageName = "daxia/qingting:{$name}_latest";
        $cmd = "cd {$path}/{$name} && docker build -t {$imageName} .";

        echo $cmd . PHP_EOL;
        exec($cmd);

        //阿里云
        $cmd = "docker tag {$imageName} registry.cn-beijing.aliyuncs.com/{$imageName}";
        echo $cmd . PHP_EOL;
        exec($cmd);
    }
}