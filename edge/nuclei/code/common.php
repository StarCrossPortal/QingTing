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

function getSavePath($url, $tool = "xray", $id = 0)
{
    $urlInfo = parse_url($url);

    $path = "/tmp/{$urlInfo['host']}";
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }

    $pathArr = ['path' => $path, 'tool_result' => "{$path}/toolResult.json", 'cmd_result' => "{$path}/cmdResult.json"];

    return $pathArr;
}

function updateScanLog($toolName, $targetName, $lastId)
{
    //修改工具状态
    $data = ['tool_name' => $toolName, 'target_name' => $targetName, 'data_id' => $lastId];
    Db::table('scan_log')->replace()->save($data);
}

function getTargetByUrl($url, $type)
{

    $urlInfo = parse_url($url);

    if ($type == 'domain') {
        $url = $urlInfo['host'];
    } else if ($type == 'ip') {
        $url = gethostbyname($urlInfo['host']);
    }

    return $url;
}