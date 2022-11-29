import requests
import os,time,json,base64
from urllib import request
import logging








#获取token值
def get_token(nessus_url, username, password):
    url = "https://{nessus_url}/session".format(nessus_url=nessus_url)
    post_data = {
        "username": username,
        "password": password
    }
    header = {
            "Content-Type":"application/json"
        }
    requests.packages.urllib3.disable_warnings()
    response = requests.post(url,headers=header, data=json.dumps(post_data),verify=False)
    if response.status_code == 200:
        data = json.loads(response.text)
        print("token",data["token"])
        return data["token"]




# 获取自定义策略模板
def get_nessus_template_uuid(nessus_url, header,template_name ):
   
    api = "https://{nessus_url}/editor/scan/templates".format(nessus_url=nessus_url)
    response = requests.get(api, headers=header, verify=False)
    templates = json.loads(response.text)['templates']
    
    for template in templates:
        print(template['name'])
        if template['name'] == template_name:
            return template['uuid']
    return None



# 创建任务
def create_task(task_name, nessus_url, header,url,policy_id): # host 是一个列表，存放的是需要扫描的多台主机
    uuid = get_nessus_template_uuid(nessus_url, header, "basic") # 获取自定义策略的uuid
   
    if uuid is None:
        return False

    data = {"uuid": uuid, "settings": {
        "name": task_name,
        "policy_id": policy_id,
        "enabled": True,
        "text_targets": url,
        "agent_group_id": []
    }}
    header["Accept"]='text/plain'
    api = "https://{nessus_url}/scans".format(nessus_url=nessus_url)
    response = requests.post(api, headers=header, data=json.dumps(data, ensure_ascii=False).encode("utf-8"),verify=False)
    print(response)
    if response.status_code == 200:
        data = json.loads(response.text)
        if data["scan"] is not None:

            scan = data["scan"]
            # 返回任务id
            return scan["id"]  


#开启扫描任务
def start_task(scan_id,nessus_url,header):
    api = "https://{nessus_url}/scans/{scan_id}/launch".format(nessus_url=nessus_url, scan_id=scan_id)
    response = requests.post(api, verify=False, headers=header)
    if response.status_code != 200:
        return False
    else:
        return True


# 获取详细信息
def get_vuln_detail(scan_id, host_id, plugin_id,nessus_url,header):
    

    api = "https://{nessus_url}/scans/{scan_id}/hosts/{host_id}/plugins/{plugin_id}".format(nessus_url=nessus_url, scan_id=scan_id, host_id=host_id, plugin_id=plugin_id)
    response = requests.get(api, headers=header, verify=False)
    data = json.loads(response.text)
    outputs = data["outputs"]
    info=data["info"]
    return info



#获取主机id和任务id
def get_host_vulnerabilities(scan_id, host_id,nessus_url,header):
   
    api = "https://{nessus_url}/scans/{scan_id}/hosts/{host_id}".format(nessus_url=nessus_url, scan_id=scan_id, host_id=host_id)
    response = requests.get(api, headers=header, verify=False)
    if response.status_code != 200:
        return 2, "Data Error"

    data = json.loads(response.text)
    vulns = data["vulnerabilities"]
    for vuln in vulns:
        vuln_name = vuln["plugin_name"]
        plugin_id = vuln["plugin_id"] #插件id，可以获取更详细信息，包括插件自身信息和扫描到漏洞的解决方案等信息
        result=get_vuln_detail(scan_id, host_id, plugin_id,nessus_url,header)
        return result
        
     



# 获取扫描结果
def get_task_status(scan_id,nessus_url,header,output_path):
    
    api = "https://{nessus_url}/scans/{task_id}".format(nessus_url=nessus_url,
                                                       task_id=scan_id)
    response = requests.get(api, headers=header, verify=False)
    if response.status_code != 200:
        return 2, "Data Error"

    data = json.loads(response.text)
    
    hosts = data["hosts"]
    
    for host in hosts:
        result=get_host_vulnerabilities(scan_id, host["host_id"],nessus_url,header) 
        if result:
            raw_data=result['plugindescription']
            with open(output_path,'w') as f:
                json.dump(scan_status,f)
    if data["info"]["status"] == "completed" or data["info"]["status"] =='canceled':
        # 已完成,此时更新本地任务状态
        return {'status':1,"msg":'ok'}

# 获取详细信息
def get_vuln_detail(scan_id, host_id, plugin_id,nessus_url,header):
    

    api = "https://{nessus_url}/scans/{scan_id}/hosts/{host_id}/plugins/{plugin_id}".format(nessus_url=nessus_url, scan_id=scan_id, host_id=host_id, plugin_id=plugin_id)
    response = requests.get(api, headers=header, verify=False)
    data = json.loads(response.text)
    outputs = data["outputs"]
    info=data["info"]
    return info

    





def Get_target_Id(url,nessus_url,headers,task_name):
    appId_lst=[]
    #判断目标是否已经创建
    with open('./nessus.json','r') as f:
        appInfo_lst=json.loads(f)
    if len(appInfo_lst)>0:
        return appInfo_lst[0]
    
    #如果没有创建任务
    task_name="task_name"+'_'+str(task_name)
    scan_id=create_task(task_name,nessus_url, header,url,policy_id=None)
    
    if not scan_id:
        addlog("任务发送到nessus失败,请在容器内检查是否能访问到nessus服务地址{nessus_url}，以及key有效性~".format(nessus_url=nessus_url))
        return False
    appId_lst.append(scan_id)
    with open('./nessus.json','r') as f:
        appInfo_lst=json.loads(appId_lst)
    start_task(scan_id,nessus_url,header)
    return scan_id
    

def main():
    nessus_url=os.getenv('url')
    username=os.getenv('user')
    password=os.getenv('pwd')
    input_path='/data/share/input.json'
    output_path='/data/share/output.json'
    token=get_token(nessus_url,username,password)
    if token:
    # 构造请求头参数
        headers = {
                'X-Cookie': 'token={0}'.format(token),
                'Content-Type': 'application/json',
                'Accept-Encoding': 'gzip, deflate',
            }

    with open(input_path,'r') as f:
        targetArr=json.loads(f)


    for value in targetArr:
        task_name,url=value['task_name'],value['url']
        if "//" in url:
            url=url.split("//")[1]
            if ":" in url:
                url=url[:url.index(":")]
        targetId=Get_target_Id(url,nessus_url,headers,task_name)
        if not targetId:
            continue
        # 获取扫描状态
        scan_status=get_task_status(targetId,nessus_url,headers,output_path)





if __name__=="__main__":
    main()



