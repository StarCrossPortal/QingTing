# coding=utf-8

import requests
import json, base64, time, os
import logging, hashlib


# 获取环境变量参数
def getParams():
    inputFile = "/data/share/xflow_input_{}.json".format(os.environ["xflow_node_id"])
    with open(inputFile, 'r') as f1:
        params = json.load(f1)
    return params

def execExit(code, msg, data=[]):
    params = getParams()
    data = {"code": code, "data": data, "msg": msg, "params": params}
    outputFile = "/data/share/xflow_output_{}.json".format(params['xflow_node_id'])
    print("往{}写入数据:".format(outputFile), data)
    with open(outputFile, 'w') as f:
        json.dump(data, f, ensure_ascii=False)
    exit()


# 处理数据类型
def deal_datatype(vision):
    try:
        if vision.startswith("[") or vision.startswith("{"):
            vision = json.loads(vision)
        return vision
    except:
        return vision
        print('数据类型有误')


# 执行结果插入数据库
def InsertResultData(params):
    outputFile = "/data/share/output_{}.json".format(os.environ["xflow_node_id"])
    # 如果结果文件不存在,直接返回
    if os.path.exists(outputFile) == False:
        # 更新执行完成状态
        execExit(2, "结果文件不存在")
        return True

    # 读取文件
    with open(outputFile, 'r') as f1:
        result = json.load(f1)

    # 插入数据
    dataList = []
    for val in result:
        if type(val) == str:
            val = {"raw": val}

        dataList.append(val)
    execExit(0, "", dataList)


def containers(cmd, params):
    if "<<sys>>" in cmd:
        params.pop('cmd')
        parstr = ''
        for k, v in params.items():
            if v != None and v != '' and k != 'lists':
                parstr = "{} -e {}='{}' ".format(parstr, k, v)

        # 拼接容器挂在目录
        vols = {"/data/share": "/data/share", "/data/code": "/data/code"}
        for k, v in vols.items():
            parstr = "{} -v {}:{} ".format(parstr, k, v)

        # 替换关键词
        cmd = cmd.replace('<<sys>>', parstr)
        print(cmd)
    # 获取上游数据
    data_lst = []
    if "tableName" in params and params['tableName'] != '':
        data_lst = params['lists']
    # 如果没有上游,那么程序只执行一次
    if params['tableName'] == '':
        params['OneTime'] = '读入全部数据一次性运行'

    inputParamFile = "/data/share/input_{}.json".format(os.environ["xflow_node_id"])
    if params['OneTime'] == '读入全部数据一次性运行':
        with open(inputParamFile, 'w') as f:
            json.dump(data_lst, f, ensure_ascii=False)
        os.system(cmd)
        InsertResultData(params)
    else:
        if len(data_lst) > 0:
            for value in data_lst:
                with open(inputParamFile, 'w') as f:
                    json.dump([value], f, ensure_ascii=False)
                os.system(cmd)
                InsertResultData(params)

def main():
    params = getParams()
    cmd = params['cmd']
    containers(cmd, params)


if __name__ == '__main__':
    main()
