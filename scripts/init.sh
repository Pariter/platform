#!/bin/sh

# An error or missing variable will halt the script
set -eu

if [ "$SERVER_ID" = "" ]; then
	echo "Missing SERVER_ID"
	exit 1
fi

if [ -z "$SERVER_NETWORK" ] || [ "$SERVER_NETWORK" != "dugwood" ]; then
	echo "Wrong network"
	exit 1
fi

CHECK_USER=${1:-}

if [ ! -z "$CHECK_USER" ] && [ "`whoami`" != "$CHECK_USER" ]; then
	echo "Wrong user: should be run by $CHECK_USER"
	exit 1
fi

exit 0