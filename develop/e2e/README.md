# E2E (Playwright)

This directory contains release smoke tests running against the release Docker Compose stack.

## What is covered

- Open start page and verify `Sign in`
- Login as admin from migration
- Verify `Upload`, `Videos`, `Tasks` tabs
- Verify Uppy dashboard on upload tab
- Verify empty `videos` and `tasks` tables
- Save screenshots for each step (including successful runs)

## Local run in release stack

```bash
cd /root/video-transcoder/develop
PROJECT_VERSION="$PROJECT_VERSION" docker compose -f docker-compose.release.yml up -d --wait

docker compose -f docker-compose.release.yml exec -T playwright bash -lc "cd /work/e2e && npm install --no-audit --no-fund && BASE_URL=http://nginx PROJECT_NAME=local E2E_ARTIFACTS_DIR=/work/release.check/local/playwright npx playwright test --project=chromium"
```

Artifacts are saved under `develop/release.check/<PROJECT_NAME>/playwright`.

