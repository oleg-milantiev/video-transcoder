#!/bin/sh

set -eu

. ../../.env

GIT_REPOSITORY_URL=${GIT_REPOSITORY_URL:-$(git -C ../.. config --get remote.origin.url)}

if [ -z "$GIT_REPOSITORY_URL" ]; then
	echo "GIT_REPOSITORY_URL is not set and remote.origin.url is empty"
	exit 1
fi

TMP_REPO_DIR="/tmp/yc-release-${PROJECT_VERSION}-$$"

cleanup() {
	rm -rf "$TMP_REPO_DIR"
}

trap cleanup EXIT INT TERM

git clone --depth 1 --branch "release/${PROJECT_VERSION}" "$GIT_REPOSITORY_URL" "$TMP_REPO_DIR"

docker build \
	--target prod \
	--build-arg REDIS_HOST="${REDIS_HOST}" \
	--build-arg REDIS_PORT="${REDIS_PORT}" \
	--build-arg DATABASE_URL="${DATABASE_URL}" \
	--build-arg DEFAULT_URI="${DEFAULT_URI}" \
	-t olegmilantiev/yc-php:${PROJECT_VERSION} \
	-f "$TMP_REPO_DIR/develop/docker/yc-php/Dockerfile" \
	"$TMP_REPO_DIR/develop" && docker push olegmilantiev/yc-php:${PROJECT_VERSION}
