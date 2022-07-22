import os
import docker
import base64
import json
import time, hashlib
import threading

mutex = threading.Lock()
import pymysql
import setproctitle


def reConndb():
    # 数据库连接重试功能和连接超时功能的DB连接
    _conn_status = True
    _max_retries_count = 20  # 设置最大重试次数
    _conn_retries_count = 0  # 初始重试次数
    _conn_timeout = 3  # 连接超时时间为3秒
    while _conn_status and _conn_retries_count <= _max_retries_count:
        try:
            print('连接数据库中..')
            db = pymysql.connect(host='mysql_addr',
                                 user='root',
                                 password='123',
                                 database='edge',
                                 port=3306, autocommit=True)

            _conn_status = False  # 如果conn成功则_status为设置为False
            print("连接数据库成功")
            return db
        except:
            _conn_retries_count += 1
            print('数据库连接失败!!')
            time.sleep(3)
            continue


def get_docker_id():
    """获取docker容器信息"""
    envs = os.environ["params"]
    xflow_id = json.loads(base64.b64decode(envs))["xflow_id"]

    # 获取当地dockre容器列表
    docker_container_lst = []
    client = docker.from_env()
    docker_container = client.containers.list()
    # 通过容器列表获取对应的容器id和name
    for containers in docker_container:

        if str(xflow_id) not in containers.name:
            continue
        docker_container_lst.append([containers.id, containers.name])

    return docker_container_lst, xflow_id


def get_log_info(container_id, cursor,xflow_id,xx):
    """ 获取容器日志 """

    position = 0
    # 拼接容器名称路径
    file_name = "/var/lib/docker/containers/" + str(container_id[0]) + "/" + str(container_id[0]) + "-json.log"
    if "-" in container_id[1]:
        container_id[1] = container_id[1].replace("-", "_")
    container_names = container_id[1].split("_")[1]
    # 从对应的容器获取日志内容
    try:
        with open(file_name, "r", encoding="utf-8") as f:
            while True:
                read = f.readline()

                if len(read) > 1:
                    # 对内容做hashmd5处理
                    m = hashlib.md5()
                    m.update(str(read).encode('utf8'))
                    hash = m.hexdigest()

                    try:
                        # 开启事务
                        mutex.acquire()

                        cursor.execute(
                            "replace into contain_log (xflow_id,contain_names,contain_id,info,hash) values(%s,%s,%s,%s,%s)",
                            (xflow_id, container_names, container_id[0], read, hash))
                        mutex.release()

                    except Exception as e:
                        mutex.release()
                        continue

                # 获取读取文件的当前指针
                cur_position = f.tell()

                if cur_position == position:
                    time.sleep(0.1)
                    continue
                else:
                    position = cur_position

    except Exception as e:
        print(e)


def set_therda_name(name):
    '''线程设置名字'''

    setproctitle.setproctitle(name)


def main_process(cursor):
    ''' 处理多线程'''
    process_list = []
    thread_name_lst = []
    if os.path.exists("./thread_name_lst.json"):
        with open("./thread_name_lst.json", "r", encoding="utf-8") as f2:

            read = f2.read()
            thread_name_lst=eval(read)

    container_ids = get_docker_id()[0]
    xflow_id = get_docker_id()[1]

    # 获取当前线程名


    # 开启多线程去处理各个容器日志
    for container_id in range(0, len(container_ids)):

        if container_ids[container_id][1] not in thread_name_lst:

            t1 = threading.Thread(target=get_log_info, args=(container_ids[container_id], cursor,xflow_id,container_ids[container_id][1]))
            #设置线程名
            set_therda_name(container_ids[container_id][1])

            proc_title_new = setproctitle.getproctitle()

            thread_name_lst.append(proc_title_new)
            process_list.append(t1)

        else:


            continue

    for s_process in process_list:
        s_process.start()


        # for j_process in process_list:
        #     j_process.join()

    with open("./thread_name_lst.json", "w", encoding="utf-8") as f1:
        f1.write(str(thread_name_lst))


if __name__ == "__main__":
    if os.path.exists("thread_name_lst.json"):
        os.system("rm -rf thread_name_lst.json")
    db = reConndb()
    cursor = db.cursor()
    while True:
        main_process(cursor)
