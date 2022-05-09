<?php


//定义要编译的功能
$action = $argv[1] ?? 'build';
$list = isset($argv[2]) ? [$argv[2]] : ['control', 'rad', 'xray', 'sqlmap', 'dirmap', 'dismap', 'oneforall', 'vulmap',
    'semgrep', 'hema', 'composer', 'java', 'python', 'gitee'];


if ($action == 'build') {
    dockerBuild($list);
} else {
    dockerPush($list);
}


function dockerPush($list)
{
    foreach ($list as $name) {
        echo "开始推送镜像 $name" . PHP_EOL;

        //推送镜像
        $imageName = "daxia/qingting:{$name}_latest";
//        $cmd = "docker push {$imageName}";
//        echo $cmd . PHP_EOL;
//        exec($cmd);

        //推送到阿里云
        $imageName = "registry.cn-beijing.aliyuncs.com/{$imageName}";
        $cmd = "docker push {$imageName}";
        echo $cmd . PHP_EOL;
        exec($cmd);

    }
}

//编译
function dockerBuild($list)
{

    foreach ($list as $name) {
        echo "开始编译镜像 $name" . PHP_EOL;
        //编译镜像
        $path = dirname(exec("pwd")) . "/edge";
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