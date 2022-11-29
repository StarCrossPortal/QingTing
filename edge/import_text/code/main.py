# coding=utf-8
import os, base64, json, time, logging
import hashlib, csv


# 获取环境变量参数
def getParams():
    inputFile = "/data/share/xflow_input_{}.json".format(os.environ["xflow_node_id"])
    with open(inputFile, 'r') as f1:
        params = json.load(f1)
    return params


def deal_dbline(vision):
    if '\\n' in vision:
        return '\n'
    elif "\\t" in vision:
        return '\t'
    elif "\\r" in vision:
        return '\r'
    elif "'" in vision:
        return vision.replace("'", '')
    else:
        return vision


def export_text(params):
    try:
        data_lst = []
        text =deal_datatype(params['text'])
        # json文本处理
        if type(text)==list or type(text)==dict:
            if type(text) == dict:
                text = [text]
            return text
        # 普通文本处理
        line_separator = deal_dbline(repr(params['line_separator']))
        separator = deal_dbline(repr(params['separator']))
        data = text.strip().split(line_separator)
        for i in data:
            if separator != "" and separator in i:
                data_lst.append(i.split(separator))
            else:
                data_lst = data
        return data_lst
    except Exception as e:
        print(f'导入文本失败{e}')
        execExit(2, f"{e}")


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


def func():
    try:
        params = getParams()
        result = export_text(params)
        print(params)
        # 插入数据
        dataList = []
        for val in result:
            if type(val) == str:
                val = {"raw": val}

            dataList.append(val)
        execExit(0, "", dataList)
    except Exception as e:
        execExit(2, f"{e}")


if __name__ == '__main__':
    func()
