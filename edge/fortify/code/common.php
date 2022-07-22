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

/**
 * 上传文件
 * @param string $srcPath
 * @param string $key
 * @param array $params
 * @return void
 */
function uploadFile(string $srcPath, string $key)
{
    $secretId = getenv('SecretId'); //替换为用户的 secretId，请登录访问管理控制台进行查看和管理，https://console.cloud.tencent.com/cam/capi
    $secretKey = getenv('SecretKey'); //替换为用户的 secretKey，请登录访问管理控制台进行查看和管理，https://console.cloud.tencent.com/cam/capi
    $region = getenv("region"); //替换为用户的 region，已创建桶归属的region可以在控制台查看，https://console.cloud.tencent.com/cos5/bucket
    $bucket = getenv('bucket'); //存储桶名称 格式：BucketName-APPID
    var_dump($secretId, $secretKey, $region, $bucket);
    if (empty($secretId) || empty($secretKey)) {
        addLog("SecretId | SecretKey | cos_url |region秘钥未配置");
        return false;
    }

    $credentials = ['secretId' => $secretId, 'secretKey' => $secretKey];
    $cosClient = new Qcloud\Cos\Client(['region' => $region, 'schema' => 'https', 'credentials' => $credentials]);

    ### 上传文件流
    try {
        $file = fopen($srcPath, "rb");
        if ($file) {
            $result = $cosClient->putObject(array(
                'Bucket' => $bucket,
                'Key' => $key,
                'Body' => $file));
            print_r($result);
        }
    } catch (\Exception $e) {
        echo "$e\n";
    }
}