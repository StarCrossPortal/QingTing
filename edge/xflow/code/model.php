<?php


require 'vendor/autoload.php';

//上传数据
use think\facade\Db;
use \PhpMqtt\Client\MqttClient;
use \PhpMqtt\Client\ConnectionSettings;


//控制容器执行状态
function controlStatus(int $concurrent)
{
    $concurrent = ($concurrent < 1) ? 1 : $concurrent;
//    addlog("控制容器执行状态");
    // 循环读取状态值,直到执行完成
    while (true) {
        $result = Db::table('control')->where(['status' => 1])->count();
        if ($result < $concurrent) {
            // 修改插件的执行状态
            $list = Db::table('control')->where(['status' => 0])->orderRand()->limit($concurrent - $result)->select();
            foreach ($list as $info) {
                $where = ['status' => 0, 'ability_name' => $info['ability_name']];
                $data = ['status' => 1, 'start_time' => date('Y-m-d H:i:s')];
                Db::table('control')->where($where)->update($data);
            }
        }

        sleep(5);
    }
}


//插入初始化数据
function initData($serverAddr, $usceId, $token)
{

    //创建表结构
    createControl();
    createTables($serverAddr, $usceId);

    file_put_contents("/tmp/init_lock.txt", 1);
}


function createTables($serverAddr, $usceId)
{
    $lock = true;
    while ($lock) {
        //获取插件建表语句
        $tables = getServerData("{$serverAddr}/user_api/get_ability_create_sql.html?usce_id={$usceId}");
        if (empty($tables)) {
            addlog("初始化请求建表语句失败,休息后继续执行");
            sleep(5);
            continue;
        }
        foreach ($tables as $tableName => $sql) {
            addlog("开始创建表 {$tableName}");
//            //删除之前的表
//            $deleteSql = "DROP TABLE IF EXISTS `{$tableName}`";
//            Db::execute($deleteSql);
            //创建新表
            $sql = str_replace("CREATE TABLE", "CREATE TABLE If NOT EXISTS ", $sql);
            Db::execute($sql);
        }

        $lock = false;
    }
}


function downAction($serverAddr, $token, $taskId)
{
    while (true) {
        //获取当前用户有哪些目标
        $lastId = Db::table('down_action')->order('id', 'desc')->value('id');
        $url = "{$serverAddr}/user_api/down_action.html?token={$token}&usceId={$taskId}&lastId={$lastId}";
        $list = getServerData($url);
        $list = is_array($list) ? $list : [];
        foreach ($list as $val) {
            $val['status'] = 1;
            $result = Db::table('down_action')->strict(false)->extra("IGNORE")->insert($val);
            if (empty($result)) {
                continue;
            }

            try {
                if ($val['action'] == 'delete') {
                    Db::table($val['table'])->where(json_decode($val['where'], true))->delete();
                } else if ($val['action'] == 'insert') {
                    Db::table($val['table'])->extra("IGNORE")->strict(false)->insert(json_decode($val['data'], true));
                } else if ($val['action'] == 'update') {
                    Db::table($val['table'])->where(json_decode($val['where'], true))->strict(false)->update(json_decode($val['data'], true));
                }
            } catch (\Exception $e) {  //如书写为（Exception $e）将无效
                addlog(["执行指令遇到错误", $val, $e->getMessage()]);
                continue;
            }

            //上报执行事件完成状态
            $url = "{$serverAddr}/user_api/down_action_ok.html?token={$token}&usceId={$taskId}&lastId={$val['id']}";
            uploadDataMqtt($url);
        }
        sleep(60);
    }

}


// 创建控制表
function createControl()
{
    addlog("创建控制表");
    $sql = "DROP TABLE IF EXISTS `control`;";
    $result = Db::execute($sql);

    $sql = "CREATE TABLE If NOT EXISTS `control` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ability_id` int DEFAULT NULL,
  `ability_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `task_version` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `xflow_node_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `status` int DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `un_name` (`ability_name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;";
    $result = Db::execute($sql);

}


function getMqtt($xflow_id)
{
    $server = '49.232.77.154';
    $port = 1883;
    $clientId = "edge_{$xflow_id}_" . rand(1000, 9999);
    $clean_session = false;

    $connectionSettings = new ConnectionSettings();
    $connectionSettings
//    ->setUsername($username)
//    ->setPassword($password)
        ->setKeepAliveInterval(300)
        ->setLastWillTopic('emqx/test/last-will')
        ->setLastWillMessage('client disconnect')
        ->setLastWillQualityOfService(1);


    $mqtt = new MqttClient($server, $port, $clientId);
    $mqtt->connect($connectionSettings, $clean_session);
    printf("client connected\n");
    return $mqtt;
}


function received($usceId, $token)
{
    $mqtt = getMqtt($usceId);
    $mqtt->subscribe("edge_received_{$usceId}", function ($topic, $message) {
        var_dump('接收到一条数据:', json_decode($message, true));

    }, 0);

    $mqtt->loop(true);
}


function uploadMqttData($usceId, $token)
{
//      $mqtt = getMqtt($usceId);
//
//      //获取表列表
//      $tables = ['httpsend', 'script', 'read_database', 'filter', 'contain_log', 'readfile', 'import_text','mqtt_message'];
//      while (true) {
//          foreach ($tables as $tableName) {
//              $lastId = Db::table('upload_log')->where(['ability_name' => $tableName])->value('upload_last_id');
//              //如果还没有记录,先插入一条记录
//              if ($lastId === null) {
//                  Db::table('upload_log')->strict(false)->insert(['ability_name' => $tableName, 'upload_last_id' => 0]);
//                  $lastId = 0;
//              }
//              $result = Db::table($tableName)->where('id', '>', $lastId)->limit(10)->select()->toArray();
//              print_r("{$tableName}__{$lastId}__" . count($result) . PHP_EOL);
//              if (empty($result)) {
//                  continue;
//              }
//              //避免log反复,上传log表数据的时候不做记录。
//              ($tableName != 'log') && addlog("开始上传 {$tableName} 表数据");
//              $tempId = 0;
//              $data = ['table' => $tableName, 'token' => $token, 'data' => []];
//              foreach ($result as $item) {
//                  $tempId = $item['id'];
//                  unset($item['id']);
//                  $item['usce_id'] = $usceId;
//                  $data['data'][] = $item;
//              }
//
//              $mqtt->publish('edge_upload_data', json_encode($data), 2, true);
//
//              //更新状态
//              Db::table('upload_log')->where(['ability_name' => $tableName])->update(['upload_last_id' => $tempId]);
//          }
//          if (rand(0, 9) == 1) sleep(1);
//      }
//
//      $mqtt->loop(true);
}

function init()
{

    //数据库配置信息,无需改动
    $config = require('./config.php');
    Db::setConfig($config);
    $i = true;
    while ($i) {
        try {
            $isTable = Db::query('SHOW TABLES');
        } catch (\Exception $e) {
            // 这是进行异常捕获
            echo "MySQL服务未启动:" . $e->getMessage() . PHP_EOL;
            sleep(1);
            continue;
        }

        $i = false;
    }


}


function Db_test_connect($usceId)
{
    $path = 'db_test_connect.py';
    $cmd = "cd /root/code && python3 {$path} {$usceId}";
    $output = exec($cmd, $output);
}


function uploadDataMqtt(string $url)
{
    $params = getParams();
    $urlInfo = parse_url($url);
    parse_str($urlInfo['query'], $urlData);

    $data = ['topic' => $urlInfo['path'], 'data' => json_encode($urlData, JSON_UNESCAPED_UNICODE), 'usce_id' => $params['xflow_id']];
    Db::table('mqtt_message')->insert($data);

    return true;
}

function readResult()
{

    while (true) {
        insertOutput();
        usleep(200);
    }
}

/**
 * 将结果插入到磁盘,并修改节点运行状态
 * @return false|mixed
 */
function insertOutput()
{

    //获取要读取的列表
    $params = getParams();
    $where = ['xflow_id' => $params['xflow_id'], 'task_version' => $params['task_version']];
    $nodeList = Db::table('node_exec_status')->where($where)->select()->toArray();
    foreach ($nodeList as $node) {
        $nodeId = $node['node_id'];
        $outputFile = "/data/share/xflow_output_{$nodeId}.json";
        //判断文件是否已经存在
        if (!file_exists($outputFile)) {
            echo "文件不存在 {$outputFile}".PHP_EOL;
            continue;
        }
        //防止磁盘还未写入完整
        $tempContent = file_get_contents($outputFile);
        if ($tempContent && $tempContent[strlen($tempContent) - 1] && $tempContent[strlen($tempContent) - 1] != '}') {
            print_r("文件内容异常 $outputFile" . PHP_EOL);
            continue;
        }
        //格式验证
        $outputInfo = json_decode($tempContent, true);
        if (isset($outputInfo['data']) == false) {
            print_r("文件内容异常 $outputFile" . PHP_EOL);
            var_dump(file_get_contents($outputFile));
            continue;
        }

        //打印输出的内容
        print_r($outputInfo);
        foreach ($outputInfo['data'] as $key => $value) {
            if (empty($value)) {
                print_r("输出的内容内容为空");
                continue;
            }
            $value = is_array($value) ? $value : ['raw' => $value];
            $data = [
                'raw_data' => json_encode($value, JSON_UNESCAPED_UNICODE),
                'xflow_node_id' => $outputInfo['params']['xflow_node_id'],
                'node_name' => $outputInfo['params']['node_name'],
                'task_version' => $outputInfo['params']['task_version'],
                'usce_id' => $outputInfo['params']['xflow_id']
            ];
            Db::table('edge_data')->insert($data);
        }

        //修改控制表状态,并通知服务器已经处理完事
        if (isset($outputInfo['params']) && is_array($outputInfo['params'])) {
            changeNodeExecStatus(2, $outputInfo['params']);
        }

        //备份历史,用于分析
        $historyPaht = "/data/share/history/" . date('Y-m-d');
        @mkdir($historyPaht, 0777, true);
        rename($outputFile, "$historyPaht/xflow_output_{$node['ability_name']}_{$nodeId}_{$params['task_version']}.json");
    }

}

/**
 * 修改节点执行状态
 * @param $status
 * @param $ability
 */
function changeNodeExecStatus($status, array $nodeParams)
{
    //修改执行状态
    $where = ['node_id' => $nodeParams['xflow_node_id'], 'task_version' => $nodeParams['task_version']];
    try {
        Db::table('node_exec_status')->where($where)->update(['exec_status' => $status]);
    } catch (\think\db\exception\DbException $e) {
        var_dump("修改状态出错:", $e);
    }

    $info = getParams();
    $ability = $nodeParams;
    $url = "{$info['serverAddr']}/xflow/update_node_exec_status.html?usce_id={$info['xflow_id']}&token={$info['token']}&xflow_node_id={$ability['xflow_node_id']}&status={$status}&ability_name={$ability['ability_name']}&ability_id={$ability['ability_id']}&task_version={$ability['task_version']}";
    uploadDataMqtt($url);


}