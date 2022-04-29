FROM daxia/qingting:base

COPY ./code /root/code
RUN rm -f /root/code/init_lock.txt

CMD ["php","/root/code/safe.php"]


#docker build -t daxia/qingting:control_latest .