FROM daxia/qingting:base_python3

COPY ./code /root/code
COPY ./tools /data/tools

COPY ./sources.list  /etc/apt/sources.list
RUN apt update -y

CMD ["php","/root/code/index.php"]


#docker build -t daxia/qingting:sqlmap_latest .