#!/bin/sh

set -eu

ROOT_DIR=`dirname "$0"`

. /etc/environment && export SERVER_ID SERVER_NETWORK && sh $ROOT_DIR/init.sh "pariter" || exit 1

cd $ROOT_DIR/../composer

echo "Updating composer core"
php composer.phar self-update

if [ "$SERVER_ID" = "99" ]; then
	echo "Update packages for development"
	php composer.phar update --optimize-autoloader --apcu-autoloader
	echo

	echo "HybridAuth fix for development"
	sed -i -e "s/.protocol . ._SERVER..HTTP_HOST..;/str_replace(':\/\/', ':\/\/dev.', \$protocol.\$_SERVER['HTTP_HOST']);/" vendor/hybridauth/hybridauth/hybridauth/Hybrid/Auth.php

	echo "Security check with sensiolabs/security-checker"
	php vendor/sensiolabs/security-checker/security-checker security:check composer.lock
	echo

	echo "Outdated packages"
	php composer.phar outdated --direct
else
	echo "Installation for production"
	php composer.phar install --no-dev --optimize-autoloader --apcu-autoloader
	echo
fi
