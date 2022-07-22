FROM cincan/apktool:dev
USER root
COPY ./code /root/code
COPY ./tools /data/tools
COPY ./sources.list  /etc/apt/sources.list
RUN cat /etc/os-release && apk add  php-mbstring

ENTRYPOINT /usr/bin/env

CMD ["php","/root/code/index.php"]


#docker build -t daxia/qingting:semgrep_latest .