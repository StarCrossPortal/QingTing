<?php

$cmd = "apt install -y git";
exec($cmd);

$data = json_decode(base64_decode($argv[1]), true);

$list = [];
foreach ($data as $bb) {

    $urlInfo = explode("/",$bb);
    $name = str_replace(".git","",$urlInfo[count($urlInfo)-1]);


    $cmd  = "cd /data/tempData && git clone {$bb}";
    exec($cmd);

    
    $list[] = "/data/tempData/{$name}";
} 

echo json_encode($list);