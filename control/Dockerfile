FROM daxia/qingting:base

COPY ./code /root/code
RUN rm -f /root/code/init_lock.txt && sed -i 's/dl-cdn.alpinelinux.org/mirrors.ustc.edu.cn/g' /etc/apk/repositories && apk update


CMD ["php","/root/code/safe.php"]


#docker build -t daxia/qingting:control_latest .