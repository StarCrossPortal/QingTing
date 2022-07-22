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
    //扩展程序
    $cmd = "php ./safe.php >> /tmp/safe.txt &";
    exec($cmd);


    //数据库配置信息,无需改动
    $config = require('./config.php');
    Db::setConfig($config);


    sleep(3);
    $i = true;
    while ($i) {
        try {
            $isTable = Db::query('SHOW TABLES LIKE "control"');
        } catch (\Exception $e) {
            // 这是进行异常捕获
            echo "MySQL服务未启动:" . $e->getMessage() . PHP_EOL;
            sleep(1);
            continue;
        }

        if ($isTable) {
            $i = false;
        }
        echo "初始化暂未完成..." . PHP_EOL;
        sleep(1);
    }


}

function main()
{
    //遍历获取当前装备信息
//    $info = [
//        'while' => 1,
//        'user_id' => 16,
//        'xflow_id' => 619,
//        'token' => '1ca4725c34758183af3fd1f723f07a31',
//        'serverAddr' => 'http://txy8g.songboy.site:10000',
//    ];

    $info = getParams();

    $url = "{$info['serverAddr']}/xflow/getUsceConfig.html?usce_id={$info['xflow_id']}&token={$info['token']}&start_node={$info['startNode']}&stop_node={$info['stopNode']}&task_version={$info['task_version']}";
    $taskList = getServerData($url);


    //判断流程图执行周期,如果
    $i = 10;
    do {
        $i++;
        execTask($info, $taskList);
        $sleepTime = empty($info['exec_cycle']) ? 1000000000 : $info['exec_cycle'];
        sleep($sleepTime);
    } while (true);//表达式为循环条件

}

function execTask(array $info, array $taskListFull)
{
    foreach ($taskListFull as $taskList) {
        //遍历执行容器
        foreach ($taskList as $item) {
            $dirPath = "./data/{$info['xflow_id']}";
            $composeFile = "{$dirPath}/{$info['xflow_id']}_{$item['name']}.yaml";

            //下载文件
            downComposeFile($composeFile, $item, $info);
            //创建表结构
            crreateTable($item['name'], $info, $item['params']);
            //停止上一轮执行
            $cmd = "docker-compose -f {$composeFile} down";
            exec($cmd);
            echo $cmd . PHP_EOL;

            //运行容器,修改状态,轮训等待执行完毕
            runContainer($item, $composeFile, $info);
        }
    }
    //修改流程图执行状态

    //添加日志
    addlog("执行完毕");
}


//运行容器
function runContainer($ability, string $fileName, array $info)
{

    $data = ['ability_id' => $ability['params']['ability_id'], 'ability_name' => $ability['name'], 'status' => 1, 'task_version' => $info['task_version'], 'xflow_node_id' => $ability['params']['xflow_node_id']];
    Db::table('control')->replace()->insert($data);


    //启动容器
    $cmd = "docker-compose -f {$fileName} up -d";
    exec($cmd);
    echo $cmd . PHP_EOL;

    changeNodeExecStatus($info, 0, $ability);
    change_CurrentNodeStatus($info, $ability);
    //轮训判断执行是否完成
    while (true) {
        $where = ['xflow_node_id' => $ability['params']['xflow_node_id'], 'task_version' => $info['task_version']];
//        $where = ['ability_name' => $ability['name'], 'task_version' => $info['task_version']];
        $isExec = Db::table('control')->where($where)->value('status');

        if (empty($isExec)) {
            changeNodeExecStatus($info, 1, $ability);
            change_CurrentNodeStatus($info, $ability);
            break;
        }
        //每隔一秒查询状态
        sleep(1);
    }
}

function changeNodeExecStatus($info, $status, $ability)
{

    $url = "{$info['serverAddr']}/xflow/update_node_exec_status.html?usce_id={$info['xflow_id']}&token={$info['token']}&xflow_node_id={$ability['params']['xflow_node_id']}&status={$status}&ability_name={$ability['params']['ability_name']}&ability_id={$ability['params']['ability_id']}&task_version={$ability['params']['task_version']}";
    $result = getServerData($url);
    $msg = $status ? "节点执行完成" : "节点开始执行";

}

function change_CurrentNodeStatus($info, $ability)
{

    $url = "{$info['serverAddr']}/xflow/update_current_node_status.html?token={$info['token']}&xflow_node_id={$ability['params']['xflow_node_id']}&task_version={$ability['params']['task_version']}";
    $result = getServerData($url);


}


//下载docker-compose文件
function downComposeFile(string $fileName, array $ability, array $info)
{

    $abilityName = $ability['name'];
    //如果文件夹不存在,则创建文件
    !file_exists(dirname($fileName)) && mkdir(dirname($fileName), 0777, true);

    $token = $info['token'];
    $url = "{$info['serverAddr']}/xflow/getComposerStr?usce_id={$info['xflow_id']}&ability_name={$abilityName}&token={$token}";
    var_dump($url);
    echo $url . PHP_EOL;
    $composeStr = file_get_contents($url);
    $composeArr = json_decode($composeStr, true);

    $ability['params']['token'] = $token;
    $paramsStr = base64_encode(json_encode($ability['params'], JSON_UNESCAPED_UNICODE));
    $composeArr['services'][$abilityName]['environment'][] = "params={$paramsStr}";


    $composeStr = trim(str_replace("...", "", str_replace("---", "", yaml_emit($composeArr))));

    file_put_contents($fileName, $composeStr);
}

function crreateTable(string $abilityName, array $info)
{
    //获取插件建表语句
    $token = $info['token'];
    $sql = getServerData("{$info['serverAddr']}/user_api/get_ability_sql.html?ability_name={$abilityName}&token={$token}");

    if (empty($sql)) {
        addlog("初始化请求建表语句失败,休息后继续执行");
        sleep(5);
        return false;
    }

    addlog("开始创建表 {$abilityName}");
    $sql = str_replace("CREATE TABLE", "CREATE TABLE If NOT EXISTS ", $sql);
    Db::execute($sql);
}


