FROM shmilylty/oneforall

COPY ./code /root/code
#COPY ./tools /data/tools

RUN apk add php php-pdo_mysql php-json php-ctype
RUN mkdir -p /data/tools && ln -s /OneForAll /data/tools/oneforall

ENTRYPOINT ["/usr/bin/env"]

CMD ["php","/root/code/index.php"]


#docker build -t daxia/qingting:oneforall_latest .