#!/bin/sh

. ../../.env

docker build --build-arg PROJECT_VERSION=${PROJECT_VERSION} -t olegmilantiev/yc-ffmpeg:${PROJECT_VERSION} . && \
    docker push olegmilantiev/yc-ffmpeg:${PROJECT_VERSION}
