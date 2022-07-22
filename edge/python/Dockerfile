FROM daxia/qingting:base_ubuntu_php_python

COPY ./code /root/code
COPY ./tools /data/tools
RUN pip3 install python -i https://pypi.tuna.tsinghua.edu.cn/simple

CMD ["php","/root/code/index.php"]


#docker build -t daxia/qingting:python_latest .