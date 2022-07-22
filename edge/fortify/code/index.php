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
    //数据库配置信息,无需改动
    Db::setConfig($config);

    $i = true;
    while ($i) {
        try {
            $isTable = Db::query('SHOW TABLES LIKE "fortify"');
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

    $codeBasePath = "/data/code";
    if (!file_exists($codeBasePath)) mkdir($codeBasePath, 0777, true);
// 循环读取状态值,直到执行完成
    while (true) {
        //程序是否执行存放与状态控制表中,如果可以执行才执行,否则休眠。
        $result = Db::table('control')->where(['ability_name' => 'fortify', 'status' => 1])->find();
        if (empty($result)) {
            sleep(15);
            continue;
        }
        addlog("fortify 开始工作");

        //读取目标数据,排除已经扫描过的目标
        $targetArr = Db::table('target')->where('id', 'NOT IN', function ($query) {
            $query->table('scan_log')->where(['tool_name' => 'fortify', 'target_name' => 'target'])->field('data_id');
        })->where(['scan_status' => 1])->limit(20)->select()->toArray();
        //
        foreach ($targetArr as $v) {
            $urlInfo = parse_url($v['url']);
            $prName = $urlInfo['path'] ?? md5($v['url']);
            $prName = str_replace("/", '_', trim($prName, '/'));

            $outpath = "/data/tmp/{$prName}";
            if (!file_exists($outpath)) mkdir($outpath, 0777, true);
            $codePath = "$codeBasePath/{$prName}";

            //执行下载代码
            $result = downCode($codeBasePath, $prName, $v['url'], []);
            if (!$result) {
                continue;
            }
            //执行检测脚本
            $result = execTool($codePath, $outpath);
            if ($result !== true) continue;

            //录入检测结果
            $result = writeData($prName, $v, $outpath);
            if ($result !== true) continue;

            //更新最后扫描的ID
            updateScanLog('fortify', 'target', $v['id'] ?? 0);

        }
        //更新最后扫描的ID
        updateScanLog('fortify', 'target', $value['id'] ?? 0);

        // 修改插件的执行状态
        Db::table('control')->where(['ability_name' => 'fortify'])->update(['status' => 0, 'end_time' => date('Y-m-d H:i:s')]);

        addlog("fortify执行完毕");
        sleep(20);
    }

}


//写入数据到数据库
function writeData($prName, $value, $outpath)
{
    $fprFile = "{$outpath}.fpr";
    $xmlFile = "{$outpath}.xml";
    $baseUrl = getenv('cos_url');
    $url = "{$baseUrl}{$fprFile}";

    if (file_exists($xmlFile) === false) {
        addlog(["fortify的XML文件不存在:{$xmlFile}", $value]);
        return false;
    }

    //上传FPR文件
    if (file_exists($fprFile) === true) {
        uploadFile($fprFile, $fprFile);
    }
    $bugList = getFortifData($xmlFile, $url);

    //4. 存储结果
    addDataAll($value['id'], $bugList, $value['user_id']);

    return true;

}


function execTool($codePath, $outPath)
{
    $buildId = md5($codePath);

    if (file_exists($outPath)) {
        chmod($outPath, 0777);
    }

    $fortifyPath = "/data/tools/fortify_linux";
    $base = "cd {$fortifyPath}/bin && ";
    if (file_exists("{$outPath}.fpr") == false) {
        $cmd = $base . "./sourceanalyzer -b {$buildId} -clean";
        systemLog($cmd);
        $cmd = $base . "./sourceanalyzer -b {$buildId} -Xmx4096M -Xms2048M -Xss48M     -source 1.8 -machine-output   {$codePath}";
        systemLog($cmd);
        $cmd = $base . "./sourceanalyzer -b {$buildId} -scan -format fpr    -f {$outPath}.fpr -machine-output ";
//        $cmd .= " -no-default-rules  -rules  {$fortifyPath}/Core/config/rules/core_php.bin";
        systemLog($cmd);
    } else {
        addlog(["fortify扫描文件 {$outPath}.fpr 已存在,不再不再重新扫描"]);
    }

    if (file_exists("{$outPath}.xml") == false) {
        $cmd = $base . "./ReportGenerator  -format xml -f {$outPath}.xml -source {$outPath}.fpr -template DeveloperWorkbook.xml";
        systemLog($cmd);
    }
    //删除日志
    file_exists("/root/.fortify/sca20.2/log/sca.log") && exec("echo '' > /root/.fortify/sca20.2/log/sca.log");
    file_exists("/root/.fortify/sca20.2/log/sca_FortifySupport.log") && exec("echo '' > /root/.fortify/sca20.2/log/sca_FortifySupport.log");
    return true;
}

function getFortifData($xmlPath, $frpUrl)
{

    $str = file_get_contents($xmlPath);

    $obj = simplexml_load_string($str, "SimpleXMLElement", LIBXML_NOCDATA);
    $test = json_decode(json_encode($obj), true);

    if (!isset($test['ReportSection'][2])) {
        echo "{$xmlPath} 数据为空";
        return [];
    }

    $list = $test['ReportSection'][2]['SubSection']['IssueListing']['Chart']['GroupingSection'] ?? [];

    $list = isset($list['Issue']) ? [$list] : $list;

    $data = [];
    foreach ($list as &$value) {
        unset($value['MajorAttributeSummary']);
        $value = isset($value['Issue'][0]) ? $value['Issue'] : [$value['Issue']];
        foreach ($value as &$val) {
            unset($val['@attributes']);
            foreach ($val as &$v) {
                $v = is_string($v) ? $v : json_encode($v);
            }
            $data[] = $val;
        }
    }

    foreach ($data as &$value) {
        $Primary = empty($value['Primary']) ? [] : json_decode($value['Primary'], true);
        $Source = empty($value['Source']) ? [] : json_decode($value['Source'], true);

        $value['Source_filename'] = $Source['FilePath'] ?? '';
        $value['Source_LineStart'] = $Source['LineStart'] ?? '';
        $value['Primary_filename'] = $Primary['FilePath'] ?? '';
        $value['Primary_LineStart'] = $Primary['LineStart'] ?? '';
        $value['Source_Snippet'] = $Primary['Snippet'] ?? '';
        $value['Primary_Snippet'] = $Primary['Snippet'] ?? '';
        $value['fpr_url'] = $frpUrl;
    }

    return $data;
}


function addDataAll(int $tid, array $data, $user_id = 0)
{
    foreach ($data as $key => $value) {
        $value['tid'] = $tid;
        $value['user_id'] = $user_id;
        $value['hash'] = md5($value['tid'] . $value['Category'] . $value['Abstract'] . $value['Primary']);
        try {
            $id = Db::table('fortify')->strict(false)->extra("IGNORE")->insertGetId($value);
            if ($id) {
                $value['file'] = !empty($value['Primary_filename']) ? $value['Primary_filename'] : $value['Source_filename'];
                $value['vul_type'] = $value['Category'];
                $value['tool_name'] = 'fortify';
                Db::table('code_audit')->strict(false)->extra("IGNORE")->insertGetId($value);
            }
        } catch (Exception $e) {
            addlog(["插入数据出现错误", $e->getMessage()]);
        }

    }
}

function downCode(string $codePath, string $prName, string $codeUrl, array $authInfo)
{
    if (!file_exists("{$codePath}/{$prName}")) {
        $cmd = "cd {$codePath}/ && git clone --depth=1 {$codeUrl}  $prName";
        systemLog($cmd, $result);
        $result = implode("\n", $result);
        if (is_string($result) && !empty($result) && !strstr('resolve', $result)) {
            addLog("拉取代码[{$codeUrl}] 失败,暂时跳过~");
            return false;
        } else {
            addlog(["命令执行返回结果", $cmd, $result]);
        }
    } else {
        $cmd = "cd {$codePath}/{$prName} && git pull ";
        systemLog($cmd);
    }

    return true;
}

//执行系统命令,并记录日志
function systemLog($shell, &$output = [])
{
    //转换成字符串
    $remark = "即将执行命令:{$shell}";
    addlog($remark);
    //记录日志
    exec($shell, $output);
}

function getParams()
{
    $params = getenv('params');
    if (empty($params)) {
        addlog("fortify 没有获取到环境变量");
        return false;
    }
    return json_decode(base64_decode($params), true);
}
