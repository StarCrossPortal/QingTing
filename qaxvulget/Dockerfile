FROM daxia/qingting:base_ubuntu_php_python

COPY ./code /root/code
COPY ./tools /data/tools
RUN chmod 777 -R /data/tools
COPY ./sources.list  /etc/apt/sources.list
RUN apt update -y
RUN apt install -y curl

WORKDIR /root/code
CMD ["php","/root/code/index.php"]


#docker build -t daxia/qingting:fofasearch_latest .