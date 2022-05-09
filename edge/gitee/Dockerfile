FROM daxia/qingting:base_ubuntu_php_python

RUN pip3 install pymysql pyodps pandas pyyaml
COPY ./tools /data/tools
COPY ./code /root/code

RUN cd /root/code && pip3 install -r requirements.txt



CMD ["python3","/root/code/main.py"]


#docker build -t daxia/qingting:gitee_latest .