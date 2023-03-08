FROM daxia/qingting:base_ubuntu_php_python

COPY ./code /root/code
RUN cd /root/code && pip3 install -r requirements.txt
WORKDIR /root/code

CMD ["python3","/root/code/main.py"]


#docker build -t daxia/qingting:gitee_latest .