FROM daxia/qingting:base_ubuntu_php_python

COPY ./code /root/code
COPY ./tools /data/tools
RUN chmod 777 -R /data/tools
COPY ./sources.list  /etc/apt/sources.list
RUN  apt update -y &&  apt install php-curl git -y  php-mbstring
RUN cd /data/tools/OneForAll/ && pip install -r requirements.txt -i https://mirrors.aliyun.com/pypi/simple/
ENTRYPOINT ["/usr/bin/env"]
CMD ["php","/root/code/index.php"]


#docker build -t daxia/qingting:semgrep_latest .


