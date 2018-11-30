#!/bin/sh

set -eu

case $1 in
	phalcon-php-fpm)
		echo "\e[32mUpdating composer\e[0m"
		cd /var/www/platform/composer
		sudo -u www-data composer install
		;;

	percona-xtradb-cluster)
		echo "Importing database"
		cd /var/www/platform/
		mysql -u root -ppassword PARITER < docker/database/PARITER.sql
		;;

	*)
		echo "Nothing to do?!"
esac

echo