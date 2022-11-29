import requests
import json,base64,time,os
import logging



# 发送飞书通知
def feishu(webhook_addr,message_text):
    header = {
        "Content-Type": "application/json;charset=UTF-8"
    }
    message_body = {
        "msg_type": "text",
        "content": {
            "text": "消息推送提示：蜻蜓安全工作台提醒\n" +'\n'+
                    ">>通知消息：%s \n" % str(message_text)
        }

    }

    response=requests.post(url=webhook_addr, json=message_body, headers=header)
    result=response.json()
    if result["StatusMessage"]=='success':
        print("发送飞书通知成功")
        return {'发送飞书通知成功'}
    else:
        print("发送飞书通知失败")
        return {'发送飞书通知失败'}






def main():
    input_path='/data/share/input.json'
    output_path='/data/share/output.json'
    webhook_addr =os.getenv('webhook_addr')
    with open(input_path,'r') as f:
        value_lst=json.load(f)
    result=feishu(webhook_addr,i)
    with open(output_path,'w') as f1:
        json.dump(result,f1)

    

    




if __name__ == '__main__':
    main()

