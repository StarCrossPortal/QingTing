# coding=utf-8
import pymysql
import os,base64,json,time,logging
from peewee import *
import hashlib,csv


#处理状态码
def execExit(code, msg, data=[]):
    params = getParams()
    data = {"code": code, "data": data, "msg": msg, "params": params}
    outputFile = "/data/share/xflow_output_{}.json".format(params['xflow_node_id'])
    print("往{}写入数据:".format(outputFile), data)
    with open(outputFile, 'w') as f:
        json.dump(data, f, ensure_ascii=False)
    exit()

# 获取环境变量参数
def getParams():
    inputFile = "/data/share/xflow_input_{}.json".format(os.environ["xflow_node_id"])
    with open(inputFile, 'r') as f1:
        params = json.load(f1)
    return params




#处理数据类型

def deal_datatype(vision):
    try:
        if vision.startswith("[") or vision.startswith("{"):
            vision = json.loads(vision)
        return vision
    except:
        return vision


def deal_dbline(vision):
    if '\\n' in vision:
        return '\n'
    elif "\\t" in vision:
        return '\t'
    elif "\\r" in vision:
        return '\r'
    elif "'" in vision:
        return vision.replace("'",'')
    else:
        return vision


def read_file(params):
    node_name=params['node_name']
    file_type=params['file_type']
    file_path=params['file_path']
    if os.path.exists(file_path):
        if file_type=='CSV':
            with open(file_path,encoding='utf-8') as csvfile:
                reader=csv.reader(csvfile)
                column = [row for row in reader]
            if params['start_line']=='1':
                data=column[1:]
            else:
                data=column
            return json.dumps(data,ensure_ascii=False)
        elif file_type=='TXT':

            with open(file_path, 'r') as f:
                data = f.read()
                data_lst = []
                line_separator = deal_dbline(repr(params['line_separator']))
                separator = deal_dbline(repr(params['separator']))
                data = data.strip().split(line_separator)
                for i in data:
                    if separator in i:
                        data_lst.append(i.split(separator))
                    else:
                        data_lst.append({"raw": i})
            return json.dumps(data_lst,ensure_ascii=False)
        elif file_type=='JSON':
            with open(file_path,'r') as f:
                data=f.read().replace(" ","")
            return json.dumps(data,ensure_ascii=False)
        else:
            execExit(2, "文件类型有误")
    execExit(2, "读取的文件不存在")



def func():
    try:
        params = getParams()
        name=params['node_name']
        result=json.loads(read_file(params))
        if type(result) == str:
            result = deal_datatype(result)
        # 如果数据格式不是统一的
        if type(result) != list and len(result) > 0:
            result = [result]
        # 插入数据
        dataList = []
        for val in result:
            m = hashlib.md5()
            m.update(str(val).encode('utf8'))
            hashStr = m.hexdigest()

            if type(val) == str:
                val = deal_datatype(val)
            if type(val) == str:
                val = {"raw": val}

            dataList.append(json.dumps(val, ensure_ascii=False))
        execExit(0,"",dataList)
    except Exception as e:
        execExit(2, f"{e}")


if __name__ == '__main__':
    func()






