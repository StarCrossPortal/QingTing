<?php

//获取输入的参数
$outputPath = "/data/share/output_" . getenv("xflow_node_id") . ".json";

//开始执行代码
$url = getenv('gitlab_server_url');
$token = getenv('private_token');

$url = "http://123.249.16.91:8993";
$token = "glpat-yat8bav7i79t3gawAyrh";

//录入检测结果
$tempList = execTool($url, $token);


//将结果输出到文件
file_put_contents($outputPath, json_encode($tempList, JSON_UNESCAPED_UNICODE));


function execTool($url, $token)
{

    $cmd = "curl --header \"PRIVATE-TOKEN: {$token}\" {$url}/api/v4/projects";

    exec($cmd, $result);

    $resultArr = json_decode($result[0], true);
    if (!isset($resultArr[0])) {
        echo "没有找到gitlab项目列表";
        return [];
    }

    foreach ($resultArr as &$value) {
        $value['url'] = $value['web_url'];
    }

    print_r(array_column($resultArr, 'web_url', 'name'));

    return $resultArr;
}
