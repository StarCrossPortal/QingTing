import json
from multiprocessing.connection import Client
import sys,os
import base64
import random
import paho.mqtt.client as mqtt
import pymysql



def reConndb(host,user,password,port,database=None):
    try:
        db = pymysql.connect(
            host=host,
            user=user,
            password=password,
            port=int(port),
            autocommit=True,
            database=database,
            cursorclass=pymysql.cursors.DictCursor
        )

        return {'msg':'success',"db":db,'status':1}
    except:
        return {'msg':'failed','status':0}

def on_connect(client, userdata, flags, rc):
    print("Connected with result code: " + str(rc))
    if rc == 0:
        print('mqtt连接成功')
    else:
        print("mqtt连接失败")


def on_message(client, userdata, msg):
    data=json.loads(msg.payload)['data']
    xflow_node_id=json.loads(msg.payload)['xflow_node_id']
    connect_result=reConndb(data['host'],data['username'],data['password'],data['port'])
    Insert_cursors=reConndb(str(flow_id)+'_mysql_addr','root','123','3306','edge')
    cursor=Insert_cursors['db'].cursor()
    datas={'msg':connect_result['msg'],'flow_id':flow_id,'xflow_node_id':xflow_node_id}
    cursor.execute("insert into mqtt_message (topic,data,usce_id) values(%s,%s,%s)",('db_testconnect',json.dumps(datas),flow_id))
    


if __name__ == '__main__':
    flow_id=sys.argv[1]
    Insert_cursors=reConndb(str(flow_id)+'_mysql_addr','root','123','3306','edge')
    cursor=Insert_cursors['db'].cursor()
    task_version=json.loads(base64.b64decode(os.environ["params"]))['task_version']
    datas={'msg':'success','flow_id':flow_id,'task_version':task_version}
    topics='db_testconnect'+task_version
    cursor.execute("insert into mqtt_message (topic,data,usce_id) values(%s,%s,%s)",(topics,json.dumps(datas),flow_id))
    server = '49.232.77.154'
    port = 1883
    ClientId = random.randint(100, 999)
    client = mqtt.Client()
    client.on_connect = on_connect
    client.on_message = on_message
    client.connect(server, port, ClientId)
    client.subscribe('db_testconnect'+'_'+flow_id, qos=2)
    client.loop_forever()  # 保持连接
