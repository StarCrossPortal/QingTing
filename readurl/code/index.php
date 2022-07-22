<?php
include "./vendor/autoload.php";
include "./common.php";

use think\facade\Db;

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
    addlog("readurl 开始工作");
    $params = getParams();
    echo json_encode($params, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    $url = $params['url'];
    $content = file_get_contents($url);
    $data = [
        'url' => $url,
        'xflow_node_id' => $params['xflow_node_id'],
        'raw_data' => $content,
    ];
    $data['hash'] = md5(json_encode($data));
    Db::table('readurl')->strict(false)->extra("IGNORE")->insert($data);

    // 修改插件的执行状态
    Db::table('control')->where(['xflow_node_id' =>$params['xflow_node_id'],'task_version'=>$params['task_version']])->update(['status' => 0, 'end_time' => date('Y-m-d H:i:s')]);

    addlog("readurl执行完毕");
}

function getParams()
{
    $params = getenv('params');
    if (empty($params)) {
        addlog("readurl 没有获取到环境变量");
        return false;
    }
    return json_decode(base64_decode($params), true);
}
