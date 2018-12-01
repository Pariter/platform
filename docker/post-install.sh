#!/bin/sh

set -eu

DIR=/var/www/platform

case $1 in
	phalcon-php-fpm)
		echo "\e[32mUpdating composer\e[0m"
		cd $DIR/composer
		sudo -u www-data composer install
		cd $DIR/config
		if [ ! -f "config.dev.php" ]; then
			sudo -u www-data cp config.sample.php config.dev.php
			chmod a+rw config.dev.php
		fi
		if [ ! -f "providers.ini" ]; then
			sudo -u www-data touch providers.ini
			chmod a+rw providers.ini
		fi
		;;

	percona-xtradb-cluster)
		echo "Importing database"
		database="PARITER"
		cd $DIR
		# Wait for database to be available
		echo -n "Wait for database to be ready"
		for run in `seq 1 100`; do
			echo -n ". "
			status=`mysql -u root -ppassword "$database" -e "SHOW TABLES" 2>/dev/null || true`
			if [ "$status" != "" ] && [ -z "${status##*Tables_in_$database*}" ]; then
				echo "started!"
				break;
			fi
			sleep 2
		done
		mysql -u root -ppassword "$database" < docker/database/$database.sql
		;;

	*)
		echo "Nothing to do?!"
esac

echo