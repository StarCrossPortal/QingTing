FROM daxia/qingting:base_ubuntu_php_python

COPY ./code /root/code
COPY ./tools /data/tools
RUN cd /data/tools/vulmap && pip3 install -r requirements.txt -i https://pypi.tuna.tsinghua.edu.cn/simple


CMD ["php","/root/code/index.php"]


#docker build -t daxia/qingting:vulmap_latest .