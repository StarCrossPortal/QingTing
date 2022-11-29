<?php

include "./vendor/autoload.php";

use AlibabaCloud\SDK\Domain\V20180129\Domain;
use AlibabaCloud\Tea\Exception\TeaError;

use Darabonba\OpenApi\Models\Config;
use AlibabaCloud\SDK\Domain\V20180129\Models\QueryDomainListRequest;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;

//获取输入的参数
$inputPath = "/data/share/input_" . getenv("xflow_node_id") . ".json";
$outputPath = "/data/share/output_" . getenv("xflow_node_id") . ".json";if (!file_exists($inputPath)) {
    print_r("未找到必要的参数文件:{$inputPath}");
}

$list = json_decode(file_get_contents($inputPath), true);

$data = [];
foreach ($list as $key => $value) {
    //接收必要参数
    $accessKeyId = $value['accessKeyId'];
    $accessKeySecret = $value['accessKeySecret'];

    //开始执行
    $tempList = execTool($accessKeyId, $accessKeySecret);
    $data = array_merge($data, $tempList);
}


//将结果输出到文件
file_put_contents($outputPath, json_encode($data, JSON_UNESCAPED_UNICODE));


function execTool($accessKeyId, $accessKeySecret)
{
    $client = createClient($accessKeyId, $accessKeySecret);
    $queryDomainListRequest = new QueryDomainListRequest([
        "pageNum" => 1,
        "pageSize" => 50
    ]);
    $runtime = new RuntimeOptions([]);
    try {
        // 复制代码运行请自行打印 API 的返回值
        $list = $client->queryDomainListWithOptions($queryDomainListRequest, $runtime);
    } catch (Exception $error) {
        if (!($error instanceof TeaError)) {
            $error = new TeaError([], $error->getMessage(), $error->getCode(), $error);
        }
        // 如有需要，请打印 error
        var_dump($error->message());
    }

    $data = [];

    foreach ($list->body->data->domain as $obj) {
        $data[] = ['domainName' => $obj->domainName, 'expirationDate' => $obj->expirationDate];
    }

    return $data;
}

function createClient($accessKeyId, $accessKeySecret)
{
    $config = new Config([
        "accessKeyId" => $accessKeyId,
        "accessKeySecret" => $accessKeySecret
    ]);
    // 访问的域名
    $config->endpoint = "domain.aliyuncs.com";
    return new Domain($config);
}
