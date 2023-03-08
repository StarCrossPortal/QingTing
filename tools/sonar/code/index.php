<?php
//获取输入的参数
$inputFile = "/data/share/input_" . getenv("xflow_node_id") . ".json";
$outputPath = "/data/share/output_" . getenv("xflow_node_id") . ".json";
if (!file_exists($inputFile)) {
    print_r("未找到必要的参数文件:{$inputFile}");
}


$list = json_decode(file_get_contents($inputFile), true);


$data = [];
foreach ($list as $key => $value) {
    //接收必要参数
    $codePath = $value['code_path'];

    //开始执行
    $data = array_merge($data, execTool($codePath));
}

var_dump($data);

//将结果输出到文件
file_put_contents($outputPath, json_encode($data, JSON_UNESCAPED_UNICODE));


function execTool($codePath)
{
    $sonar_url = "<<sonar_url>>";
    $sonar_token = "<<sonar_token>>";
    $prName = pathinfo($codePath)['filename'];

    $cmd = "docker run --rm -e SONAR_HOST_URL=\"{$sonar_url}\"  -e SONAR_SCANNER_OPTS=\"-Dsonar.projectKey={$prName}\"   -e SONAR_LOGIN=\"{$sonar_token}\" -v \"{$codePath}:/usr/src\"  sonarsource/sonar-scanner-cli";
    exec($cmd, $execRet);

    //获取漏洞详情
    $result = getIssues($sonar_url, $prName, $sonar_token);

    return $result;
}


function getIssues($url, $name, $token)
{
    $url = rtrim($url, '/');
    $baseStr = base64_encode("$token:");

    $result = [];
    $total = 100;
    $page = 1;
    while ($total == 100) {
        $cmd = "curl --request GET  --url '{$url}/api/issues/search?p={$page}&componentKeys={$name}&types=BUG&ps=100&additionalFields=_all'  --header 'Authorization: Basic $baseStr'";
        exec($cmd, $response);

        $response = json_decode($response[0], true);
        $tempList = $response['issues'] ?? [];
        $result = array_merge($result, $tempList);
        $total = count($tempList);
        $page++;
    }

    return $result;
}