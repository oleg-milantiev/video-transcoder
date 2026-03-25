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
- Opens video details and verifies core fields (`Title`, `Extension`, `Created At`, `User ID`)
- Waits for async processing with up to 5 retries (5s delay + page reload)
- Verifies poster is rendered (loaded image, non-zero natural size)
- Verifies `Duration` meta field exists and is non-empty after processing
- Performs `Sign out` and verifies `Sign in` links are visible again
- Saves screenshots for each key step

### `03.admin.crud.js` - admin area CRUD smoke
 
1. Navigate to the site root and sign in as the admin account (environment: `ADMIN_EMAIL` / `ADMIN_PASSWORD`).
2. Open the EasyAdmin interface (`Admin` link) and verify the presence of sidebar sections: `Users`, `Tariffs`, `Videos`, `Presets`, `Tasks`, `Logs`.
3. Open `Users` and verify the admin user (`oleg@milantiev.com`) is present and that CRUD controls (New/Edit/Detail) are available where expected. Capture a screenshot.
4. Open `Presets` and ensure required presets exist: create or update
   - `180p` — width: 320, height: 180, codec: `h264`, bitrate: `1.1` Mbps
   - `4k UHD` — width: 3840, height: 2160, codec: `h264`, bitrate: `8.0` Mbps
   For each preset, the test creates it if missing and then opens edit to ensure values are persisted. Capture a screenshot per preset.
5. Open `Tariffs` and ensure tariffs exist and are configured:
   - Create/update `Free` (first set `delay=60`, then update to `delay=3600`, `instance=1`)
   - Create/update `Premium` (`delay=0`, `instance=2`)
   Capture screenshots after create/update steps.
6. Assign tariff `Free` to the admin user (`oleg@milantiev.com`) via `Users -> Edit` and confirm the assignment is visible in the users table. Capture a screenshot.
7. Open `Tasks` and verify the table is present. Locate the first task row and perform the admin-side delete flow for tasks:
   - Click `Mark deleted` on the first task and accept the confirmation dialog.
   - Re-open `Tasks` to refresh the index and verify the first task row is styled as deleted (the row's video/title cell gets the `video-title-deleted` class — rendered as strikethrough) and that the `Mark deleted` action is no longer available for that task. Capture a screenshot.
8. Open `Videos` and verify the uploaded video from `02.upload.video.js` is present in the list. Open the video's details and verify core fields (Title, Extension, Created At, User ID). Capture a screenshot.
9. While on the `Videos` index (after verifying details), perform the admin-side delete flow for this video:
   - Click `Mark deleted` on the video row and accept the confirmation dialog.
   - Re-open `Videos` and verify the video row shows the deleted style (`video-title-deleted`) and that the `Mark deleted` action is no longer available. Capture a screenshot.
10. Re-open `Tasks` (again) and perform final consistency checks:
	- Ensure task rows reflect the deleted state (strikethrough) and that no `Mark deleted` actions remain for listed tasks.
	- Confirm the Tasks list still behaves read-only for CRUD where expected (no New/Edit/Delete actions available in CRUD toolbar). Capture a screenshot.
11. Open `Logs` and verify the view is read-only (no New/Edit actions), that filter controls are visible, and that the logs table contains rows. Capture a screenshot.
12. Return to the main site, verify UI elements (`Upload`, `Sign out`) and perform `Sign out`. Verify `Sign in` links are visible again. Capture a final screenshot.

Throughout the flow the test saves screenshots for each key milestone and validates UI state changes (action availability, row styling, and persisted field values).

### `04.transcode.flow.js` - transcode, download and remove flow

- Logs in as admin
- Uploads the source fixture again under the `-04` suffix and operates on that uploaded video (uploads `2022_10_04_Two_Maxes-04.mp4`), then opens `Videos` and enters that video
- Verifies `Presets` table is visible on `Video Details`
- Verifies preset `180p` exists and starts transcoding via `Transcode`
- Verifies flash popup title `Transcoding started`
- Verifies task appears with status in `PENDING|PROCESSING|COMPLETED`
- Polls the task status every ~6 seconds (no page reload), confirms progress increases, and waits for `COMPLETED`
- Verifies flash popup title `Transcoding completed`
- Verifies `Download` action is shown for completed task
- Clicks `Download` and validates successful endpoint response/redirect to `.mp4`
- Saves the resolved final `.mp4` URL to a local test variable
- Accepts browser download redirect behavior where Playwright may report download as `canceled`
- Returns to `Videos`, confirms delete popup (`Delete this video?`), and deletes the video
- Verifies deleted row state in list: title is styled as deleted and active `Delete` action is unavailable
- Opens video details and verifies deleted UI (`This video has been deleted`, deleted title, `DELETED` in presets, no actions)
- Requests previously saved `.mp4` URL and verifies `404`
- Performs `Sign out` and verifies `Sign in` links are visible again
- Saves screenshots for each key step

### `05.task.state.flow.js` - TASK_STATE_FLOW coverage for 4k cancel/restart

- Logs in as admin, then re-uploads fixture video as `2022_10_04_Two_Maxes-05.mp4`
- Opens `Video Details` for this `-05` video
- Uses long-running preset `4k UHD` and starts transcoding
- Waits until task appears in runtime states (`PENDING|PROCESSING|COMPLETED`)
- Polls progress (no page reload) and verifies at least one progress increase before cancellation
- Sends `Cancel` while task is `PROCESSING`
- Waits until state becomes `CANCELLED`
- Verifies cancelled row has no `Download` and exposes `Transcode` for restart
- Starts transcoding again from the same `4k UHD` row (restart path)
- Polls status/progress until `COMPLETED` and verifies progress increase during restarted run
- Verifies `Download` appears for completed task
- Performs `Sign out` and verifies `Sign in` links are visible again
- Saves screenshots for each milestone (`start`, `cancel`, `cancelled`, `restart completed`)

## Execution order

- `01.admin.login.js`
- `02.upload.video.js`
- `03.admin.crud.js`
- `04.transcode.flow.js`
- `05.task.state.flow.js`

## Data dependencies

- `02` uploads the original source video fixture used by `03` for admin checks.
- `03` ensures presets `180p` and `4k UHD`, tariffs (`Free`, `Premium`), and user tariff assignment (`Free`) are ready. Additionally, `03` performs admin-side `Mark deleted` actions on the first Task and on the specific Video and verifies UI changes.
- `04` uploads the source fixture a second time as `2022_10_04_Two_Maxes-04.mp4` and validates the full transcode + delete lifecycle for the `-04` video using preset `180p`.
- `05` re-uploads the source fixture as `2022_10_04_Two_Maxes-05.mp4` and uses `4k UHD` from `03` for long-running state-flow checks (progress/cancel/restart).

## Local run in release stack

```bash
cd /root/video-transcoder/develop
bash release.check.sh
```

Artifacts are saved under `develop/release.check/<PROJECT_NAME>/playwright`.
