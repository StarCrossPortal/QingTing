<?php


use think\facade\Db;

function in_array_strpos($word, $array)
{
    foreach ($array as $v) {
        if (strpos($word, $v) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * 写入日志
 * @param $content
 */
function addlog($content, $out = false)
{

    $datetime = date('Y-m-d H:i:s');
    $content = is_array($content) ? var_export($content, true) : $content;
    echo $datetime . '---' . $content . PHP_EOL;

}

function execLog($shell, &$output)
{
    //转换成字符串
    $remark = "即将执行命令:{$shell}" . PHP_EOL;
    addlog($remark);
    //记录日志
    //shell_exec($shell);
    exec($shell, $output);
    addlog(["命令执行结果", $shell, $output]);
}

function getSavePath($url, $tool = "sqlmap", $id = 0)
{
    $urlInfo = parse_url($url);

    $urlInfo['path'] = isset($urlInfo['path']) ? $urlInfo['path'] : "";

    $urlInfo['path'] = str_replace("/", '_', $urlInfo['path']);

    $rand = rand(1000000, 90000000);
    $rand = 11;
    $path = dirname(__DIR__) . "/runtime/temp/{$urlInfo['host']}{$urlInfo['path']}_{$id}_{$tool}_rand{$rand}";
    $path = str_replace("//", "/", $path);
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }

    $pathArr = [
        'path' => $path,
        'tool_result' => "{$path}_toolResult.json",
        'cmd_result' => "{$path}_cmdResult.json"
    ];


    return $pathArr;
}

function updateScanLog($toolName, $targetName, $lastId, $tid = 0)
{
    //修改工具状态
    $data = ['tool_name' => $toolName, 'target_name' => $targetName, 'data_id' => $lastId, 'tid' => $tid];
    Db::table('scan_log')->replace()->save($data);
}

//执行系统命令,并记录日志
function systemLog($shell, $showRet = true)
{
    //转换成字符串
    $remark = "即将执行命令:{$shell}";
    addlog($remark);
    //记录日志
    exec($shell, $output);
    addlog(["命令执行结果", $shell, $output]);
    if ($output && $showRet) {
        echo implode("\n", $output) . PHP_EOL;
    }

    return $output;
}