#!/bin/bash

set -eu
. ./.env


RED='\033[1;31m'
GREEN='\033[1;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

CURRENT_VERSION=$(docker compose ps php --format json | jq -r '.Image' | sed 's/.*://')
NEW_VERSION="${PROJECT_VERSION}"

if [ -z "$NEW_VERSION" ]; then
    echo -e "${RED}Error: PROJECT_VERSION environment variable is not set${NC}"
    exit 1
fi

compare_versions() {
    local v1=$1
    local v2=$2

    if [ "$v1" = "$v2" ]; then
        echo "equal"
        return 0
    fi

    local IFS=.
    local i ver1=($v1) ver2=($v2)

    for ((i=0; i < ${#ver1[@]} || i < ${#ver2[@]}; i++)); do
        local n1=${ver1[i]:-0}
        local n2=${ver2[i]:-0}

        if ((10#$n1 > 10#$n2)); then
            echo "greater"
            return 0
        fi
        if ((10#$n1 < 10#$n2)); then
            echo "less"
            return 0
        fi
    done

    echo "equal"
}

echo -e "${YELLOW}=== Release Script Started ===${NC}"
echo -e "Current version: ${GREEN}${CURRENT_VERSION}${NC}"
echo -e "New version: ${GREEN}${NEW_VERSION}${NC}"

trap "echo -e \"${RED}Script interrupted or failed${NC}\"; exit 1" EXIT

RESULT=$(compare_versions "$NEW_VERSION" "$CURRENT_VERSION")

case "$RESULT" in
    "greater")
        # Release mode: NEW_VERSION > CURRENT_VERSION
        echo -e "${GREEN}=== Release Mode ===${NC}"

        echo "Enabling maintenance mode..."
        touch ./maintenance/enable

        echo "Pulling latest Docker images..."
        docker compose pull

        echo "Stopping PHP, FFmpeg, and FFmpeg-transcode services..."
        docker compose stop php ffmpeg ffmpeg-transcode || true

        echo "Starting PHP, FFmpeg, and FFmpeg-transcode services..."
        docker compose up -d php ffmpeg ffmpeg-transcode

        echo "Running database migrations..."
        docker compose exec php php bin/console doct:migr:migr --no-interaction

        echo "Clearing cache..."
        docker compose exec php php bin/console cache:clear

        echo "Running smoke tests..."
        docker compose exec php php bin/console app:smoke:prod

        echo "Stopping Nginx..."
        docker compose stop nginx

        echo "Starting Nginx..."
        docker compose up -d nginx

        echo -e "${GREEN}Release completed successfully!${NC}"
        ;;
    "less")
        # Rollback mode: NEW_VERSION < CURRENT_VERSION
        echo -e "${YELLOW}=== Rollback Mode ===${NC}"

        echo "Enabling maintenance mode..."
        touch ./maintenance/enable

        echo "Finding last migration for new version..."
        LAST_MIGRATION=$(docker run --rm olegmilantiev/yc-php:${NEW_VERSION} find /var/www/yc/migrations -name "Version*.php" -type f | sort | tail -1 | xargs basename | sed 's/\.php//')

        if [ -z "$LAST_MIGRATION" ]; then
            echo -e "${RED}Error: Could not find any migration file${NC}"
            rm -f ./maintenance/enable
            trap - EXIT
            exit 1
        fi

        echo "Last migration found: ${LAST_MIGRATION}"

        echo "Rolling back to migration: ${LAST_MIGRATION}..."
        docker compose exec php php bin/console doct:migr:migr DoctrineMigrations\\"${LAST_MIGRATION}" --no-interaction

        echo "Stopping PHP, FFmpeg, and FFmpeg-transcode services..."
        docker compose stop php ffmpeg ffmpeg-transcode || true

        echo "Starting PHP, FFmpeg, and FFmpeg-transcode services..."
        docker compose up -d php ffmpeg ffmpeg-transcode

        echo "Clearing cache..."
        docker compose exec php php bin/console cache:clear

        echo "Running smoke tests..."
        docker compose exec php php bin/console app:smoke:prod

        echo "Stopping Nginx..."
        docker compose stop nginx

        echo "Starting Nginx..."
        docker compose up -d nginx

        echo -e "${GREEN}Rollback completed successfully!${NC}"
        ;;
    "equal")
        echo -e "${YELLOW}Versions are identical, no action needed${NC}"
        trap - EXIT
        ;;
esac

trap - EXIT

echo "Disabling maintenance mode..."
rm -f ./maintenance/enable

echo -e "${GREEN}=== Release Script Finished ===${NC}"
echo "Deployment completed: ${CURRENT_VERSION} → ${NEW_VERSION}"
