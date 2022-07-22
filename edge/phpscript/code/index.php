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
    $params = getParams();

    $tableName = $params['tableName'];
    $code = $params['script'];

    file_put_contents("./temp.php", $code);

    addlog("PHP脚本 开始工作");

    //读取目标数据,排除已经扫描过的目标
    $list = Db::table($tableName)->where(['xflow_node_id' => $params['source_xflow_node_id']])
        ->where('id', 'NOT IN', function ($query) use ($params) {
        $query->table('scan_log')->where(['tool_name' => $params['xflow_node_id'], 'target_name' => $params['tableName']])->field('id');
    })->limit(100)->select()->toArray();
    foreach ($list as $item) {

        $data = base64_encode($item['raw_data']);
        $cmd = "php ./temp.php $data";
        exec($cmd, $output);

        $data = json_decode($output[0], true);
        if ($data == false) {
            addlog("执行脚本,获得返回值解析失败");
            continue;
        }

        //如果是字符串,需要封装一层
        $data = is_string($data) ? ['data' => $data] : $data;


        //如果是一维数组,封装成二维
        if (is_array($data) && isMap($data)) {
            $data = [$data];
        }

        foreach ($data as $value) {
            //如果是普通字符串,则封装一层
            $value = is_string($value) ? ['data' => $value] : $value;
            //将数据转为json字符串
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);

            $dataOne = ['raw_data' => $value, 'xflow_node_id' => $params['xflow_node_id']];

            $dataOne['hash'] = md5(json_encode($dataOne));

            $result = Db::table('phpscript')->strict(false)->extra("IGNORE")->insert($dataOne);
            var_dump($result);

            //扫描的ID
            updateScanLog($params['xflow_node_id'], $params['tableName'], $item['id'] ?? 0);
        }

    }

    // 修改插件的执行状态
    Db::table('control')->where(['xflow_node_id' =>$params['xflow_node_id'],'task_version'=>$params['task_version']])->update(['status' => 0, 'end_time' => date('Y-m-d H:i:s')]);

    addlog("PHP脚本 执行完毕");
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
