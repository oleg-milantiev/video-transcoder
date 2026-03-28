#!/bin/sh

docker exec -i develop-php-1 sh -c 'export XDEBUG_MODE=coverage && vendor/bin/phpunit tests/ --coverage-html var/cache/cover'
scp -r /root/video-transcoder/develop/symfony/var/cache/cover 192.168.2.5:/mnt/seagate/mo/tmp