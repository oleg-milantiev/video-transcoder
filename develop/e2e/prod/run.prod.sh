#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
E2E_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
DEVELOP_ROOT="$(cd "$E2E_ROOT/.." && pwd)"

cd "$DEVELOP_ROOT"

export BASE_URL="${BASE_URL:-http://nginx}"
export PROJECT_NAME="${PROJECT_NAME:-prod_$(date +%Y%m%d_%H%M)}"
export PROD_SOURCE_VIDEO="${PROD_SOURCE_VIDEO:-2022_10_04_Two_Maxes.mp4}"
export E2E_ARTIFACTS_DIR="${E2E_ARTIFACTS_DIR:-$DEVELOP_ROOT/release.check/$PROJECT_NAME/playwright-prod}"
CONTAINER_ARTIFACTS_DIR="/work/release.check/$PROJECT_NAME/playwright-prod"

# Prepare two isolated test users via Symfony command
PREPARE_OUTPUT="$(docker compose exec -T php bin/console app:smoke:prepare)"
TEST_EMAIL_FREE="$(echo "$PREPARE_OUTPUT" | grep '^TEST_EMAIL_FREE=' | cut -d= -f2)"
TEST_PASSWORD_FREE="$(echo "$PREPARE_OUTPUT" | grep '^TEST_PASSWORD_FREE=' | cut -d= -f2)"
TEST_EMAIL_PREMIUM="$(echo "$PREPARE_OUTPUT" | grep '^TEST_EMAIL_PREMIUM=' | cut -d= -f2)"
TEST_PASSWORD_PREMIUM="$(echo "$PREPARE_OUTPUT" | grep '^TEST_PASSWORD_PREMIUM=' | cut -d= -f2)"

if [[ -z "$TEST_EMAIL_FREE" || -z "$TEST_PASSWORD_FREE" || -z "$TEST_EMAIL_PREMIUM" || -z "$TEST_PASSWORD_PREMIUM" ]]; then
  echo "Error: app:smoke:prepare did not return all required credentials" >&2
  exit 1
fi

export TEST_EMAIL_FREE TEST_PASSWORD_FREE TEST_EMAIL_PREMIUM TEST_PASSWORD_PREMIUM

cleanup() {
  FILE="${E2E_ARTIFACTS_DIR}/test-results/.last-run.json"

  if [ -f "$FILE" ]; then
    cat "$FILE" | docker compose exec -T php bin/console app:smoke:result
  else
    echo "null" | docker compose exec -T php bin/console app:smoke:result
  fi

  if [[ -n "${TEST_EMAIL_FREE:-}" && -n "${TEST_EMAIL_PREMIUM:-}" ]]; then
    docker compose exec -T php bin/console app:smoke:finish \
      --email="$TEST_EMAIL_FREE" \
      --email="$TEST_EMAIL_PREMIUM" || true
  fi
}

trap cleanup EXIT

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
printf '  TEST_EMAIL_FREE=%s\n' "$TEST_EMAIL_FREE"
printf '  TEST_EMAIL_PREMIUM=%s\n' "$TEST_EMAIL_PREMIUM"
printf '  E2E_ARTIFACTS_DIR=%s\n' "$E2E_ARTIFACTS_DIR"

docker compose run --rm playwright bash -lc "
  set -euo pipefail
  cd /work/e2e
  npm install --no-audit --no-fund
  BASE_URL=$(quote "$BASE_URL") \
  PROJECT_NAME=$(quote "$PROJECT_NAME") \
  E2E_ARTIFACTS_DIR=$(quote "$CONTAINER_ARTIFACTS_DIR") \
  PROD_SOURCE_VIDEO=$(quote "$PROD_SOURCE_VIDEO") \
  TEST_EMAIL_FREE=$(quote "$TEST_EMAIL_FREE") \
  TEST_PASSWORD_FREE=$(quote "$TEST_PASSWORD_FREE") \
  TEST_EMAIL_PREMIUM=$(quote "$TEST_EMAIL_PREMIUM") \
  TEST_PASSWORD_PREMIUM=$(quote "$TEST_PASSWORD_PREMIUM") \
  npx playwright test -c prod/playwright.config.js --project=chromium$PLAYWRIGHT_ARGS
"
