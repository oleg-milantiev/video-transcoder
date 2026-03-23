#!/bin/bash

# build + push
cd /root/video-transcoder/develop/docker/yc-php && ./build.sh
cd /root/video-transcoder/develop/docker/yc-ffmpeg && ./build.sh
cd /root/video-transcoder/develop/docker/yc-nginx && ./build.sh
