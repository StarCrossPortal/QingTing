FROM registry.cn-beijing.aliyuncs.com/daxia/qingting:xflow_base

COPY ./code /root/code
COPY ./tools /data/tools
RUN chmod 777 -R /data/tools
COPY ./sources.list  /etc/apt/sources.list
#RUN  apt update -y
RUN pip install peewee  pymysql && pip install -U docker && pip install setproctitle




#CMD ["python","/root/code/sleep.py"]
CMD ["python","/root/code/main.py"]

#docker build -t daxia/qingting:clog_latest .