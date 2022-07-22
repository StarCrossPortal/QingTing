<?php


//定义要编译的功能
$action = $argv[1] ?? 'build';

$default = ['control', 'rad', 'xray', 'sqlmap', 'dirmap', 'dismap', 'oneforall', 'vulmap',
    'semgrep', 'hema', 'composer', 'java', 'python', 'gitee', 'awvs', 'fortify', 'nmap', 'fscan', 'webcrack', 'masscan', 'knock'];
$default = [
    "readfile","readurl","httpsend","fofasearch","qaxvulget","pyscript","phpscript","fofafilter","qaxvulformat","qaxvulformat",
    "murphysec","dingding","webhook"];
$list = isset($argv[2]) ? [$argv[2]] : $default;


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
       echo file_get_contents("http://127.0.0.1:8000/login/ability_update/name/{$name}/version/{$version}");
        // echo file_get_contents("http://qingting.starcross.cn/login/ability_update/name/{$name}/version/{$version}");
    }
}

//编译
function dockerBuild($list)
{

    foreach ($list as $name) {
        echo "开始编译镜像 $name" . PHP_EOL;
        //编译镜像
        $path = exec("pwd") . "/edge";
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