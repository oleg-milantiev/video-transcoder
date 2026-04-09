#!/bin/sh

BACKUP_DIR="./postgresql"
DB_SERVICE="postgres"
DB_USER="yc"
DB_NAME="yc"
KEEP_DAYS=7

FILENAME="$BACKUP_DIR/db_$(date +%Y%m%d).sql.gz"
docker compose exec -T $DB_SERVICE pg_dump -U $DB_USER -c $DB_NAME | gzip > "$FILENAME"
# zcat ./postgresql/backup_20240101.sql.gz | docker compose exec -i postgres psql -U yc -d yc

find "$BACKUP_DIR" -type f -name "*.sql.gz" -mtime +$KEEP_DAYS -exec rm {} \;

#####

cd ./e2e/prod && bash run.prod.sh

#####

set -e

BACKUP_BASE="./volumes"
COMPOSE_FILE="docker-compose.yml"
LOG_FILE="./volumes/backup.log"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log() {
    echo -e "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log_success() {
    log "${GREEN}✓ $1${NC}"
}

log_warning() {
    log "${YELLOW}⚠ $1${NC}"
}

log_error() {
    log "${RED}✗ $1${NC}"
}

get_volumes_from_compose() {
    docker compose config --volumes 2>/dev/null | grep -v "^#" | sort -u
}

get_volume_source() {
    local volume_name=$1
    local project_name=$(docker compose config | grep "^name" | awk '{print $2}')

    if docker volume inspect "$volume_name" &>/dev/null; then
        echo "$volume_name"
    elif docker volume inspect "${project_name}_${volume_name}" &>/dev/null; then
        echo "${project_name}_${volume_name}"
    else
        echo ""
    fi
}

backup_volume() {
    local volume_name=$1
    local full_volume_name=$(get_volume_source "$volume_name")

    if [ -z "$full_volume_name" ]; then
        log_error "Volume $volume_name not found in Docker"
        return 1
    fi

    local backup_path="${BACKUP_BASE}/${volume_name}"
    local temp_container="backup-$(date +%s)-${RANDOM}"

    log "Backup volume: $volume_name -> $backup_path"
    mkdir -p "$backup_path"

    docker run --rm \
        --name "$temp_container" \
        -v "${full_volume_name}:/source:ro" \
        -v "$(pwd)/${BACKUP_BASE}:/backup" \
        alpine sh -c "
            apk add --no-cache rsync >/dev/null 2>&1
            mkdir -p /backup/${volume_name}
            rsync -av --delete /source/ /backup/${volume_name}/ 2>&1
        "

    if [ $? -eq 0 ]; then
        local size=$(du -sh "$backup_path" 2>/dev/null | cut -f1)
        log_success "Backup $volume_name finished (size: ${size:-0})"
        return 0
    else
        log_error "Backup $volume_name error"
        return 1
    fi
}

main() {
    log "========================================="
    log "Run Docker volumes backup"
    log "========================================="

    volumes=$(get_volumes_from_compose)
    if [ -z "$volumes" ]; then
        log_warning "No volumes found in docker-compose.yml"
        exit 0
    fi

    log "found volumes:"
    echo "$volumes" | while read v; do
        echo "  - $v"
    done

    # Счетчики
    total=0
    success=0
    failed=0

    while IFS= read -r volume; do
        [ -z "$volume" ] && continue
        total=$((total + 1))

        if backup_volume "$volume"; then
            success=$((success + 1))
        else
            failed=$((failed + 1))
        fi
        echo ""
    done <<< "$volumes"

    log "========================================="
    log "Backup finished"
    log "Total: $total, Success: $success, Failed: $failed"

    if [ $failed -eq 0 ]; then
        log_success "All volumes successfuly backuped"
    else
        log_error "Some backups has errors"
        exit 1
    fi
}

main
