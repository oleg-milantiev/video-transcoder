#!/bin/bash

set -eu
. ./.env

export PROJECT_NAME=relcheck_${PROJECT_VERSION//./_}_$(date +%s)
echo $PROJECT_NAME

ARTIFACTS_DIR=./release.check/${PROJECT_NAME}
mkdir -p "$ARTIFACTS_DIR"

cleanup() {
  docker compose -p "$PROJECT_NAME" -f docker-compose.release.yml logs > "$ARTIFACTS_DIR/stack.log" || true
  docker compose -p "$PROJECT_NAME" -f docker-compose.release.yml down -v || true
}

trap cleanup EXIT

# build + push
#cd /root/video-transcoder/develop/docker/yc-php && ./build.sh
#cd /root/video-transcoder/develop/docker/yc-ffmpeg && ./build.sh
#cd /root/video-transcoder/develop/docker/yc-nginx && ./build.sh

# release test stack up
cd /root/video-transcoder/develop
PROJECT_VERSION=$PROJECT_VERSION docker compose \
  -p "$PROJECT_NAME" \
  -f docker-compose.release.yml \
  up -d --wait

# migrations
docker compose -p "$PROJECT_NAME" -f docker-compose.release.yml exec -T php \
  php bin/console doctrine:migrations:migrate --no-interaction

docker compose -p "$PROJECT_NAME" -f docker-compose.release.yml exec -T php \
  vendor/bin/phpunit tests/
docker compose -p "$PROJECT_NAME" -f docker-compose.release.yml exec -T php \
  composer stan

docker compose -p "$PROJECT_NAME" -f docker-compose.release.yml exec -T playwright bash -lc "
  cd /work/e2e
  npm install --no-audit --no-fund
  BASE_URL=http://nginx \
  PROJECT_NAME=$PROJECT_NAME \
  E2E_ARTIFACTS_DIR=/work/release.check/$PROJECT_NAME/playwright \
  ADMIN_EMAIL=oleg@milantiev.com \
  ADMIN_PASSWORD=admin \
  npx playwright test --project=chromium
"
