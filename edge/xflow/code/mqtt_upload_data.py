import os

import json, datetime
import sys, time, logging
import base64
import random
from model import MysqlModel
import pika
import time


# 将datetime类型数据变为str
def deal_datetime(item):
    for k, v in item.items():
        if type(v) == datetime.datetime:
            item[k] = str(v)
    return item


def publish(vision):
    tables = ['edge_data', 'contain_log', 'mqtt_message']
    Db = MysqlModel.MysqlModel('upload_log')
    while True:
        for tableName in tables:
            Db.setWhere({'ability_name': tableName})
            lastInfo = Db.find()
            if lastInfo == None:
                datas = {'ability_name': tableName, 'upload_last_id': 0}
                Db.insert_data_one(datas)
                lastId = 0
            else:
                lastId = lastInfo['upload_last_id']
            Db1 = MysqlModel.MysqlModel(tableName)
            Db1.setWhere({'id': ['>', lastId]})
            Db1.setLimit(50)
            result = Db1.select()
            if len(result) == 0:
                continue
            # if tableName!='log':
            print("开始上传{}表数据,起始Id:{}".format(tableName, lastId))
            tempId = 0
            data = {'table': tableName, 'token': vision[2], 'data': []}
            for item in result:
                tempId = item['id']
                del item['id']
                item['usce_id'] = vision[1]
                item = deal_datetime(item)
                data['data'].append(item)
            # 推送消息
            channel.basic_publish(exchange='',
                                  routing_key='upload_data',
                                  body=json.dumps(data),
                                  properties=pika.BasicProperties(delivery_mode=1)
                                  )
            # Db1 = MysqlModel.MysqlModel("upload_log")
            Db.setWhere({'ability_name': tableName})
            Db.update({'upload_last_id': tempId})
            time.sleep(0.5)


if __name__ == '__main__':
    User = os.getenv('rabbitmq_user')
    Pwd = os.getenv('rabbitmq_password')
    Ip = os.getenv('rabbitmq_host')
    Port = os.getenv('rabbitmq_port')
    vision = sys.argv
    user_info = pika.PlainCredentials(User, Pwd)  # 用户名和密码
    connection = pika.BlockingConnection(pika.ConnectionParameters(
        Ip, Port, '/', user_info))  # 连接服务器上的RabbitMQ服务
    channel = connection.channel()
    channel.queue_declare(queue='upload_data', durable=True)

    publish(vision)
    # 关闭连接
    # connection.close()
