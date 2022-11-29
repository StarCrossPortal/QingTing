FROM daxia/qingting:base_ubuntu_php_python
COPY ./sources.list  /etc/apt/sources.list
RUN apt update -y && apt install -y wget vim &&  wget -q https://s.murphysec.com/install.sh -O - | /bin/bash && murphysec -v
COPY ./code /root/code
WORKDIR /root/code
CMD ["php","/root/code/index.php"]


#docker build -t daxia/qingting:semgrep_latest .