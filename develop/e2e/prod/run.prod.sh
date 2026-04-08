#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
E2E_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
DEVELOP_ROOT="$(cd "$E2E_ROOT/.." && pwd)"

cd "$DEVELOP_ROOT"

export BASE_URL="${BASE_URL:-http://nginx}"
export PROJECT_NAME="${PROJECT_NAME:-prod_$(date +%Y%m%d_%H%M)}"
export PROD_DATE_SUFFIX="${PROD_DATE_SUFFIX:-$(date +%Y%m%d)}"
export PROD_USER_LOCAL_PART="${PROD_USER_LOCAL_PART:-prod-${PROD_DATE_SUFFIX}}"
export PROD_USER_DOMAIN="${PROD_USER_DOMAIN:-example.test}"
export PROD_SOURCE_VIDEO="${PROD_SOURCE_VIDEO:-2022_10_04_Two_Maxes.mp4}"
export ADMIN_EMAIL="${ADMIN_EMAIL:-oleg@milantiev.com}"
export ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin}"
export E2E_ARTIFACTS_DIR="${E2E_ARTIFACTS_DIR:-$DEVELOP_ROOT/release.check/$PROJECT_NAME/playwright-prod}"
CONTAINER_ARTIFACTS_DIR="/work/release.check/$PROJECT_NAME/playwright-prod"

cleanup() {
  FILE="${E2E_ARTIFACTS_DIR}/test-results/.last-run.json"

  if [ -f "$FILE" ]; then
    cat "$FILE" | docker compose exec -T php bin/console app:smoke:result
  else
    echo "null" | docker compose exec -T php bin/console app:smoke:result
  fi
}

trap cleanup EXIT

if [[ -z "${PROD_USER_PASSWORD:-}" ]]; then
  PROD_USER_PASSWORD="$(printf '%04x%04x' "$RANDOM" "$RANDOM")"
  export PROD_USER_PASSWORD
fi

mkdir -p "$E2E_ARTIFACTS_DIR"

quote() {
  printf '%q' "$1"
}

PLAYWRIGHT_ARGS=""
for arg in "$@"; do
  PLAYWRIGHT_ARGS+=" $(quote "$arg")"
done

printf 'Running prod smoke\n'
printf '  BASE_URL=%s\n' "$BASE_URL"
printf '  PROJECT_NAME=%s\n' "$PROJECT_NAME"
printf '  PROD_DATE_SUFFIX=%s\n' "$PROD_DATE_SUFFIX"
printf '  PROD_USER_LOCAL_PART=%s\n' "$PROD_USER_LOCAL_PART"
printf '  E2E_ARTIFACTS_DIR=%s\n' "$E2E_ARTIFACTS_DIR"

docker compose exec -T playwright bash -lc "
  set -euo pipefail
  cd /work/e2e
  npm install --no-audit --no-fund
  BASE_URL=$(quote "$BASE_URL") \
  PROJECT_NAME=$(quote "$PROJECT_NAME") \
  E2E_ARTIFACTS_DIR=$(quote "$CONTAINER_ARTIFACTS_DIR") \
  ADMIN_EMAIL=$(quote "$ADMIN_EMAIL") \
  ADMIN_PASSWORD=$(quote "$ADMIN_PASSWORD") \
  PROD_DATE_SUFFIX=$(quote "$PROD_DATE_SUFFIX") \
  PROD_USER_LOCAL_PART=$(quote "$PROD_USER_LOCAL_PART") \
  PROD_USER_DOMAIN=$(quote "$PROD_USER_DOMAIN") \
  PROD_USER_PASSWORD=$(quote "$PROD_USER_PASSWORD") \
  PROD_SOURCE_VIDEO=$(quote "$PROD_SOURCE_VIDEO") \
  npx playwright test -c prod/playwright.config.js --project=chromium$PLAYWRIGHT_ARGS
"

