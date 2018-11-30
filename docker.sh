#!/bin/sh

set -eu

case "$1" in
start)
docker-compose up -d
echo -e "\e[32mRun post-install"
docker exec `docker container ls|grep dugwood/phalcon-php-fpm|awk '{print $1}'` sh /var/www/platform/docker/post-install.sh phalcon-php-fpm
docker exec `docker container ls|grep percona/percona-xtradb-cluster|awk '{print $1}'` sh /var/www/platform/docker/post-install.sh percona-xtradb-cluster
;;

stop)
docker-compose stop
;;

*)
echo "Nothing to do?!"
;;
esac