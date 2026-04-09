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
