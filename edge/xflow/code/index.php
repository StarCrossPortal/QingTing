<?php
include "./vendor/autoload.php";
include "./common.php";
include "./model.php";

use think\facade\Db;

//初始化操作
init();

//初始化数据
//遍历获取当前装备信息
$info = getParams();
initData($info['serverAddr'], $info['xflow_id'], $info['token']);

//扩展程序
$cmd = "php ./safe.php >> /tmp/safe.txt &";
exec($cmd);

//删除之前的数据
Db::table('edge_data')->where('id', '>', 0)->delete();

//主程序
exec_node();

sleep(6);

function getTaskList()
{
    //遍历获取当前装备信息
    $info = getParams();
    $paramStr = http_build_query([
        'usce_id' => $info['xflow_id'],
        'token' => $info['token'],
        'start_node' => $info['startNode'],
        'stop_node' => $info['stopNode'],
        'task_version' => $info['task_version'],
        'select_node' => $info['selectNode'],
    ]);
    $url = "{$info['serverAddr']}/xflow/getUsceConfigV2.html?{$paramStr}";

    $taskList = getServerData($url);

    //获取任务
    foreach ($taskList as $nodeId => $item) {
        $params = $item['params'];
        $data = [
            'node_id' => $nodeId,
            'source_node_id' => json_encode($params['source_xflow_node_id']),
            'task_version' => $params['task_version'],
            'exec_status' => 0,
            'label' => $item['name'],
            'xflow_id' => $params['xflow_id'],
            'ability_name' => $params['ability_name'],
        ];

        Db::table('node_exec_status')->replace()->insert($data);
    }

    return $taskList;
}

function exec_node()
{
    $info = getParams();
    $xflow_id = $info['xflow_id'];
    $version = $info['task_version'];
    //向服务器请求任务列表
    $taskList = getTaskList();

    //开始执行任务
    $i = true;
    while ($i) {
        $list = Db::table('node_exec_status')->where(['xflow_id' => $xflow_id])->select()->toArray();
        $list = array_column($list, null, 'node_id');
        $listCount = count($list);
        $key = 0;
        foreach ($taskList as $nodeId => $value) {
            $key++;
            $preItem = $list[$nodeId];
            $item = $list[$nodeId];
            //如果已经执行,则不再执行
            if ($item['exec_status']) {
//                print_r("节点{$value['name']}已经执行 {$item['exec_status']},跳过 {$item['node_id']}\n");
                continue;
            }

            //如果有上游节点,并且也没有执行，则跳过
            if (!empty($item['source_node_id'])) {
                $sourceNodeArr = json_decode($item['source_node_id'], true);
                if (!is_array($sourceNodeArr)) {
                    print_r(["{$value['note']} 上游数据格式不正确", $item['source_node_id']]);
                    continue;
                }
                foreach ($sourceNodeArr as $preNodeId) {
                    if (is_string($preNodeId) && $list[$preNodeId]['exec_status'] != 2) {
//                        print_r("节点{$value['name']}的上游 {$preNodeId} 没执行完成,跳过 {$item['node_id']}\n");
                        continue 2;
                    }
                }
            }

            //如果没有上游，执行
            execOneTask($value, $info, $key);
            print_r("----------------------------------------------------------\n");

            //每执行一次数量减1,如果都跳过了,就说明执行完成了
            $listCount--;
        }

        //判断是否都跳过了
        $where = ['xflow_id' => $xflow_id, 'exec_status' => 0, 'task_version' => $version];
        $i = Db::table('node_exec_status')->where($where)->count();
        print_r($i);
        sleep(1);
    }
}


function execOneTask($nodeInfo, $envParam, $key)
{
    $dirPath = "./data/{$envParam['xflow_id']}";
    $composeFile = "{$dirPath}/{$envParam['xflow_id']}_{$nodeInfo['name']}_{$key}.yaml";
    //下载compose文件,并将参数写入到xflow_input.json文件里
    downComposeFile($composeFile, $nodeInfo, $envParam);
    //启动容器
    $cmd = "docker-compose -f {$composeFile} up -d ";
    @exec($cmd);
    echo $cmd . PHP_EOL;

    //修改程序的执行状态为1
    changeNodeExecStatus(1, $nodeInfo['params']);
}

//下载docker-compose文件
function downComposeFile(string $fileName, array $ability, array $info)
{
    $abilityName = $ability['name'];
    //如果文件夹不存在,则创建文件
    !file_exists(dirname($fileName)) && mkdir(dirname($fileName), 0777, true);
    $token = $info['token'];
    $url = "{$info['serverAddr']}/xflow/getComposerStr?usce_id={$info['xflow_id']}&ability_name={$abilityName}&xflow_node_id={$ability['params']['xflow_node_id']}&token={$token}";
//    echo $url . PHP_EOL;
    $composeArr = json_decode(file_get_contents($url), true);
    $composeStr = trim(str_replace("...", "", str_replace("---", "", yaml_emit($composeArr, YAML_UTF8_ENCODING))));
    file_put_contents($fileName, $composeStr);
    $ability['params']['token'] = $token;
    //把参数作为json写入到磁盘挂载
    $where = ['task_version' => $ability['params']['task_version']];
    $lists = Db::table('edge_data')->where($where)->whereIn('xflow_node_id', $ability['params']['source_xflow_node_id'])->column('raw_data');

    $sql = Db::table('edge_data')->getLastSql();
    var_dump(["<{$ability['params']['node_name']}> 的上游节点数据  ：", $sql, $lists]);
    foreach ($lists as &$value) {
        $value = json_decode($value, true);
    }
    $inputData = array_merge($ability['params'], ['lists' => $lists]);
    backParam($inputData);


}

function backParam($inputData)
{
    $historyPaht = "/data/share/history/" . date('Y-m-d');
    @mkdir($historyPaht, 0777, true);

//     //备份历史input信息，便于调试分析
     $timeStr = date('H_i_s');
//     $inPath = "/data/share/input.json";
//     $outPath = "/data/share/output.json";
//     if (file_exists($inPath)) rename($inPath, "$historyPaht/input_{$timeStr}.json");
//     if (file_exists($outPath)) rename($outPath, "$historyPaht/output_{$timeStr}.json");

    $inPath = "/data/share/xflow_input_{$inputData['xflow_node_id']}.json";
    if (file_exists($inPath)) rename($inPath, "$historyPaht/xflow_input_{$timeStr}.json");

    file_put_contents($inPath, json_encode($inputData, JSON_UNESCAPED_UNICODE));
}
