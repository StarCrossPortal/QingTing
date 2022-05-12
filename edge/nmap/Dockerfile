FROM daxia/qingting:base_ubuntu_php_python

COPY ./code /root/code
COPY ./tools /data/tools

RUN cd /data/tools/nmap && apt update -y &&  apt install nmap -y

CMD ["php","/root/code/index.php"]


#docker build -t daxia/qingting:semgrep_latest .