FROM registry.cn-beijing.aliyuncs.com/daxia/qingting:semgrep_base

#RUN pip3 install semgrep -i https://pypi.tuna.tsinghua.edu.cn/simple
#RUN apt update -y &&  apt install git -y

COPY ./code /root/code
COPY ./tools /data/tools

CMD ["php","/root/code/index.php"]


#docker build -t daxia/qingting:semgrep_latest .