FROM daxia/qingting:base_ubuntu_php_python

COPY ./code /root/code
COPY ./tools /data/tools

RUN  apt update -y &&  apt install masscan   libpcap* -y

CMD ["php","/root/code/index.php"]


#docker build -t daxia/qingting:semgrep_latest .