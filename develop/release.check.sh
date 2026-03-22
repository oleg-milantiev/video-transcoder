#!/bin/bash

set -eu
. ./.env

export PROJECT_NAME=relcheck_${PROJECT_VERSION//./_}_$(date +%s)
echo $PROJECT_NAME

ARTIFACTS_DIR=./release.check/${PROJECT_NAME}
mkdir -p "$ARTIFACTS_DIR"

cleanup() {
echo exit
  docker compose -p "$PROJECT_NAME" -f docker-compose.release.yml logs > "$ARTIFACTS_DIR/docker-compose.log" || true
  docker compose -p "$PROJECT_NAME" -f docker-compose.release.yml down -v || true
}

trap cleanup EXIT

# build + push
#cd /root/video-transcoder/develop/docker/yc-php && ./build.sh
#cd /root/video-transcoder/develop/docker/yc-ffmpeg && ./build.sh
#cd /root/video-transcoder/develop/docker/yc-nginx && ./build.sh

# release test stack up (without workers)
cd /root/video-transcoder/develop
PROJECT_VERSION=$PROJECT_VERSION docker compose \
  -p "$PROJECT_NAME" \
  -f docker-compose.release.yml \
  up -d --wait nginx php redis postgres playwright

# change uploads permission
docker compose -p "$PROJECT_NAME" -f docker-compose.release.yml exec -T php \
  chown -R www-data:www-data /var/www/yc/public

# migrations
docker compose -p "$PROJECT_NAME" -f docker-compose.release.yml exec -T php \
  php bin/console doctrine:migrations:migrate --no-interaction

# release test stack up (workers after migrations)
cd /root/video-transcoder/develop
PROJECT_VERSION=$PROJECT_VERSION docker compose \
  -p "$PROJECT_NAME" \
  -f docker-compose.release.yml \
  up -d --wait ffmpeg ffmpeg-transcode

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

docker compose -p "$PROJECT_NAME" -f docker-compose.release.yml cp php:/var/www/yc/var/log/dev.log "$ARTIFACTS_DIR/php.log" || true
docker compose -p "$PROJECT_NAME" -f docker-compose.release.yml cp ffmpeg:/var/www/yc/var/log/dev.log "$ARTIFACTS_DIR/ffmpeg.log" || true
docker compose -p "$PROJECT_NAME" -f docker-compose.release.yml cp ffmpeg-transcode:/var/www/yc/var/log/dev.log "$ARTIFACTS_DIR/ffmpeg-transcode.log" || true
