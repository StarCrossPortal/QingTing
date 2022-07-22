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
    //首次运行延时20秒,等待MySQL初始化完成,再执行主程序
    if (!file_exists("/tmp/init_lock.txt")) {
        sleep(10);
        file_put_contents("/tmp/init_lock.txt", 1);
    }
    //数据库配置信息,无需改动
    Db::setConfig($config);
}

function main()
{

    $awvs_url = getenv('awvs_url');
    $awvs_token = getenv('awvs_token');

    if (empty($awvs_url) || empty($awvs_token)) {
        $errMsg = ["执行AWVS扫描任务失败,未找到有效得配置信息", $awvs_url, $awvs_token];
        addlog($errMsg);
        return false;
    }
    // 循环读取状态值,直到执行完成
    while (true) {
        //程序是否执行存放与状态控制表中,如果可以执行才执行,否则休眠。
        $result = Db::table('control')->where(['ability_name' => 'awvs', 'status' => 1])->find();
        //如果还不能开始,则休眠15秒后继续
        if (empty($result)) {
            sleep(15);
            continue;
        }
        addlog("awvs 开始工作");

        //读取目标数据,排除已经扫描过的目标
        $targetArr = Db::table('target')->where('id', 'NOT IN', function ($query) {
            $query->table('scan_log')->where(['tool_name' => 'awvs', 'target_name' => 'target'])->field('data_id');
        })->where(['scan_status'=>1])->limit(20)->select()->toArray();

        $targetArr = Db::table('target')->limit(20)->select()->toArray();
        foreach ($targetArr as $value) {
            //定义变量
            list($url, $id, $user_id, $tid) = [$value['url'], $value['id'], $value['user_id'], $value['id'],];

            //获取AWVS中的ID,如果还没有则创建目标
            $targetId = getTargetId($id, $url, $awvs_url, $awvs_token);
            if (!$targetId) {
                continue;
            }
            //获取扫描状态
            $retArr = getScanStatus($targetId, $awvs_url, $awvs_token);
            if ($retArr && is_array($retArr)) {
                addVulnList($retArr['last_scan_id'], $retArr['last_scan_session_id'], $awvs_url, $awvs_token, $tid);
            }
        }
        //更新最后扫描的ID
        updateScanLog('awvs', 'target', $value['id'] ?? 0);

        // 修改插件的执行状态
        Db::table('control')->where(['ability_name' => 'awvs'])->update(['status' => 0, 'end_time' => date('Y-m-d H:i:s')]);

        addlog("awvs执行完毕");
        sleep(20);
    }

}


function addVulnList($scanId, $scanSessionId, $awvs_url, $awvs_token, $tid)
{
    $vulnList = getVulnList($scanId, $scanSessionId, $awvs_url, $awvs_token);
    foreach ($vulnList['vulnerabilities'] as $value) {
        if (empty($value['tags'])) {
            continue;
        }
        $detail = getDetail($scanId, $scanSessionId, $value['vuln_id'], $awvs_url, $awvs_token);
        $value = array_merge($value, $detail);
        foreach ($value as $k => $v) {
            $value[$k] = is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE);
        }

        $value['tid'] = $tid;
        $value['hash'] = md5(json_encode($value));
        $id = Db::table('awvs')->extra('IGNORE')->insertGetId($value);
        if ($id) {
            $value['tool_name'] = 'awvs';
            $value['detail'] = $value['vt_name'];
            $value['vul_type'] = $value['vt_name'];
            $value['data_id'] = $id;
            Db::table('bugs')->extra('IGNORE')->insert($value);
        }
    }
}

function getTargetId($id, $url, $awvs_url, $awvs_token)
{
    //判断URL是否有效
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        addlog(["URL地址不正确", $id, $url]);
        return false;
    }

    //判断目标是否已经创建
    $appInfo = Db::table('awvs_task_list')->where(['tid' => $id])->find();
    if (!empty($appInfo)) {
        return $appInfo['target_id'];
    }

    //新增一个目标
    $appInfo = addTarget($url, $awvs_url, $awvs_token);
    if (!isset($appInfo['target_id'])) {
        $errMsg = ["任务发送到AWVS失败,请在容器内检查是否能访问到AWVS服务地址{$awvs_url}，以及token有效性~", $id, $url];
        addlog($errMsg, true);
        return false;
    }
    $appInfo['tid'] = $id;
    Db::table('awvs_task_list')->extra('IGNORE')->insert($appInfo);

    //通过目标ID创建扫描任务
    startScan($appInfo['target_id'], $awvs_url, $awvs_token);
}

function addTarget($url, $awvs_url, $awvs_token)
{
    addlog("开始生成新任务");
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $awvs_url . "/api/v1/targets");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "{\"address\": \"{$url}\",\"description\": \"xxxx\",\"criticality\": \"10\"}");
    $headers = array();
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'X-Auth: ' . $awvs_token;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
        return false;
    }
    curl_close($ch);
    $appInfo = json_decode($result, true);

    return $appInfo;
}


function getScanStatus($targetId, $awvs_url, $awvs_token)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $awvs_url . '/api/v1/targets/' . $targetId);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $headers = array();
    $headers[] = 'X-Auth: ' . $awvs_token;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        addlog(["获取AWVS扫描状态失败", curl_error($ch)]);
    }
    curl_close($ch);

    $retArr = json_decode($result, true);

    if (isset($retArr['code']) && $retArr['code'] == 404) {
        addlog(["未在AWVS中找到此目标ID", $targetId]);
        Db::table('awvs_task_list')->where(['target_id' => $targetId])->delete();
        return false;
    }
    //API未授权
    if (isset($retArr['code']) && $retArr['code'] == 401) {
        $errMsg = ["AWVS未授权,请参照文档配置地址和token...", $awvs_url];
        addlog($errMsg);
        return false;
    }

    //判断目标扫描状态
    if (!isset($retArr['last_scan_session_status']) or $retArr['last_scan_session_status'] != 'completed') {
        addlog("目标[{$targetId}]还未扫描完成,请耐心等待...");
        return false;
    }

    return $retArr;
}


function getVulnList($scanId, $scanSessionId, $awvs_url, $awvs_token)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $awvs_url . "/api/v1/scans/{$scanId}/results/{$scanSessionId}/vulnerabilities?l=1000&s=severity:desc");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $headers = array();
    $headers[] = 'X-Auth: ' . $awvs_token;
    $headers[] = 'Content-Type: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }
    curl_close($ch);

    return json_decode($result, true);

}

function getDetail($scanId, $scanSessionId, $vulnId, $awvs_url, $awvs_token)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $awvs_url . "/api/v1/scans/{$scanId}/results/{$scanSessionId}/vulnerabilities/{$vulnId}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $headers = array();
    $headers[] = 'X-Auth: ' . $awvs_token;
    $headers[] = 'Content-Type: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }
    curl_close($ch);

    return json_decode($result, true);
}

function startScan($targetId, $awvs_url, $awvs_token)
{
    $postData = "{\"profile_id\":\"11111111-1111-1111-1111-111111111119\",\"schedule\":{\"disable\":false,\"start_date\":null,\"time_sensitive\":false},\"target_id\":\"{$targetId}\"}";
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $awvs_url . '/api/v1/scans');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

    $headers = array();
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'X-Auth: ' . $awvs_token;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }
    curl_close($ch);


}
