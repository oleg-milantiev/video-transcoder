#!/bin/sh

docker exec -i develop-php-1 sh -c 'export XDEBUG_MODE=coverage && vendor/bin/phpunit tests/ --coverage-html var/cache/cover'
rsync -avr --delete /root/video-transcoder/develop/symfony/var/cache/cover/ 192.168.2.198:/mnt/goodwin/milantiev/www/oleg/oleg.milantiev.com/www/cover/