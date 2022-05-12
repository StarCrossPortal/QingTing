FROM daxia/qingting:base_ubuntu_php_python

COPY ./code /root/code
COPY ./tools /data/tools
RUN chmod 777 -R /data/tools
RUN cd /data/tools/##NAME## && apt update -y &&  ##INSTALL_CMD##

CMD ["php","/root/code/index.php"]


#docker build -t daxia/qingting:semgrep_latest .