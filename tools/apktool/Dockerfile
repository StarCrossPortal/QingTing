FROM cincan/apktool:dev
USER root
COPY ./code /root/code
COPY ./tools /data/tools
COPY ./sources.list  /etc/apt/sources.list
RUN cat /etc/os-release && apk add  php-mbstring
RUN apt-get install openjdk-8-jdk
RUN chmod +x /data/tools/apktool
RUN chmod +x /data/tools/apktool.jar
ENTRYPOINT /usr/bin/env

CMD ["php","/root/code/index.php"]


#docker build -t daxia/qingting:semgrep_latest .