FROM daxia/qingting:base_ubuntu_php_python

COPY ./code /root/code
COPY ./tools /data/tools
RUN chmod 777 -R /data/tools
COPY ./sources.list  /etc/apt/sources.list
RUN cd /data/tools/PHP && apt update -y &&  apt install -y php-mbstring

CMD ["php","/root/code/index.php"]


#docker build -t daxia/qingting:semgrep_latest .