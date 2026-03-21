# E2E (Playwright)

This directory contains release smoke tests running against the release Docker Compose stack.

## What is covered

Tests are designed to run sequentially (`workers: 1`) and build on data created by previous specs.

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
- Waits for async processing and verifies poster is not broken (`img.complete`, non-zero natural size)
- Verifies `Duration` meta field is present and non-empty after processing
- Performs `Sign out` and verifies `Sign in` links are visible again
- Saves screenshots for each key step

### `03.admin.crud.js` - admin area CRUD smoke

- Logs in as admin and verifies `Admin` link on main page
- Opens EasyAdmin and verifies sidebar sections (`Users`, `Tariffs`, `Videos`, `Presets`, `Tasks`)
- Verifies user `oleg@milantiev.com` exists in `Users`
- Verifies CRUD action availability/limitations per section
- Creates or updates preset `180p` (`320x180`, codec `h264`, bitrate `1.1` Mbps)
- Creates/updates tariff `Free` in two explicit steps: first `delay=60`, then updates to `delay=3600` (`instance=1`)
- Creates or updates tariff `Premium` (`instance=2`, `delay=0`)
- Assigns tariff `Free` to `oleg@milantiev.com`
- Verifies uploaded video from test 02 exists in `Videos`
- Returns to main page, performs `Sign out`, and verifies `Sign in` links are visible again
- Saves screenshots for each key step

### `04.transcode.flow.js` - transcode and download flow

- Logs in as admin
- Opens `Videos`, enters the previously uploaded video `2022_10_04_Two_Maxes.mp4`
- Verifies `Presets` table is visible on `Video Details`
- Verifies preset `180p` exists and starts transcoding via `Transcode`
- Verifies task appears for preset and tracks status/progress
- Reloads page every 5 seconds and checks progress/status until `COMPLETED`
- Verifies `Download` action is shown for completed task
- Clicks `Download` and validates successful download endpoint response/redirect to `.mp4`
- Performs `Sign out` and verifies `Sign in` links are visible again
- Saves screenshots for each key step

## Execution order

- `01.admin.login.js`
- `02.upload.video.js`
- `03.admin.crud.js`
- `04.transcode.flow.js`

## Data dependencies

- `02` uploads the source video used later by `03` and `04`
- `03` ensures preset `180p`, tariffs (`Free`, `Premium`), and user tariff assignment (`Free`) are ready
- `04` uses data prepared by `03` and validates full transcode lifecycle

## Local run in release stack

```bash
cd /root/video-transcoder/develop
bash release.check.sh
```

Artifacts are saved under `develop/release.check/<PROJECT_NAME>/playwright`.
