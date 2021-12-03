FROM php:7.4-cli
MAINTAINER tomh@uwm.edu
RUN apt-get update
RUN apt-get install -y cron mariadb-client
COPY ./.my.cnf /root/
COPY . /opt/sensmon
RUN crontab < /opt/sensmon/crontab
RUN docker-php-ext-install mysqli
RUN apt-get install -y ckermit

CMD [ "cron" , "-f" ]
