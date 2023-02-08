# 码云最新项目
import re, ssl
import requests
import os, json
import time
import urllib3

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)
# 全局取消证书验证
ssl._create_default_https_context = ssl._create_unverified_context

url = "https://gitee.com/explore/all?order=latest&page="
header = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.45 Safari/537.36 Edg/96.0.1054.29"}

data = {}
page = 1
num = 2
time.sleep(10)

result_lst = []

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
            result_lst.append({"url": http_url1, "star": star})
            j += 1
    time.sleep(3)

print(result_lst)
out_put_path = "/data/share/output_{}.json".format(os.environ["xflow_node_id"])
with open(out_put_path, 'w') as f1:
    json.dump(result_lst, f1)
