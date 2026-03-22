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
- Waits for async processing with up to 5 retries (5s delay + page reload)
- Verifies poster is rendered (loaded image, non-zero natural size)
- Verifies `Duration` meta field exists and is non-empty after processing
- Performs `Sign out` and verifies `Sign in` links are visible again
- Saves screenshots for each key step

### `03.admin.crud.js` - admin area CRUD smoke

- Logs in as admin and verifies `Admin` link on main page
- Opens EasyAdmin and verifies sidebar sections (`Users`, `Tariffs`, `Videos`, `Presets`, `Tasks`, `Logs`)
- Verifies user `oleg@milantiev.com` exists in `Users`
- Verifies CRUD action availability/limitations per section
- Creates or updates preset `180p` (`320x180`, codec `h264`, bitrate `1.1` Mbps)
- Creates or updates preset `4k` (`3840x2160`, codec `h264`, bitrate `8.0` Mbps)
- Creates/updates tariff `Free` in two explicit steps: first `delay=60`, then updates to `delay=3600` (`instance=1`)
- Creates or updates tariff `Premium` (`instance=2`, `delay=0`)
- Assigns tariff `Free` to `oleg@milantiev.com`
- Verifies uploaded video from test 02 exists in `Videos`
- Verifies `Tasks` is read-only (`NEW`/`EDIT`/`DELETE` unavailable)
- Verifies `Logs` is read-only (`NEW`/`EDIT` unavailable), filters action is visible, and logs table is non-empty
- Returns to main page, performs `Sign out`, and verifies `Sign in` links are visible again
- Saves screenshots for each key step

### `04.transcode.flow.js` - transcode and download flow

- Logs in as admin
- Opens `Videos`, enters the previously uploaded video `2022_10_04_Two_Maxes.mp4`
- Verifies `Presets` table is visible on `Video Details`
- Verifies preset `180p` exists and starts transcoding via `Transcode`
- Verifies task appears with status in `PENDING|PROCESSING|COMPLETED`
- Polls and reloads page every 5 seconds, confirms progress increases, and waits for `COMPLETED`
- Verifies `Download` action is shown for completed task
- Clicks `Download` and validates successful endpoint response/redirect to `.mp4`
- Accepts browser download redirect behavior where Playwright may report download as `canceled`
- Performs `Sign out` and verifies `Sign in` links are visible again
- Saves screenshots for each key step

### `05.task.state.flow.js` - TASK_STATE_FLOW coverage

> Status: **TBD / not implemented yet**.

Goal: verify key runtime scenarios documented in `TASK_STATE_FLOW.md` on real UI/API behavior,
including long-running progress, cancellation, and restart.

Planned scope:

- Use long-running preset `4k` (`3840x2160`, `h264`, high bitrate) to keep task in `PROCESSING`
  long enough for stable progress sampling and cancellation checks
- Start transcode from `Video Details` for `4k` preset and confirm first transition
  `PENDING -> PROCESSING`
- Poll status/progress to confirm:
  - state is `PROCESSING`
  - progress is numeric and increases at least once before cancellation
- Trigger cancellation while task is still `PROCESSING` and verify transition to `CANCELLED`
- Verify no `Download` action is exposed for cancelled task
- Trigger transcode again for the same video+preset and verify restart path:
  - previous cancelled task is reused via domain restart flow (`CANCELLED -> PENDING`)
  - task goes again to `PROCESSING`
- Let second run complete and verify final transition to `COMPLETED`
- Verify `Download` becomes available only for completed state

Planned assertions mapped to `TASK_STATE_FLOW.md`:

- Runtime flow `Happy path`: `PENDING -> PROCESSING -> COMPLETED`
- Runtime flow `Cancel during processing`: `PROCESSING -> CANCELLED`
- Runtime flow `Retry path`: cancelled task restarted and completed on next attempt
- Runtime flow `Start blocked (no transition)`: keep as optional/advanced check (requires
  controlled invalid duration fixture)

Implementation notes for future test author:

- Keep `workers: 1` and preserve dependency chain with previous specs
- Prefer explicit waits/polling with reloads for status/progress stability (similar to `04`)
- Capture screenshots at state milestones (`PROCESSING`, `CANCELLED`, restart, `COMPLETED`)
- If timing is flaky, increase polling window only for this spec, not globally

## Execution order

- `01.admin.login.js`
- `02.upload.video.js`
- `03.admin.crud.js`
- `04.transcode.flow.js`
- `05.task.state.flow.js` (TBD)

## Data dependencies

- `02` uploads the source video used later by `03` and `04`
- `03` ensures presets `180p` and `4k`, tariffs (`Free`, `Premium`), and user tariff assignment (`Free`) are ready
- `04` uses data prepared by `03` and validates full transcode lifecycle for `180p`
- `05` (TBD) will use `4k` from `03` for long-running state-flow checks (progress/cancel/restart)

## Local run in release stack

```bash
cd /root/video-transcoder/develop
bash release.check.sh
```

Artifacts are saved under `develop/release.check/<PROJECT_NAME>/playwright`.
