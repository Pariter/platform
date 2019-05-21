#!/bin/sh

set -eu

cd `dirname $0`/../application

rm node_modules/ -rf

npm install --no-bin-links

ionic build --prod

