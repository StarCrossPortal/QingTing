FROM daxia/qingting:base_ubuntu_php_python

COPY ./code /root/code
COPY ./tools /data/tools
RUN chmod 777 -R /data/tools
COPY ./sources.list  /etc/apt/sources.list
RUN  apt update -y
RUN cd /data/tools/AppInfoScanner/ && pip install -r requirements.txt

CMD ["php","/root/code/index.php"]


#docker build -t daxia/qingting:semgrep_latest .