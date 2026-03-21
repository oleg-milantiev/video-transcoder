# E2E (Playwright)

This directory contains release smoke tests running against the release Docker Compose stack.

## What is covered

### `01.admin.login.js` - admin login and empty tabs smoke

- Opens start page and verifies `Sign in` is visible
- Logs in as admin from migration (`ADMIN_EMAIL` / `ADMIN_PASSWORD`)
- Verifies `Upload`, `Videos`, `Tasks` tabs after login
- Verifies Uppy dashboard is visible on `Upload`
- Verifies empty states in `Videos` (`No videos`) and `Tasks` (`No tasks`)
- Performs `Sign out` and verifies `Sign in` links are visible again
- Saves screenshots for each key step

### `02.upload.video.js` - upload video and verify details flow

- Logs in as admin and opens upload tab
- Uploads `2022_10_04_Two_Maxes.mp4` through Uppy file picker
- Verifies upload completion status in Uppy
- Opens `Videos` tab and checks uploaded row fields are filled
- Opens video details and verifies core fields (`Title`, `Extension`, `Status`, `Created At`, `User ID`)
- Waits for poster/meta readiness with retries and page reloads
- Performs `Sign out` and verifies `Sign in` links are visible again
- Saves screenshots for each key step

### `03.admin.crud.js` - admin area CRUD smoke

- Logs in as admin and verifies `Admin` link on main page
- Opens EasyAdmin and verifies sidebar sections (`Users`, `Tariffs`, `Videos`, `Presets`, `Tasks`)
- Verifies user `oleg@milantiev.com` exists in `Users`
- Verifies CRUD action availability/limitations per section
- Creates or updates preset `180p` (`320x180`, codec `h264`, bitrate `1.1` Mbps)
- Creates or updates tariff `Free` (`instance=1`, `delay=60`)
- Verifies uploaded video from test 02 exists in `Videos`
- Returns to main page, performs `Sign out`, and verifies `Sign in` links are visible again
- Saves screenshots for each key step

## Local run in release stack

```bash
cd /root/video-transcoder/develop
bash release.check.sh
```

Artifacts are saved under `develop/release.check/<PROJECT_NAME>/playwright`.
