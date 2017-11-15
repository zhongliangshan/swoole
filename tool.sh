#!/bin/bash
if [ "$1" -neq 'start' -o "$1" -neq 'restart' -o "$1" -neq 'stop' ];then
	echo "命令有误"
fi
php server.php -d -h 127.0.0.1 -p 9501 $1
