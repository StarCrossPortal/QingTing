<?php
include "./vendor/autoload.php";
include "./common.php";

main();
function main()
{

    $params = getParams();
    $tableName = $params['tableName'];
    $fieldKey = $params['field_values'];
    $fieldRule = $params['rule_values'];
    $fieldValue = $params['value_values'];

    $data_lst = [];
    addlog("filter 脚本 开始工作");

    $targetArr = $params['lists'];
    $data = [];
    foreach ($targetArr as $item) {

        //更新最后扫描的ID
        $exec_result = execTool($item, $fieldKey, $fieldRule, $fieldValue);
        if (empty($exec_result)) {
            addLog(["filter过滤目标后返回结果为空", $item, $fieldKey, $fieldRule, $fieldValue]);
            continue;
        }

        $data[] = $exec_result;

    }


    execExit(0, '', $data);

}

function getParams()
{
    $xflow_node_id = getenv("xflow_node_id");
    $inputPath = "/data/share/xflow_input_{$xflow_node_id}.json";
    $params = json_decode(file_get_contents($inputPath), true);
    return $params;
}

//将工具执行
function execTool($data, $fieldKey, $fieldRule, $value)
{

    $fieldKey = json_decode($fieldKey, true);
    $fieldRule = json_decode($fieldRule, true);
    $value = json_decode($value, true);

    foreach ($fieldKey as $key => $val) {
        if ($fieldRule[$key] == '==') {
            if (isset($data[$fieldKey[$key]]) && !($data[$fieldKey[$key]] == $value[$key])) return false;
        } elseif ($fieldRule[$key] == '>=') {
            if (isset($data[$fieldKey[$key]]) && !($data[$fieldKey[$key]] >= $value[$key])) return false;
        } elseif ($fieldRule[$key] == '<=') {
            if (isset($data[$fieldKey[$key]]) && !($data[$fieldKey[$key]] <= $value[$key])) return false;
        } elseif ($fieldRule[$key] == 'in') {
            if (isset($data[$fieldKey[$key]]) && (strpos($data[$fieldKey[$key]], $value[$key]) === false)) return false;
        } elseif ($fieldRule[$key] == 'not in') {
            if (isset($data[$fieldKey[$key]]) && (strpos($data[$fieldKey[$key]], $value[$key]) === true)) return false;
        }
    }

    return $data;
}

function execExit($code, $msg, $data = [])
{
    $xflow_node_id = getenv("xflow_node_id");
    $outputPath = "/data/share/xflow_output_{$xflow_node_id}.json";
    $data = ["code" => $code, "data" => $data, "msg" => $msg, "params" => getParams()];
    file_put_contents($outputPath, json_encode($data, JSON_UNESCAPED_UNICODE));
    exit();
}


