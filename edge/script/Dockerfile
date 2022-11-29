FROM daxia/qingting:base_ubuntu_php_python

COPY ./sources.list  /etc/apt/sources.list
RUN  apt update -y
RUN apt install -y nmap masscan curl git vim
RUN curl -sSL https://get.daocloud.io/docker | sh

COPY ./code /root/code
RUN pip install -r /root/code/requirements.txt -i https://pypi.tuna.tsinghua.edu.cn/simple

WORKDIR /root/code

CMD ["python","/root/code/main.py"]