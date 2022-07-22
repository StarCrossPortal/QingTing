
# coding=utf-8
import pymysql
import requests
import os,base64,json
import hashlib
import time
from peewee import *

db = pymysql.connect(host='mysql_addr',
                     user='root',
                     password='123',
                     database='edge',
                     port=3306,autocommit=True)

cursor = db.cursor()


def func():
    params = json.loads(base64.b64decode(os.environ["params"]))

    if "url"  not in params:
        return "读取url参数失败"
    url=params["url"]

    if "headers"  not in params:
        return "未获取到headers参数"

    headers = {'parmas': params["headers"]}
   

    result= requests.get(url,headers=headers)

    datas={"url":url,'xflow_node_id' :params['xflow_node_id'],"raw_data":result.text}
    m = hashlib.md5()
    m.update(str(datas).encode('utf8'))
    datas["hash"] =m.hexdigest()




    #忽略插入数据
    cursor.execute("replace into httpsend (url,xflow_node_id,raw_data,hash) values(%s,%s,%s,%s)",(datas["url"],datas["xflow_node_id"],datas["raw_data"],datas["hash"]))
    print(params['xflow_node_id'])
    #更新控制表状态
    cursor.execute("update control set status=%s,end_time=%s where xflow_node_id=%s and task_version=%s ",(0,time.strftime('%Y-%m-%d %H:%M:%S', time.localtime()),params['xflow_node_id'],params['task_version']))

    if result.status_code != 200:
        return "发包失败"




if __name__ == '__main__':
    func()