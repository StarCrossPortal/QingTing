FROM daxia/qingting:base_ubuntu_php_python

COPY ./code /root/code
RUN apt update -y && apt install git vim php-mbstring -y
WORKDIR /root/code
CMD ["php","/root/code/index.php"]


#docker build -t daxia/qingting:composer_latest .