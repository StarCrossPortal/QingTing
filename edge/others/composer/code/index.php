<?php
//获取输入的参数
$inputPath = "/data/share/input_" . getenv("xflow_node_id") . ".json";
$outputPath = "/data/share/output_" . getenv("xflow_node_id") . ".json";if (!file_exists($inputPath)) {
    print_r("未找到必要的参数文件:{$inputPath}");
}

$list = json_decode(file_get_contents($inputPath), true);

$data = [];
foreach ($list as $key => $value) {
    //接收必要参数
    $codePath = $value['code_path'];

    //开始执行
    $data = array_merge($data, writeData($codePath));
}


//将结果输出到文件
file_put_contents($outputPath, json_encode($data, JSON_UNESCAPED_UNICODE));


function writeData(string $codePath)
{

    $fileArr = getFilePath($codePath, 'composer.lock');
    if (!$fileArr) {
        exec("rm -rf {$codePath}");
        addlog("[{$codePath}]扫描composer依赖失败,composer.lock 依赖文件不存在:{$codePath}");
        return false;
    }

    $packages_list = [];
    foreach ($fileArr as $value) {
        $json = file_get_contents($value['file']);
        if (empty($json)) {
            addlog("项目文件内容为空:{$value['file']}");
            continue;
        }
        $json = str_replace('"require-dev"', '"require_dev"', $json);
        $json = str_replace('"notification-url"', '"notification_url"', $json);
        $arr = json_decode($json, true);
        $packages = $arr['packages'];
        foreach ($packages as &$val) {
            foreach ($val as $k => $temp) {
                $val[$k] = is_string($temp) ? $temp : json_encode($temp, JSON_UNESCAPED_UNICODE);
            }
        }
        $packages_list = array_merge($packages, $packages_list);
    }
    return $packages_list;
}

function getFilePath($dir, $filename, $level = 1)
{
    static $files = [];
    if (!is_dir($dir)) {
        return $files;
    }
    if ($level > 3) {
        return $files;
    }

    foreach (scandir($dir) as &$file_name) {
        if ($file_name == '.' || $file_name == '..' || (file_exists($file_name) && $file_name != $filename)) {
            continue;
        }
        if ($file_name == $filename) {
            $files[] = [
                'filepath' => $dir,
                'filename' => $file_name,
                'file' => $dir . "/{$filename}"
            ];
        }
        if (is_dir($dir . DIRECTORY_SEPARATOR . $file_name)) {
            getFilePath($dir . DIRECTORY_SEPARATOR . $file_name, $filename, $level + 1);
        }
    }
    return $files;
}


