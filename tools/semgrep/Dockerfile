#FROM registry.cn-beijing.aliyuncs.com/daxia/qingting:semgrep_base
FROM daxia/qingting:base_ubuntu_php_python

RUN pip3 install semgrep -i https://pypi.tuna.tsinghua.edu.cn/simple
RUN apt update -y &&  apt install vim git -y

COPY ./code /root/code
CMD ["php","/root/code/index.php"]


#docker build -t daxia/qingting:semgrep_latest .