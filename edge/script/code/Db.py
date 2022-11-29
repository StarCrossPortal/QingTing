import pymysql,time
def reConndb(xflow_id):
    # 数据库连接重试功能和连接超时功能的DB连接
    _conn_status = True
    _max_retries_count = 20  # 设置最大重试次数
    _conn_retries_count = 0  # 初始重试次数
    _conn_timeout = 3  # 连接超时时间为3秒
    while _conn_status and _conn_retries_count <= _max_retries_count:
        try:
            print('连接数据库中..')
            db = pymysql.connect(host=str(xflow_id) + '_mysql_addr',
                                 user='root',
                                 password='123',
                                 database='edge',
                                 port=3306,
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