#!/bin/sh

. ../../.env

docker build -t olegmilantiev/yc-php:${PROJECT_VERSION} . && docker push olegmilantiev/yc-php:${PROJECT_VERSION}
