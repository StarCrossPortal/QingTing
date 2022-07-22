# 码云最新项目

import re,ssl
import requests
import os
import time
import pymysql
import urllib3
from fun import db


urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)
# 全局取消证书验证
ssl._create_default_https_context = ssl._create_unverified_context

db = db()
cur = db.cursor(cursor=pymysql.cursors.DictCursor)

url = "https://gitee.com/explore/all?order=latest&page="
header = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.45 Safari/537.36 Edg/96.0.1054.29"}

data = {}
page = 1
num = 100
usce_id = os.environ["taskId"]

time.sleep(30)

# 使用 execute() 执行sql
# sql = "select * from control where ability_name = 'gitee' and status = 1"
# cur.execute(sql)
# # 使用 fetchall() 获取所有查询结果
# result = cur.fetchall()
# if (result):
#     print("开始 执行gitee")
# else:
#     time.sleep(10)
#     continue

while page <= num:
    req = requests.get(url + str(page), headers=header)
    page += 1
    if req.status_code == 200:
        star_reg = "<div class='explore-project__meta-social pull-right'>.*?<div class='stars-count' data-count=.*?>(.*?)</div>.*?</div>"
        star_list = re.findall(star_reg, req.text, re.M | re.I | re.S)

        reg = '<a title=".*?" target="_blank" class="title project-namespace-path" sa_evt="repoClick" sa_location="开源全部推荐项目" sa_url=".*?" sa_repo_id=".*?" href="(.*?)">(.*?)</a>'
        list = re.findall(reg, req.text, re.M | re.I | re.S)
        j = 0
        for i in list:
            name = i[0].split('/')[2]
            ssh_url = 'git@gitee.com:' + i[0][1:] + '.git'
            http_url1 = 'https://gitee.com' + i[0] + '.git'
            star = star_list[j]
            j += 1
            # 使用 execute() 执行sql
            sql = "select count(id) as num from target where url in('" + http_url1 + "')"
            cur.execute(sql)
            # 使用 fetchall() 获取所有查询结果
            result = cur.fetchall()
            if result[0]['num'] == 0:
                insert = 'insert into target(name,url,usce_id,star) value(%s,%s,%s,%s)'
                try:
                    cur.execute(insert, (name, http_url1,usce_id,star))
                    db.commit()
                except:
                    db.rollback()
    time.sleep(3)

# 修改结束时间
now_time = time.strftime("%Y-%m-%d %H:%M:%S", time.localtime())
sql = "UPDATE `control` SET `status` = 0,end_time=%s WHERE `ability_name` = 'gitee'"
cur.execute(sql,now_time)


print("执行 gitee 完毕")
time.sleep(3600*2)
# 关闭游标
cur.close()
# 关闭数据库连接
db.close()
