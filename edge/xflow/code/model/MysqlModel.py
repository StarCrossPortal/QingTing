import json, pymysql, base64, os


def singleton(cls):
    _instance = {}
    def _singleton(*args, **kwargs):
        table = args[0]
        if table not in _instance:
            _instance[table] = cls(*args, **kwargs)  # �~H~[建�~@个对象,并�~]�~X�~H��~W�~E��~S中
        return _instance[table]

    return _singleton


@singleton
class MysqlModel:
    db_table_field = {}
    table = ''
    cursor = object
    limit = 10
    order = None
    where = {}
    obj ={}

    def __init__(self, table):  # 自定义构造方法
        self.db_table_field = {}
        self.table = table
        self.cursor = self.reConndb().cursor()
        self.db_table_field[table] = self.get_table_filed()
        self.limit = 10
        self.order = None
        self.where = {}

    def reConndb(self):
        flow_id = json.loads(base64.b64decode(os.environ["params"]))['xflow_id']
        try:
            db = pymysql.connect(host=str(flow_id) + '_mysql_addr',
                                 user='root',
                                 password='123',
                                 database='edge',
                                 port=3306,
                                 autocommit=True,
                                 cursorclass=pymysql.cursors.DictCursor
                                 )

            return db
        except:
            print("数据库连接失败")

    def filter(self, raw_data):
        data = {}
        for key, value in raw_data.items():
            if key in self.db_table_field[self.table]:
                data[key] = value
        return data

    def insert_data_one(self, raw_data,where={}):
        self.setWhere(where)
        # 分析调试代码
        # print("表名称:", self.table)
        # print("表字段:", self.db_table_field[self.table])
        # print("表数据:", raw_data.items())
        # 过滤非必要字段
        data = self.filter(raw_data)
        # 字段名拼接
        cols = ", ".join("{}".format(k) for k in data.keys())  # 字段名拼接
        cols = "(" + cols + ")"
        val_cols = ", ".join("{}".format(str(json.dumps(k, ensure_ascii=False)))
                             for k in data.values())
        val_cols = "(" + val_cols + ")"
        res = self.cursor.execute("replace into {} {} values{}".format(self.table, cols, val_cols))
        self.clear()

        return res;
    def insertDataAll(self, data,where={}):
        if type(data) == list and len(data) > 0:
            for i in data:
                self.insert_data_one(i,where)

    def setLimit(self, limit):
        self.limit = limit
        return self

    def setOrder(self, order):
        self.order = order
        return self

    def find(self):
        sql = 'select * from {} {} {} LIMIT 0,1'.format(self.table, self.getWhere(), self.getOrder())
        # print(sql)
        try:
            # 执行SQL语句
            self.cursor.execute(sql)
            # 获取所有记录列表
            results = self.cursor.fetchall()
        except:
            results = []

        if len(results) == 0:
            return None
        self.clear()
        return results[0]

    def select(self):
        sql = 'select * from {} {} {} {}'.format(self.table, self.getWhere(), self.getOrder(), self.getLimit())
        results = []
        try:
            # 执行SQL语句
            self.cursor.execute(sql)
            # 获取所有记录列表
            results = self.cursor.fetchall()

        except pymysql.Error as e:
            print("SQL EXEC Error:", sql)
            print(e)
        self.clear()
        return results

    def delete(self):
        sql = 'delete {} {} '.format(self.table, self.getWhere())
        res = ''
        try:
            # 执行SQL语句
            res = self.cursor.execute(sql)

        except pymysql.Error as e:
            print("SQL EXEC Error:", sql)
            print(e)
        self.clear()
        return res

    def update(self, data):
        for key, value in data.items():
            temp = "{}='{}'".format(key, value)
            UpdateStr = "{}".format(temp)

        sql = 'update  {} set {} {} '.format(self.table, UpdateStr, self.getWhere())
        res = ''
        try:
            # 执行SQL语句
            res = self.cursor.execute(sql)

        except pymysql.Error as e:
            print("SQL EXEC Error:", sql)
            print(e)
        self.clear()
        return res

    def clear(self):
        self.limit = 10
        self.order = None
        self.where = {}

    def setTable(self, table):
        self.table = table

    def get_table_filed(self):
        if self.table in self.db_table_field.keys():
            return self.db_table_field[self.table]

        sql = "select * from " + self.table + " limit 1"
        result = self.cursor.execute(sql)
        desc = self.cursor.description
        fields = []
        for field in desc:
            fields.append(field[0])
        self.db_table_field[self.table] = fields

        return fields

    def getOrder(self):
        orderStr = ''
        if self.order is not None:
            orderStr = " order by {} ".format(self.order)

        return orderStr

    def getLimit(self):
        limitStr = " limit 0,{} ".format(self.limit)
        return limitStr

    def setWhere(self, whereDict):
        data = self.filter(whereDict)
        for key, value in data.items():
            self.where[key] = value
        return data

    def getWhere(self):
        whereStr = " 1=1 "
        if self.where:
            for key, value in self.where.items():
                if (type(value) == str):
                    temp = "{}='{}'".format(key, value)
                elif (type(value) == list):
                    temp = "{}{}'{}'".format(key, value[0], value[1])
                whereStr = "{} and {}".format(whereStr, temp)
        else:
            return ""
        self.where = {}
        return "where {}".format(whereStr)
