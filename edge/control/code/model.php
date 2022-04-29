<?php


require 'vendor/autoload.php';

//上传数据
use think\facade\Db;

function heartbeat($serverAddr, $nodeId, $token, $taskId)
{
//    $ipInfo = json_decode( file_get_contents("http://ipinfo.io/"), true);

    while (true) {
        $url = "{$serverAddr}/user_api/heartbeat.html?node_id={$nodeId}&token={$token}&task_id={$taskId}";
        $result = getServerData($url);
        $msg = $result ? '成功' : "失败  URL: $url";
        addlog("心跳连接到服务器 {$msg}");
        sleep(5);
    }
}

function uploadData($serverAddr, $sceId, $token)
{
    addlog("开始将节点数据上传到服务器");
    $tables = getServerData("{$serverAddr}/user_api/get_upload_table_name.html?id={$sceId}");

    $url = "{$serverAddr}/user_api/upload_data_batch.html";
    $header = ['Content-Type' => 'application/json;charset=UTF-8'];
    while (true) {
        foreach ($tables as $tableName) {

            $lastId = Db::table('upload_log')->where(['ability_name' => $tableName])->value('upload_last_id');
            //如果还没有记录,先插入一条记录
            if ($lastId === null) {
                Db::table('upload_log')->insert(['ability_name' => $tableName, 'upload_last_id' => 0]);
                $lastId = 0;
            }
            $result = Db::table($tableName)->where('id', '>', $lastId)->limit(50)->select()->toArray();
            if (empty($result)) {
                continue;
            }
            addlog("开始上传 {$tableName} 表数据");
            $tempId = 0;
            $data = ['table' => $tableName, 'token' => $token, 'data' => []];
            foreach ($result as $item) {
                $tempId = $item['id'];
                unset($item['id']);
                $data['data'][] = $item;
            }


            $rawRet = Requests::post($url, $header, json_encode($data));
            $response = json_decode($rawRet->body, true);

            if (empty($response['data'])) {
                addlog("上传 {$tableName} 表数据" . count($result) . "条,成功 {$response['data']} 条");
            }


            //更新状态
            Db::table('upload_log')->where(['ability_name' => $tableName])->update(['upload_last_id' => $tempId]);

        }

        sleep(15);
    }
}


//控制容器执行状态
function controlStatus(int $concurrent)
{
    $concurrent = ($concurrent < 1) ? 1 : $concurrent;
    addlog("控制容器执行状态");
    // 循环读取状态值,直到执行完成
    while (true) {
        $result = Db::table('control')->where(['status' => 1])->count();
        if ($result < $concurrent) {
            // 修改插件的执行状态
            $list = Db::table('control')->where(['status' => 0])->orderRand()->limit($concurrent - $result)->select();
            foreach ($list as $info) {
                addlog("正在将 {$info['ability_name']} 状态修改为可执行");
                $where = ['status' => 0, 'ability_name' => $info['ability_name']];
                $data = ['status' => 1, 'start_time' => date('Y-m-d H:i:s')];
                Db::table('control')->where($where)->update($data);
            }
        }
        sleep(5);
    }
}

function getServerData(string $url)
{
    $data = file_get_contents($url);
    $data = json_decode($data, true);
    return $data['data'] ?? [];
}


//插入初始化数据
function initData($serverAddr, $sceId, $token)
{
    if (file_exists("/tmp/init_lock.txt")) {
        addlog("初始化操作已经执行过一次了,重复执行会导致数据丢失!!!");
//        return true;
    }
    //创建表结构
    createControl();
    createTables($serverAddr, $sceId);

    //获取场景所拥有的的功能
    addAbilityControl($serverAddr, $sceId);

    //同步扫描记录
    syncScanTarget($serverAddr, $token);

    file_put_contents("/tmp/init_lock.txt", 1);
}

function syncScanTarget($serverAddr, $token)
{
    //插入功能控制数据
    addlog("插入扫描记录数据");
    //获取当前用户有哪些目标
    $url = "{$serverAddr}/user_api/get_scan_log.html?token={$token}";
    $list = getServerData($url);

    foreach ($list as $value) {
        unset($value['id']);
        addlog(["插入扫描记录数据", $value['tool_name'], $value['target_name']], $value['data_id']);
        Db::table('scan_log')->extra("IGNORE")->insertAll($value);
    }

}

function createTables($serverAddr, $sceId)
{
    $lock = true;
    while ($lock) {
        //获取插件建表语句
        $tables = getServerData("{$serverAddr}/user_api/get_ability_create_sql.html?id={$sceId}");
        if (empty($tables)) {
            addlog("初始化请求建表语句失败,休息后继续执行");
            sleep(5);
            continue;
        }
        foreach ($tables as $tableName => $sql) {
            addlog("开始创建表 {$tableName}");
            $sql = str_replace("CREATE TABLE", "CREATE TABLE If NOT EXISTS ", $sql);
            Db::execute($sql);
        }

        $lock = false;
    }
}

function addAbilityControl($serverAddr, $sceId)
{
    //插入功能控制数据
    addlog("插入初始化数据");
    $lock = true;
    while ($lock) {
        $url = "{$serverAddr}/user_api/get_ability.html?id={$sceId}";
        $data = getServerData($url);

        if (empty($data)) {
            addlog(["初始化请求场景功能列表失败,休息后继续执行", $url]);
            sleep(5);
            continue;
        }

        foreach ($data as $val) {
            if (in_array($val, ['urls', 'bugs', 'scan_log', 'target'])) {
                continue;
            }

            // 往数据库插入表数据状态
            $data = ['ability_name' => $val, 'status' => '0'];
            Db::table('control')->extra("IGNORE")->insert($data);
        }
        $lock = false;
    }

}

function insertTarget($serverAddr, $token)
{
    $lastId = 0;
    while (true) {
        //获取当前用户有哪些目标
        $url = "{$serverAddr}/user_api/get_target.html?token={$token}&lastId={$lastId}";
        $list = getServerData($url);
        $list = is_array($list) ? $list : [];
        foreach ($list as $val) {

            $info = Db::table('target')->where($val)->find();
            if (empty($info)) {
                //插入目标
                addlog("添加新目标 {$val['name']}  {$val['url']}");
                $result = Db::table('target')->extra("IGNORE")->insertGetId($val);
                $lastId = empty($result) ? $lastId : $val['id'];
            }
        }
        sleep(5);
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
  `status` int DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `un_name` (`ability_name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;";
    $result = Db::execute($sql);

}