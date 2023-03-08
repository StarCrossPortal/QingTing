FROM daxia/qingting:base_ubuntu_php_python

COPY ./code /root/code
COPY ./sources.list  /etc/apt/sources.list
RUN apt update -y
RUN apt install -y curl vim

WORKDIR /root/code
CMD ["php","/root/code/index.php"]


#docker build -t daxia/qingting:fofasearch_latest .