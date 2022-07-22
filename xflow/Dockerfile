FROM registry.cn-beijing.aliyuncs.com/daxia/qingting:xflow_base

COPY ./code /root/code
COPY ./tools /data/tools
RUN chmod 777 -R /data/tools

WORKDIR /root/code/

CMD ["php","/root/code/index.php"]


#docker build -t daxia/qingting:xflow_latest .