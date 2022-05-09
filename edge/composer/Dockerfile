FROM daxia/qingting:base_ubuntu_php_python

COPY ./code /root/code
COPY ./tools /data/tools
RUN apt update -y && apt install git php-mbstring -y

CMD ["php","/root/code/index.php"]


#docker build -t daxia/qingting:composer_latest .