#!/bin/sh

. ../../.env

GIT_REPOSITORY_URL=${GIT_REPOSITORY_URL:-$(git -C ../.. config --get remote.origin.url)}

if [ -z "$GIT_REPOSITORY_URL" ]; then
    echo "GIT_REPOSITORY_URL is not set and remote.origin.url is empty"
    exit 1
fi

docker build \
	--target=prod \
	--build-arg PROJECT_VERSION=${PROJECT_VERSION} \
	--build-arg GIT_REPOSITORY_URL=${GIT_REPOSITORY_URL} \
	-t olegmilantiev/yc-ffmpeg:${PROJECT_VERSION} . && \
docker push olegmilantiev/yc-ffmpeg:${PROJECT_VERSION}
