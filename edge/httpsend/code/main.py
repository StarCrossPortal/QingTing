# coding=utf-8
from asyncio.log import logger
from urllib import response
import ssl
import requests, urllib3
import os, base64, json, re, time, logging
import hashlib
from requests.auth import HTTPProxyAuth

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)


# 获取环境变量参数
def getParams():
    inputFile = "/data/share/xflow_input_{}.json".format(os.environ["xflow_node_id"])
    with open(inputFile, 'r') as f1:
        params = json.load(f1)
    return params


def execExit(code, msg, data=[]):
    params = getParams()
    data = {"code": code, "data": data, "msg": msg, "params": params}
    outputFile = "/data/share/xflow_output_{}.json".format(params['xflow_node_id'])
    print("往{}写入数据:".format(outputFile), data)
    with open(outputFile, 'w') as f:
        json.dump(data, f, ensure_ascii=False)
    exit()


# 设置Tsl
def set_tsl(params):
    context = ssl.SSLContext(ssl.PROTOCOL_TLS)
    context.check_hostname = False
    text = params['certfile']
    current_path = os.getcwd()
    certifi_path = current_path + '/' + 'certfile.crt'
    with open(certifi_path, 'w') as f:
        f.write(text)
    context.load_cert_chain(certfile=certifi_path, keyfile=params['password'])
    context.verify_mode = ssl.CERT_REQUIRED
    return context


# 处理代理和代理认证
def set_proxies(params):
    proxies_dic = {}
    protocol = params['protocol']
    Server = params['Server']
    Port = params['Port']
    proxise = "{}".format(protocol) + "://" + Server + ":" + Port
    proxies_dic[protocol] = proxise
    if params['is_Auth']:
        username = params['username']
        passwd = params['password']
        auth = HTTPProxyAuth("username", "passwd")
        return proxies_dic, auth
    return proxies_dic


# 解析Http数据包
def del_http_package(content, params, allowredirect=True):
    try:
        data = ""
        headers = {}
        verify = False
        proxise = ''
        auth = ''
        context = ''
        method = 'get'
        timeout = 60
        allow_redirects = False
        if 'is_client_tsl' in params and params['is_client_tsl'] == 'true':
            context = set_tsl(params)
            verify = True
        # if 'is_ser_tsl' in params  and params['is_ser_tsl']=='true' :
        #     print('')
        if 'is_proxies' in params and params['is_proxies'] == 'true':
            proxise = set_proxies(params)
            if len(proxise) > 1:
                proxise = proxise[0]
                auth = proxise[1]
            proxise = proxise[0]
        if 'timeout' in params:
            timeout = int(params['timeout'])

        # 是否跳转
        if 'allow_redirects' in params and params['allow_redirects'] == 'true':
            allow_redirects = True

        if content != '':
            http_type = 'https' if params['is_https'] == 'true' else 'http'

            correct = True
            lines = content.strip().split('\n')
            for key, line in enumerate(lines):
                if key == 0:
                    tmp = line.strip().split()
                    if len(tmp) != 3:
                        print('请求报文格式错误')
                        execExit(2, "请求报文格式错误")
                        correct = False
                        break
                    method = tmp[0].lower()
                    request_params = tmp[1]
                elif "Host" in line:
                    url = lines[key].strip().split()[1]
                    if ' " ' in url:
                        url = url[1:-1]
                elif method == 'post' and key == len(lines) - 1:
                    data = line.strip()

                elif line:
                    tmp = line.strip().split(':')
                if len(tmp) < 2:
                    correct = False
                    print('headers error ' + line)
                    break
                tmp[1] = tmp[1].strip()
                headers[tmp[0].lower()] = ':'.join(tmp[1:])

            if correct:
                url = http_type + '://' + url + request_params
    except Exception as e:
        execExit(2, f"组件执行异常{e}")

    return {
        'url': url,
        'data': data,
        'verify': verify,
        'method': method,
        'headers': headers,
        'allow_redirects': allowredirect,
        'timeout': timeout,
        "proxies": proxise,
        "auth": auth,
        "cert": context,
        "allow_redirects": allow_redirects
    }


def func():
    try:
        data_list = []
        response_result = ""
        http_package = ""
        params = getParams()
        retyr_time = 1
        if 'retyr_time' in params:
            retyr_time = int(params['retyr_time'])

        if "package" not in params:
            print('未获取到请求报文')
            execExit(2, "未获取到请求报文")

        http_package = params["package"]

        data = del_http_package(http_package, params)
        response_result = requests.request(**data)
        response_result.headers["StatusCode"] = response_result.status_code
        if response_result.status_code != 200:
            while retyr_time >= 0:
                data = del_http_package(http_package, params)
                response_result = requests.request(**data)
                if response_result.status_code == 200:
                    retyr_time = 0
                    break
                retyr_time -= 1
        m = hashlib.md5()
        m.update(str(response_result.text).encode('utf8'))
        hashStr = m.hexdigest()
        data_list.append(json.dumps({"raw": response_result.text}, ensure_ascii=False))
        execExit(0, "", data_list)
    except Exception as e:
        execExit(2, f"{e}")


if __name__ == '__main__':
    func()
