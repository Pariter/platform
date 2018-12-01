#!/bin/sh

ROOT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )"

cd $ROOT_DIR/php
docker build . && docker tag `docker images -q|head -n1` `cat RELEASE` &&  docker push `cat RELEASE`

cd $ROOT_DIR/phalcon-php-fpm
docker build . && docker tag `docker images -q|head -n1` `cat RELEASE` &&  docker push `cat RELEASE`