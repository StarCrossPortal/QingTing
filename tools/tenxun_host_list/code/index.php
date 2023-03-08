<?php

include "./vendor/autoload.php";

use TencentCloud\Common\Credential;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Cvm\V20170312\CvmClient;
use TencentCloud\Cvm\V20170312\Models\DescribeInstancesRequest;


//获取输入的参数
$inputFile = "/data/share/input_".getenv("xflow_node_id").".json";
$outputFile = "/data/share/output_".getenv("xflow_node_id").".json";

//没有input,直接返回
if (!file_exists($inputFile)) {
    file_put_contents($outputFile, json_encode([]));
    return 0;
}
//读取上游数据
$list = json_decode(file_get_contents($inputFile), true);
$data = [];
//处理数据
foreach ($list as $val) {
    $SecretId = getenv('accessKeyId');
    $SecretKey = getenv('accessKeySecret');
    $region = getenv('region');


    $tempList = getHostList($SecretId, $SecretKey, $region);

    $data = array_merge($data, $tempList);
}
//将结果写入到指定位置,供蜻蜓平台导入数据
file_put_contents($outputFile, json_encode($data, JSON_UNESCAPED_UNICODE));

function getHostList($SecretId, $SecretKey, $region)
{
    try {
        $cred = new Credential($SecretId, $SecretKey);
        // 实例化一个http选项，可选的，没有特殊需求可以跳过
        $httpProfile = new HttpProfile();
        $httpProfile->setEndpoint("cvm.tencentcloudapi.com");

        // 实例化一个client选项，可选的，没有特殊需求可以跳过
        $clientProfile = new ClientProfile();
        $clientProfile->setHttpProfile($httpProfile);
        // 实例化要请求产品的client对象,clientProfile是可选的
        $client = new CvmClient($cred, $region, $clientProfile);

        // 实例化一个请求对象,每个接口都会对应一个request对象
        $req = new DescribeInstancesRequest();

        $params = array();
        $req->fromJsonString(json_encode($params));

        // 返回的resp是一个DescribeInstancesResponse的实例，与请求对象对应
        $resp = $client->DescribeInstances($req);

        // 输出json格式的字符串回包

    } catch (TencentCloudSDKException $e) {
        echo $e;
        return [];
    }

    return json_decode($resp->toJsonString(), true);
}

