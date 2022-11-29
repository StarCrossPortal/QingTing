<?php



// 定义grep关键词,和需要执行的命令
$keyList = [
    'control.php uploadData' => 'cd /root/code  &&  php control.php uploadData  >> /tmp/uploadData.txt & ',
//    'control.php Db_test_connect' => 'cd /root/code  &&  php control.php Db_test_connect  >> /tmp/Db_test_connect.txt & ',
    'control.php Clog' => 'cd /root/code  &&  php control.php Clog  >> /tmp/Clog.txt & ',
    'control.php readResult' => 'cd /root/code  &&  php control.php readResult  >> /tmp/readResult.txt & ',
];

// 死循环不断监听任务是不是挂了
$timeSleep = 5;
$i = true;
while (true) {
    // 遍历需要监控的关键词和对应的脚本
    foreach ($keyList as $key => $value) {
        // 执行命令查看任务是否已经执行
        $cmd = "ps -ef | grep '{$key}' | grep -v ' grep'";
        $result = [];
        exec($cmd, $result);
        // 如果返回值长度是0说明任务没有执行
        if (count($result) == 0) {
            // 执行命令
            exec($value);
            print_r("{$key} 进程已结束，正在重启此进程..." . PHP_EOL);
            print_r($value);
        }
    }
    // 每次循环完毕将休眠2个小时
    sleep($timeSleep);
}