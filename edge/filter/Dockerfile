FROM daxia/qingting:base_ubuntu_php_python

COPY ./sources.list  /etc/apt/sources.list
RUN  apt update -y &&  apt install -y php-mbstring vim
COPY ./code /root/code

CMD ["php","/root/code/index.php"]


#docker build -t daxia/qingting:semgrep_latest .