<?php
//接收用户token
$token = $argv[1];

// 随机端口
$port = rand(50000, 65000);
var_dump(exec('pwd'));
//下载FRP文件
if (!file_exists("`pwd`/frp/frpc")) {
    exec("mkdir ./frp");
    exec("cd frp && wget  http://txy8g.songboy.site:9999/frpc && wget  http://txy8g.songboy.site:9999/frpc.ini");
}


//替换端口,名称,docker地址
$str = file_get_contents("./frp/frpc.ini");
$str = str_replace("ssh", "ssh-$port", $str);
$str = str_replace("6000", $port, $str);
file_put_contents("./frp/frpc.ini", $str);

//启动打洞
$cmd = 'bash -c "nohup /root/frp/frpc -c /root/frp/frpc.ini > /dev/null 2>&1 &"';
system($cmd);

//注册服务器
$data = ['url' => "tcp://txy8g.songboy.site:{$port}/", 'token' => "dddd"];
$param = http_build_query($data);
$host = "http://txy8g.songboy.site:10000/";
$url = "{$host}/node/auto_register.html?{$param}";


file_get_contents($url);
echo "节点注册成功~";