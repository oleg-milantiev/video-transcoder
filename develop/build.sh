#!/bin/bash

# build + push
docker exec -i develop-php-1 vendor/bin/phpunit tests/ && \
docker exec -i develop-php-1 composer stan && \
cd /root/video-transcoder/develop/docker/yc-php && ./build.sh && \
cd /root/video-transcoder/develop/docker/yc-ffmpeg && ./build.sh && \
cd /root/video-transcoder/develop/docker/yc-nginx && ./build.sh 
