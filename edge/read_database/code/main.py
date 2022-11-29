# coding=utf-8
import pymysql
import os, base64, json, time, logging
import hashlib, datetime


def reConndb(host, user, pwd, database, port):
    # 数据库连接重试功能和连接超时功能的DB连接
    _conn_status = True
    _max_retries_count = 20  # 设置最大重试次数
    _conn_retries_count = 0  # 初始重试次数
    _conn_timeout = 3  # 连接超时时间为3秒
    while _conn_status and _conn_retries_count <= _max_retries_count:
        try:
            print('连接数据库中..')
            db = pymysql.connect(host=host,
                                 user=user,
                                 password=pwd,
                                 database=database,
                                 port=port,
                                 autocommit=True,
                                 cursorclass=pymysql.cursors.DictCursor
                                 )

            _conn_status = False  # 如果conn成功则_status为设置为False
            print("连接数据库成功")
            return db
        except:
            _conn_retries_count += 1
            print('数据库连接失败!!')
            time.sleep(3)
            continue


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


# 处理datetime数据类型
def deal_datetime(data):
    new_slt = []
    for i in range(len(data)):
        for j in data[i]:
            if type(data[i][j]) == datetime.datetime:
                data[i][j] = str(data[i][j])
        new_slt.append(data[i])

    return new_slt


def read_data(params):
    try:
        sql_type = params['type']
        host = params['host']
        username = params['username']
        password = params['password']
        database = params['database']
        port = params['port']
        table_name = params['table_name']
    except Exception as e:
        execExit(2, '获取数据库参数有误')

    try:
        if sql_type == 'MySQL':
            cursor = reConndb(host, username, password, database, int(port)).cursor()
            if params['is_read_all'] == '读取全表':
                cursor.execute("select * from `{}`".format(table_name))
                lst = list(cursor.fetchall())
                return lst
            elif params['is_read_all'] == '读取指定条数' and params['lines_num'] != None:
                lines_num = params['lines_num']
                cursor.execute("select * from `{}` limit {}".format(table_name, int(lines_num)))
                lst = list(cursor.fetchall())
                return lst

    except Exception as e:
        execExit(2, "连接数据库失败{}".format(e))


def func():
    try:
        params = getParams()
        result = read_data(params)
        result = deal_datetime(result)
        dataList = []
        for val in result:
            m = hashlib.md5()
            m.update(str(val).encode('utf8'))
            dataList.append(json.dumps(val, ensure_ascii=False))
        execExit(0, "", dataList)
    except Exception as e:
        execExit(2, f"{e}")


if __name__ == '__main__':
    func()
