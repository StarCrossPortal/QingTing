FROM daxia/qingting:base_ubuntu_php_python

RUN apt update -y &&  apt install git php-xml php-mbstring -y
COPY ./code /root/code
COPY ./tools /data/tools
RUN chmod 777 -R /data/tools
COPY ./sources.list  /etc/apt/sources.list

WORKDIR /root/code

CMD ["php","/root/code/index.php"]


#docker build -t daxia/qingting:semgrep_latest .