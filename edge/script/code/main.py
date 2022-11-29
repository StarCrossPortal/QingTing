# coding=utf-8
import os, base64, json, time, logging, datetime
import hashlib, sys
from Db import reConndb


# 获取环境变量参数
def getParams():
    inputFile = "/data/share/xflow_input_{}.json".format(os.environ["xflow_node_id"])
    with open(inputFile, 'r') as f1:
        params = json.load(f1)
    return params


# 处理datetime,json

def del_datetime_json(lst):
    data = []
    for i in range(len(lst)):
        for j in lst[i]:
            if type(lst[i][j]) == datetime.datetime:
                lst[i][j] = str(lst[i][j])
        data.append(lst[i])
    return data


# 处理数据类型

def deal_datatype(vision):
    print(vision)
    try:
        if vision.startswith("[") or vision.startswith("{"):
            vision = json.loads(vision)
        return vision
    except Exception as e:
        print("数据类型有误{}".format(e))
        return vision


def exec_sql(sql_code, xflow_id):
    cursor = reConndb(xflow_id).cursor()
    cursor.execute(code_content)
    lst = list(cursor.fetchall())
    with open('/data/share/output.json', 'w') as f2:
        json.dump(lst, f2)


def installPackage(params):
    current_path = os.path.abspath('.')
    # 判断是否需要安装依赖
    if "dep_package" in params and params['dep_package'] != '':
        dep_package = params['dep_package']
        with open(current_path + "/dep_package.txt", 'w') as f:
            f.write(dep_package)
        cmd = "pip install -r {}".format(current_path + "/dep_package.txt")
        # 执行安装依赖
        result1 = os.popen(cmd).read()
        print("安装依赖完成 {} {}".format(dep_package, result1))


# 执行相应的脚本语句
def exec_script(params, data):
    try:
        current_path = os.path.abspath('.')
        lanage_type = params['type']
        code_content = params['code_content']
        input_path = '/data/share/'
        if os.path.exists(input_path) == False:
            os.mkdir(input_path)
        inputParamFile = "/data/share/input_{}.json".format(os.environ["xflow_node_id"])
        with open(inputParamFile, 'w') as f:
            json.dump(data, f, ensure_ascii=False)
        data = json.dumps(data, default=str)
        if lanage_type.lower() == "python  3.7":
            installPackage(params)
            # 拼接要执行的代码
            file_name = current_path + "/temp.py"
            cmd = "python3 {}".format(file_name)
            # 如果需要传递参数
            if data is not None:
                cmd = "python3 {}".format(file_name)
        elif lanage_type.lower() == "php 7.4":
            file_name = current_path + "/temp.php"
            cmd = "php {} ".format(file_name)
            # 如果需要传递参数
            if data is not None:
                cmd = "php {}".format(file_name)
        elif lanage_type.lower() == "sql":
            lst = exec_sql(code_content, params['xflow_id'])
            lst = del_datetime_json(lst)
            return json.dumps(lst)
        elif lanage_type.lower() == "shell":
            cmd = code_content

        if os.path.exists(file_name):
            os.popen("rm -rf {}".format(file_name)).read()
        with open(file_name, 'w') as f1:
            f1.write(code_content)

        # 执行临时脚本
        print("开始执行命令: " + cmd)
        cmdRet = os.popen(cmd).read()
        print(cmdRet)


    except Exception as e:
        print("exec_script 组件执行异常{}".format(e))
        execExit(2, f"{e}")


def exec_main(params, value=None):
    outputFile = "/data/share/output_{}.json".format(os.environ["xflow_node_id"])
    try:
        data = value if value != None else None
        exec_script(params, data)
        if os.path.exists(outputFile) == False:
            print("执行没有产生结果文件:{}".format(outputFile))
            return []
        with open(outputFile, 'r') as f1:
            result = json.load(f1)
        # 如果数据格式不是统一的
        if type(result) != list and len(result) > 0:
            result = [{"raw": result}]

        return result
    except IOError as e:
        print('exec_main 组件执行异常{}'.format(e))
        execExit(2, f"{e}")


def func():
    try:
        params = getParams()
        dataList = []
        if params['tableName'] != "":
            targetArr = params['lists']
            if params['OneTime'] == '读入全部数据一次性运行':
                dataList = exec_main(params, targetArr)
            else:
                for value in targetArr:
                    tempList = exec_main(params, value)
                    dataList = dataList + tempList

        else:
            dataList = exec_main(params)

        # 更新控制表状态
        execExit(0, "", dataList)
    except IOError as e:
        print('组件执行异常:{}'.format(e))
        execExit(2, f"{e}")


def execExit(code, msg, data=[]):
    params = getParams()
    data = {"code": code, "data": data, "msg": msg, "params": params}
    outputFile = "/data/share/xflow_output_{}.json".format(params['xflow_node_id'])
    print("往{}写入数据:".format(outputFile), data)
    with open(outputFile, 'w') as f:
        json.dump(data, f, ensure_ascii=False)
    exit()


if __name__ == '__main__':
    func()
