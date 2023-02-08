<?php

//获取输入的参数
$outputPath = "/data/share/output_" . getenv("xflow_node_id") . ".json";

//开始执行代码
$url = getenv('gitlab_server_url');
$token = getenv('private_token');

$url = "http://10.1.1.140:880/";
$token = "glpat-ggjo6Z6aQXWCZ2FNJcsz";
$username = "root";
$password = "UnSoOs7l8YN6dYDQRP/1/dzpKswF7dq7fpyhKBey95A=";

//录入检测结果
$tempList = execTool($url, $token, $username, $password);


//将结果输出到文件
file_put_contents($outputPath, json_encode($tempList, JSON_UNESCAPED_UNICODE));


function execTool($url, $token, $username, $password)
{

    $cmd = "curl --header \"PRIVATE-TOKEN: {$token}\" {$url}/api/v4/projects";
    exec($cmd, $result);
    $uriInfo = parse_url($url);
    $uriInfo['port'] = $uriInfo['port'] ?? 80;

    $resultArr = json_decode($result[0], true);
    if (!isset($resultArr[0])) {
        echo "没有找到gitlab项目列表";
        return [];
    }

    foreach ($resultArr as &$value) {
        $value = ['url' => $value['web_url']];
        preg_match("/\/\/(.*?)\//", $value['url'], $ret);

        $authStr = urlencode($username) . ":" . urlencode($password);
        $value['url'] = str_replace($ret[1], $authStr . "@" . $uriInfo['host'] . ":{$uriInfo['port']}", $value['url']);
    }

    var_dump($resultArr);

    return $resultArr;
}
