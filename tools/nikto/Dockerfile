FROM daxia/qingting:base_ubuntu_php_python

COPY ./code /root/code
COPY ./sources.list  /etc/apt/sources.list
RUN apt update -y &&  apt-get install nikto vim -y
CMD ["python3","/root/code/main.py"]


#docker build -t daxia/qingting:semgrep_latest .