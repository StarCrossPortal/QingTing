FROM daxia/qingting:base_ubuntu_php_python

RUN apt update -y && apt install -y git
COPY ./code /root/code
COPY ./tools /data/tools


CMD ["php","/root/code/index.php"]


#docker build -t daxia/qingting:hema_latest .