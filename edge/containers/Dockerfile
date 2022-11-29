FROM daxia/qingting:base_ubuntu_php_python

#COPY ./sources.list  /etc/apt/sources.list
RUN  apt update -y
RUN  apt install curl vim -y && curl -sSL https://get.daocloud.io/docker | sh
RUN apt install python3-pip
COPY ./code /root/code
RUN  pip install -r /root/code/requirements.txt -i https://pypi.doubanio.com/simple
WORKDIR /root/code
CMD ["python3","/root/code/main.py"]


#docker build -t daxia/qingting:semgrep_latest .