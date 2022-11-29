FROM daxia/qingting:base_ubuntu_php_python

COPY ./code /root/code
COPY ./sources.list  /etc/apt/sources.list
RUN  apt update -y && apt install vim -y
RUN pip install -r /root/code/requirements.txt
WORKDIR /root/code

CMD ["python","/root/code/main.py"]