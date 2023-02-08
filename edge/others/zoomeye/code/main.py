#coding=utf-8
import requests
import os,time,json,base64
import threading
mutex = threading.Lock() 

# 获取多线程执行结果
class MyThread(threading.Thread):
    def __init__(self, func, args=()):
        super(MyThread, self).__init__()
        self.func = func
        self.args = args
    def run(self):
        time.sleep(2)
        self.result = self.func(*self.args)
    def get_result(self):
        threading.Thread.join(self)  # 等待线程执行完毕
        try:
            return self.result
        except Exception:
            return None

# 对目标进行搜索
def Search(apikey,url,page):
    dic={}
    headers = {
        'API-KEY' : apikey,
    }
    response = requests.get(url =url + str(page),
                        headers = headers)
  
    results=json.loads(response.text) 
    
    dic[url]=results
    return dic
    

# 输出结果处理
def del_results(lst):
    result=[]
    for i in lst:
        for j in i:
            result.append(j)

    return result


if __name__=="__main__":
    input_path="/data/share/input_{}.json".format(os.environ["xflow_node_id"])
    output_path="/data/share/output_{}.json".format(os.environ["xflow_node_id"])
    apikey = os.getenv('zoomeye_apikey')
    Domain_lst=[]
    with open(input_path,'r') as f:
        keyword_lst=json.load(f)
    for i in keyword_lst:
        keyword=i['keyword']
        url_lst=[
        'https://api.zoomeye.org/domain/search?q={}&facet=app,os&type=0&page='.format(keyword),
        'https://api.zoomeye.org/domain/search?q={}&facet=app,os&type=1&page='.format(keyword),
        ]
        page = 1
        for url in url_lst:
            t=MyThread(Search, (apikey, url,page))
            t.start()
            t.join()
            threading_result=t.get_result()
            for val in threading_result.keys():
                values=threading_result[val]['list']
                for i in values:
                    if len(i['ip']) > 0:
                        Domain_lst.append(i['ip'])

        Domain_lst=del_results(Domain_lst)
    with open(output_path,'w') as f:
        json.dump(Domain_lst,f)


        

