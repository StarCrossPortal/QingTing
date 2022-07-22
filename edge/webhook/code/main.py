# -*- coding: UTF-8 -*-
import os,base64
import json
import time
import requests
import pymysql
import hashlib


db = pymysql.connect(host='mysql_addr',
                     user='root',
                     password='123',
                     database='edge',
                     port=3306,autocommit=True)

cursor = db.cursor()


def addlog(content, out = False):


    datetime = time.strftime('%Y-%m-%d %H:%M:%S', time.localtime())
    content =content if str(content) else json.loads(content)


def updateScanLog(toolName, targetName, Id):

    # 修改工具状态
    cursor.execute("replace into scan_log (tool_name,target_name,data_id) values(%s,%s,%s)",(toolName, targetName, Id))





def  main():
    #获取环境变量信息
    params = getParams()
    tableName = params['tableName']
    if "url" not in params:
        return "url参数不正确"
    url =params['url']
    xflow_id = params['xflow_node_id']

    # 读取目标表数据

    cursor.execute("select id,raw_data from %s where xflow_node_id=%s limit 3" ,(tableName,params['source_xflow_node_id']))
    lst=list(cursor.fetchall())

    # 遍历处理数据
    for item in lst:
        updateScanLog(xflow_id, tableName, item[0])
        sendDingDing(url, item,xflow_id)

    # 更新最后扫描的ID




    cursor.execute("update control set status=%s,end_time=%s where xflow_node_id=%s and task_version=%s",(0,time.strftime('%Y-%m-%d %H:%M:%S', time.localtime()),params['xflow_node_id'],params['task_version']))

    addlog("webhook执行完毕")



def request_by_curl(url, data_string,xflow_id):
    #从目标地址获取数据
    r=requests.post(url=url,data=data_string)
    result=r.text




    m = hashlib.md5()
    m.update(str(result).encode('utf8'))
    hash= m.hexdigest()
    cursor.execute("replace  into webhook(raw_data,hash,xflow_node_id) values(%s,%s,%s)",(result,hash,xflow_id))
    return r.status_code



def  sendDingDing(url, params,xflow_id):
    # 获取目的地址的数据结果
    result = request_by_curl(url, params[1],xflow_id)

    if result !=200 :
        addlog(["给用户发送消息通知失败", result,params], False)
    



def getParams():
    params=os.environ["params"]

    if params==None:
        addlog("readurl 没有获取到环境变量")
        return False
    return json.loads(base64.b64decode(params))

if __name__ == '__main__':
    main()