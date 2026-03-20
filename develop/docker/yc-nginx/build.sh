#!/bin/sh

set -eu
. ../../.env

docker build \
    --build-arg PROJECT_VERSION=${PROJECT_VERSION} \
    -t olegmilantiev/yc-nginx:${PROJECT_VERSION} \
    -f ./Dockerfile .

docker push olegmilantiev/yc-nginx:${PROJECT_VERSION}
