FROM daxia/qingting:base_ubuntu_php_python

COPY ./code /root/code
COPY ./tools /data/tools
RUN chmod 777 -R /data/tools
COPY ./sources.list  /etc/apt/sources.list
RUN cd /root/code && apt update -y
RUN pip install -r /root/code/requirements.txt

CMD ["python","/root/code/sleep.py"]


#docker build -t daxia/qingting:semgrep_latest .