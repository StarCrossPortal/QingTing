FROM daxia/qingting:base_ubuntu_php_python

COPY ./code /root/code
COPY ./tools /data/tools
WORKDIR /data/tools/dirmap
RUN python3 -m pip install -r requirement.txt

CMD ["php","/root/code/index.php"]


#docker build -t daxia/qingting:dismap_latest .