<?php


// This file is auto-generated, don't edit it. Thanks.
namespace AlibabaCloud\SDK\Sample;
include "./vendor/autoload.php";
include "./common.php";

use think\facade\Db;
use AlibabaCloud\SDK\Domain\V20180129\Domain;
use \Exception;
use AlibabaCloud\Tea\Exception\TeaError;
use AlibabaCloud\Tea\Utils\Utils;

use Darabonba\OpenApi\Models\Config;
use AlibabaCloud\SDK\Domain\V20180129\Models\QueryDomainListRequest;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;

class Sample
{

    /**
     * 使用AK&SK初始化账号Client
     * @param string $accessKeyId
     * @param string $accessKeySecret
     * @return Domain Client
     */
    public static function createClient($accessKeyId, $accessKeySecret)
    {
        $config = new Config([
            // 您的 AccessKey ID
            "accessKeyId" => $accessKeyId,
            // 您的 AccessKey Secret
            "accessKeySecret" => $accessKeySecret
        ]);
        // 访问的域名
        $config->endpoint = "domain.aliyuncs.com";
        return new Domain($config);
    }

    /**
     * @param string[] $args
     * @return void
     */
    public static function main()
    {
        $params = getParams();
        $accessKeyId = $params['accessKeyId'];
        $accessKeySecret = $params['accessKeySecret'];
        $client = self::createClient($accessKeyId, $accessKeySecret);
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
            Utils::assertAsString($error->message);
        }

        $data = [];

        foreach ($list->body->data->domain as $obj) {
            $data[] = ['domainName' => $obj->domainName, 'expirationDate' => $obj->expirationDate];
        }

        return $data;
    }
}


//初始化操作
init();
//主程序
main();

function init()
{
    $config = require('./config.php');
    //数据库配置信息,无需改动
    Db::setConfig($config);
}

function main()
{
    addlog("ali_domain_list 开始工作");

    $params = getParams();
    $url = $params['url'];
    $domainList = Sample::main();
    foreach ($domainList as $domain){
        $data = [
            'url' => $url,
            'xflow_node_id' => $params['xflow_node_id'],
            'raw_data' => json_encode($domain, JSON_UNESCAPED_UNICODE),
        ];
        $data['hash'] = md5(json_encode($data));
        Db::table('ali_domain_list')->strict(false)->extra("IGNORE")->insert($data);
    }


    // 修改插件的执行状态
    $where = ['xflow_node_id' => $params['xflow_node_id'], 'task_version' => $params['task_version']];
    Db::table('control')->where($where)->update(['status' => 0, 'end_time' => date('Y-m-d H:i:s')]);

    addlog("ali_domain_list执行完毕");
}

function getParams()
{
    $params = getenv('params');
    if (empty($params)) {
        addlog("ali_domain_list 没有获取到环境变量");
        return false;
    }
    return json_decode(base64_decode($params), true);
}
