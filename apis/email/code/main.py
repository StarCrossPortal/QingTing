import json,base64,time,os
import logging
import smtplib
from email.mime.text import MIMEText
from email.utils import formataddr



# 发送飞书通知
def SendEmail(send_email,recev_email,password,smtpserver,message_text):
    try:
        msg = MIMEText(str(message_text), 'plain', 'utf-8')  # 邮件内容
        msg['Subject'] = "蜻蜓安全工作台提醒"  # 邮件的主题
        msg['send_email'] = formataddr(["tracy", send_email])  
        msg['recev_email'] = formataddr(["test", recev_email])  
        smtp = smtplib.SMTP_SSL(smtpserver, 465)  
        smtp.login(send_email, password)  
        smtp.sendmail(send_email,recev_email, msg.as_string()) 
        result="send Success"
        smtp.quit()
    except(smtplib.SMTPException) as e:
        result="send error"
    finally:
        return result



def main():


    recev_email = os.getenv('recev_email') #接收者邮箱
    send_email = os.getenv('send_email')  #发送者邮箱
    password = os.getenv('password')       #发送者邮箱为授权码
    input_path="/data/share/input_{}.json".format(os.environ["xflow_node_id"])
    output_path="/data/share/output_{}.json".format(os.environ["xflow_node_id"])


    if send_email.split("@")[1] =='qq.com':
        smtpserver = "smtp.qq.com"
    elif send_email.split("@")[1] =='163.com':
        smtpserver = "smtp.163.com"
    elif send_email.split("@")[1] =='gmail.com':
        smtpserver = "gmail.com"

    with open(input_path,'r') as f:
        value_lst=json.load(f)
    for i in value_lst:
        result=SendEmail(send_email,recev_email,password,smtpserver,i)
        with open(output_path) as f1:
            json.dump(result,f1)

if __name__ == '__main__':
    main()
