# E2E (Playwright)

This directory contains release smoke tests running against the release Docker Compose stack.

Осталось протестировать
- конструктор пресетов
  - соответствие выбора для Premium
- custom
  - много пресетов для Premium
  - зачёркнутые кнопки транскодера после их запуска
  - нерабочие кнопки в удалённом видео

## Helpers and test style

- Shared UI interactions and selectors are centralized in `helpers/`.
- Tests in `tests/` should read like scenario steps and avoid low-level locators.
- Prefer helper calls such as:
  - `loginAsAdmin(page)` / `loginAsTest(page)` instead of manual sign-in steps
  - `createOrUpdateTariffByTitle(page, 'Free', { delay: 3600, instance: 1, ... }, testInfo, 'screenshot.png')`
  - `openAdminSection(page, 'Users', '/admin/user')`
  - `assignTariffToUser(page, adminEmail, 'Free', testInfo)`

### Helper modules

- `helpers/auth.js` — login/logout helpers: `loginAsAdmin`, `loginAsTest`, `loginAs`, `logoutToPublic`, `getAdminCredentials`, `getTestCredentials`
- `helpers/mainApp.js` — Upload/Videos/Tasks tab navigation: `openUploadTab`, `openVideosTab`, `openTasksTab`, `expectTabsVisible`, `expectUploadDashboardVisible`, `expectVideosTableVisible`, `expectTasksTableVisible`, `expectEmptyVideos`, `expectEmptyTasks`, `videoRowByTitle`, `activeVideoRowByTitle`, `expectVideoRowHasCoreValues`
- `helpers/upload.js` — Uppy upload actions:
  - `uploadFixture(page, fileName)` — opens Upload tab and uploads by filename
  - `uploadFixtureAs(page, sourceFileName, uploadAsName, { expectedErrorText })` — unified helper: reads file, opens Upload tab, triggers upload; if `expectedErrorText` is set the upload is expected to fail and Videos tab is NOT opened; otherwise navigates to Videos tab after completion
  - `uploadFixtureAsName(page, sourceFileName, uploadAsName)` — wraps `uploadFixtureAs` (success path)
  - `uploadFixtureAsNameExpectingFailure(page, sourceFileName, uploadAsName, expectedErrorText)` — wraps `uploadFixtureAs` (failure path)
  - `expectUploadHintText(page, expectedText)` — checks the TariffHint text
- `helpers/video.js` — Video Details page, preset blocks, task-row helpers:
  - `waitForVideoDetailsVisible(page, { requirePresets })` — waits for `📄 Details` nav-link + optional preset h6 blocks
  - `expectDetailsValue(page, label)` — checks a `<dt>/<dd>` pair is non-empty
  - `renameVideoFromDetails(page, newTitle)` — opens SweetAlert2 rename modal and submits
  - `expectVideoDetailsTitle(page, expectedTitle)` — polls `<h6>` until it matches expected title
  - `clickBackButton(page)` — clicks the `Close` button (✕) in video details
  - `presetBlock(page, presetTitle)` — locates the `<h6>`-parent `div` for a preset
  - `tasksTable(page)` — locates `#transcoding-tasks-section table`
  - `taskRowByPreset(page, presetTitle)` — first task row matching presetTitle (any status)
  - `activeTaskRowByPreset(page, presetTitle)` — first non-CANCELLED row for preset
  - `taskRowByPresetAndHeight(page, presetTitle, height)` — row matching preset AND `{height}p`
  - `activeTaskRowByPresetAndHeight(page, presetTitle, height)` — non-CANCELLED row for preset+height
  - `taskRowByHeight(page, heightLabel)` — task row by height label only (e.g. `'1080p'`), no preset filter; useful for parallel tests where only height is known
  - `presetRow` — alias for `taskRowByPreset`
  - `readPresetTaskState(page, presetTitle, opts)` — reads `{ status, progress }` from task row (strips `?` icon suffix)
  - `readPresetTaskStateByHeight(page, presetTitle, height, opts)` — same but height-specific
  - `waitForPresetTaskStatus(page, presetTitle, targetStatus, { maxAttempts, pollMs, preferActive })` — polls until a single preset's task reaches `targetStatus` (throws on timeout)
  - `pollUntilCompletedWithProgressTracking(page, presetTitle, { maxAttempts, pollMs, preferActive })` — polls until `COMPLETED` while tracking that progress increases at least once; returns `{ completed, sawProgressIncrease }` — callers `expect()` these values
  - `waitForPosterAndMeta(page, testInfo, prefix)` — polls until poster `<img>` is fully loaded and `duration` meta is present (up to 5 retries × 5 s + page reload)
  - `getAllPresetTitles(page)` — returns all visible preset titles from `h6` blocks
  - `expectAllPresetsToShowTranscodeWithExpectedSize(page)` — checks each preset block shows buttons with `~X MB` size hints
  - `expectPresetStatusHelpIcon(page, presetTitle, { statusText, tooltipText })` — checks `?` help icon is visible in status cell with expected tooltip
  - `clickTranscodeForPreset(page, presetTitle)` — clicks the first enabled resolution button in a preset block
  - `expectPresetStatus(page, presetTitle, status)` — asserts task row has expected status
  - `waitForAllPresetsToComplete(page, presetTitles, maxAttempts, delayMs)` — polls until all preset titles reach COMPLETED
  - `waitForAllPresetsProcessingWithProgress(page, presetTitles, maxAttempts, pollMs)` — polls until all presets are PROCESSING with progress > 0
  - `waitForDeletedVideoDetailsWithoutPoster(page, expectedTitle, maxAttempts, delayMs)` — polls for deleted badge + absent poster in details
  - `verifyDeletedVideoInListAndDetails(page, testInfo, uploadedFileName, screenshotPrefix, { maxAttempts, pollMs })` — end-to-end deleted state check: polls the Videos list until the row shows `td.video-title-deleted` + "No poster", then opens Details and waits for deleted state without poster; takes screenshots `{screenshotPrefix}-list.png` and `{screenshotPrefix}-details.png`
  - `expectFlashPopupTitle(page, titleText, timeout)` — waits for `.app-flash-toast .app-flash-title`
- `helpers/admin.js` — EasyAdmin CRUD helpers: `openAdminSection`, `openAdminDashboardFromHome`, `ensureAdminMenuSectionsVisible`, `createOrUpdatePreset`, `createOrUpdateTariffByTitle`, `assignTariffToUser`, `createUserWithTariff`, `mainTableBodyForHeading`, `dismissVisibleAdminModal`, `dismissAllVisibleModals`
- `helpers/dialogs.js` — `clickAndAcceptConfirm(page, clickableLocator, expectedNativeMessagePart)` — handles both native `dialog` events and Bootstrap confirm buttons
- `helpers/download.js` — `clickDownloadAndVerifyMp4(page, row)` (clicks Download, verifies `< 400` status and `.mp4` URL), `expectDownloadFilename(page, filename)`, `expectRowDownloadFilename(row, filename)`
- `helpers/screenshot.js` — `shot(page, testInfo, name)` — consistent screenshot capture
- `helpers/constants.js` — `UI_TIMEOUT=8000`, `NAV_TIMEOUT=15000`, `UPLOAD_TIMEOUT=30000`
- `helpers/capture.js` — diagnostic helpers:
  - `attachSseMessages(page, testInfo)` — attaches `mercure-sse.json` from `window.__mercure_messages`; safe to call in `finally` (swallows all errors)

### Prod-only helpers (`prod/helpers/`)

- `prod/helpers/index.js` — re-exports all shared helpers + `loginAsCredentials`, `buildRunContext`
- `prod/helpers/admin.js` — `filterUsersByEmail`, `setTariffForFilteredUser`, `deleteUserByEmail`, `deleteFilteredUser` (isolated user management with filter panel)
- `prod/helpers/runContext.js` — `buildRunContext()` generates a per-run isolated user context (date-based email, random password, video name from env or defaults)

---

## Task table UI

The **Transcoding Tasks** table (HTML id `transcoding-tasks-section`) has these columns:

| # | Column | Notes |
|---|---|---|
| 0 | Preset | preset title |
| 1 | Resolution | `{height}p` (e.g. `1080p`) |
| 2 | Status | may have `?` icon suffix for PENDING tasks with tariff restrictions |
| 3 | Progress | `{N}%` or `-` |
| 4 | Created | formatted date |
| 5 | Actions | `Cancel` / `Download` / `Transcode` restart |

The `readPresetTaskState` helper strips the ` ?` suffix from column 2 before returning `status`.

After Cancel+Restart there may be **two rows** for the same preset: one CANCELLED and one new active row. `activeTaskRowByPreset` / `activeTaskRowByPresetAndHeight` filters out CANCELLED rows.

---

## Preset block UI

The **Start new Video Transcoding Task** section renders one `<div>` per preset:
- `<h6>` heading: `{presetTitle} ({videoCodec}/{audioCodec}/{format})`
- Row of `<button class="btn-outline-primary">` with `{width}x{height}` labels
- `~X MB` size hint under each button (calculated from bitrate × duration)
- Buttons filtered by `tariff.height`; buttons for already-existing tasks are struck-through and disabled

Section is **hidden** if `_width` or `_height` are absent in video meta.

---

## What is covered

Tests run sequentially (`workers: 1`) and build on data from previous specs.

### `01.admin.login.js` — admin login and empty-tabs smoke

- Opens root page, verifies `Sign in` link is visible
- Logs in as admin (`ADMIN_EMAIL` / `ADMIN_PASSWORD`)
- Verifies `Upload`, `Videos`, `Tasks` tab buttons after login
- Verifies Uppy dashboard is visible on `Upload` tab
- Verifies empty states: `No videos` and `No tasks`
- Signs out and verifies `Sign in` links reappear
- Screenshots: `01` → `07`

### `02.upload.video.js` — upload, details, rename, back-navigation

- Logs in as admin and opens Upload tab
- Uploads `2022_10_04_Two_Maxes.mp4` through Uppy file picker
- Opens `Videos` tab, finds the uploaded row, checks core values (title, date)
- Clicks the row → `Video Details` page
- Checks `Title` and `Created At` `<dt>/<dd>` pairs are non-empty
- Waits for poster image and `duration` meta (up to 5 retries × 5 s + page reload)
- Renames video via SweetAlert2 modal to `{baseName}-02`
- Verifies the renamed title in `Video Details` and in the `Videos` list row
- Signs out
- Screenshots: `01` → `08`

### `03.admin.crud.js` — admin area CRUD smoke

1. Sign in as admin → verify `Admin` link.
2. Open EasyAdmin → verify sidebar links: `Users`, `Tariffs`, `Videos`, `Presets`, `Tasks`, `Logs`.
3. Verify admin user `oleg@milantiev.com` is present in Users with New/Edit/Detail actions.
4. Ensure presets exist (create or update):
   - `180p` — h264/aac/mp4, bitrate `{"180": 1.1}` *(note: now called `Standard video Quality` in newer tests — see preset naming below)*
   - `FHD` — h264/aac/mp4, bitrate `{"1080": 6.0}`
5. Ensure tariffs exist and configure:
   - `Free` — `delay=60 → 3600`, `instance=1`, `videoDuration=3600`, `videoSize=100`, `maxWidth=1920`, `maxHeight=1080`, `storageGb=1`, `storageHour=24`
   - `Premium` — `delay=0`, `instance=2`, `videoDuration=86400`, `videoSize=1024`, `maxWidth=3840`, `maxHeight=2160`, `storageGb=100`, `storageHour=720`
6. Create test user `test@test.com` / `test` / `ROLE_USER` with tariff `Free`. Assign tariff `Free` to admin user.
7. **Videos**: verify uploaded video (`2022_10_04_Two_Maxes-02`) is listed, no `New` action. Mark it deleted via `Mark deleted` button + confirm dialog; verify row gets `data-deleted-row="1"` and `Mark deleted` action disappears.
8. **Tasks**: verify no `New`, `Edit`, or `Delete` actions in the admin task list.
9. **Logs**: verify read-only (no New/Edit), filter control visible, at least one log row present.
10. Sign out via `logoutToPublic`.
- Screenshots: `01` → `11`

> **Note**: The TODO sections (Tasks mark-deleted flow) are commented out — tasks don't exist yet at this step in the flow.

### `04.transcode.flow.js` — transcode, download, rename, delete lifecycle

Uses console capture + Mercure SSE attachment on failure.

- Logs in as `test@test.com`
- Uploads fixture as `2022_10_04_Two_Maxes-04.mp4`
- Opens video details → verifies preset block (`Standard video Quality`) is visible
- Clicks the **2nd** enabled resolution button (HD = 720p) to start transcoding
- Verifies flash popup: `Transcoding started`
- Polls task state (no page reload, realtime updates) until `PENDING|PROCESSING|COMPLETED`
- Confirms progress increases at least once during polling
- Waits for `COMPLETED`, verifies flash popup: `Transcoding completed`
- Verifies `Download` link in the task row
- Clicks Download → verifies `.mp4` response, saves resolved URL
- **Before rename**: verifies `download` attribute = `{baseName}-aac-h264-720p.mp4`
- Renames video via SweetAlert2 modal to `{baseName}-renamed`
- **After rename**: verifies `download` attribute updates to `{renamedBaseName}-aac-h264-720p.mp4`
- Goes to Videos list → `Delete` button + confirm (`Delete this video?`)
- Polls until row gets `td.video-title-deleted`; verifies `Delete` button is disabled/gone
- Opens deleted video details: verifies `This video has been deleted`, `dd.video-title-deleted`, `DELETED` status in tasks table, no action buttons/links in tasks tbody
- Requests previously saved `.mp4` URL → expects `404`
- Signs out
- Screenshots: `01` → `09`

### `05.task.state.flow.js` — cancel-in-PROCESSING + restart lifecycle

Uses console capture + Mercure SSE attachment on failure.

- Logs in as admin
- Assigns `Premium` tariff to admin via EasyAdmin
- Re-logs in (refreshes security token + tariff context)
- Uploads fixture as `2022_10_04_Two_Maxes-05.mp4`
- Opens video details → verifies preset block for `High video Quality, High Efficiency Audio` (h265/opus — slow codec)
- Clicks the **4th** enabled button (1280×720) to start transcoding
- Polls until `PENDING|PROCESSING|COMPLETED` appears in task row
- **Polls until PROCESSING** → sends `Cancel` button click mid-task
- Waits (up to 60 × 6 s = 6 min) until task reaches `CANCELLED`
- Verifies no `Download` link, `Transcode` restart button visible
- Clicks `Transcode` restart button
- Polls until new task reaches `PENDING|PROCESSING|COMPLETED` (with `preferActive: true`)
- Waits for `COMPLETED`; verifies progress increased during restarted run
- Verifies `Download` link visible
- Signs out
- Screenshots: `01` → `07`

### `06.multi.preset.flow.js` — multi-phase: Free → Premium tariff, single preset, download

Uses console capture + Mercure SSE attachment.

#### Phase 1 — test user, upload, pending state verification

- Login as `test@test.com` (Free tariff, `delay=3600`)
- Upload fixture as `2022_10_04_Two_Maxes-06.mp4`
- Open Videos tab → find row → verify details fields (Title, Created At)
- Wait for poster + meta
- Click `Transcode` **twice** on `Standard video Quality` (both tasks created as PENDING)
- Wait 3 s → verify both task rows show status `PENDING`
- Verify `?` help icon visible next to `PENDING` with tooltip `Why isn't my video transcoding?`
- Sign out

#### Phase 2 — admin upgrades tariff

- Login as admin → assign `Premium` tariff to `test@test.com`
- Sign out

#### Phase 3 — full transcode + download

- Login as `test@test.com` (now Premium)
- Find `-06` video, open details
- Click `Transcode` on `Standard video Quality` (triggers scheduler; dispatches `StartTaskScheduler` for all pending tasks)
- Poll until `Standard video Quality` reaches `COMPLETED` (up to 24 × 5 s = 2 min)
- Verify `Download` button visible
- Verify `download` attribute = `{baseName}-aac-h264-2160p.mp4`
- Click Download → verify `< 400` response and `.mp4` URL
- Sign out
- Screenshots: `01` → `20`

### `07.parallel.transcode.js` — parallel PROCESSING on Premium tariff (2 workers)

Uses console capture + Mercure SSE attachment.

- Login as `test@test.com` (Premium, `instance=2` — set by test 06)
- Upload fixture as `2022_10_04_Two_Maxes-07.mp4`
- Open video details → wait for poster + meta
- Click `1080p` button and `720p` button in `Standard video Quality` preset block
- **Parallel check**: poll every 1 s (up to 90 s) until **both** `1080p` and `720p` task rows show `PROCESSING` with `progress > 0` simultaneously — confirms two workers running in parallel
- Poll every 1 s (up to 120 s) until **both** reach `COMPLETED`
- Verify `Download` links for both rows
- Verify `download` attribute for each: `{baseName}-aac-h264-1080p.mp4` and `{baseName}-aac-h264-720p.mp4`
- Click Download for each, verify `.mp4` response
- Sign out
- Screenshots: `01` → `11`

### `08.tariff.js` — tariff restrictions: file size, storage, resolution, duration

Uses console capture + Mercure SSE attachment.

**Setup cleanup**: deletes the old `-05` video from test 05 (to free storage for fresh tests).

#### Phase 1 — baseline valid upload

- Login as admin → assign `Free` tariff → re-login (session refresh)
- Upload `2022_10_04_Two_Maxes-08-success.mp4`
- Verify row in Videos list, open details, verify title + poster + meta
- Verify all preset blocks show resolution buttons with `~X MB` size hints

#### Phase 2 — create tariff variants

Creates (or updates) 4 tariffs cloned from Free:

| Tariff | Changed field | Value |
|---|---|---|
| `Free-filesize` | `videoSize` | 3 MB |
| `Free-storage` | `storageGb` | 0.01 GB |
| `Free-resolution` | `maxWidth=320`, `maxHeight=180` | |
| `Free-duration` | `videoDuration` | 2 s |

#### Phase 3 — apply each tariff, verify behavior

For each tariff variant:
1. Assign to admin → re-login
2. Check upload hint text (if applicable)
3. Attempt upload:
   - **Free-filesize**: hint contains `3 MB`; upload rejected with `exceeds maximum allowed size`
   - **Free-storage**: hint contains `Storage is running low`; upload rejected with `exceeds maximum allowed size`
   - **Free-resolution**: hint contains `320`; upload completes → video becomes deleted + no poster (both in list and details)
   - **Free-duration**: upload completes → video becomes deleted + no poster

#### Phase 4 — low storage disables transcoding for existing valid video

- Re-assign `Free-storage` → re-login
- Open the `-08-success` video details → verify title is correct
- Sign out

---

## `prod/tests/01.prod.safe.js` — isolated production smoke

Self-contained test with automatic cleanup. Designed to run safely in production without side effects.
Timeout: **35 minutes**.

### Phases

#### Phase 1 — Admin creates isolated user

- Admin logs in
- Deletes any pre-existing isolated user with today's email (idempotent)
- Creates fresh user `{prod-YYYYMMDD}@example.test` with `Free` tariff
- Signs out

#### Phase 2 — Fresh user: upload + verify empty state

- Login as the isolated user
- Verify empty state in Videos, Tasks
- Verify upload hint `0 MB / 1 GB`
- Upload `2022_10_04_Two_Maxes.mp4` as `{userLocalPart}.mp4`
- Verify row in Videos, open details, wait for poster + meta
- Verify `Standard video Quality` preset block is visible
- Click `1080p` (first button) → wait 400 ms → click `720p` (next available button)

#### Phase 3 — Free tariff: 1080p starts, 720p waits PENDING

- Wait for `1080p` to reach `PROCESSING` with Cancel button visible
- Wait for `1080p` progress to exceed 30%
- Click `Cancel` on the `1080p` active task row
- Wait for `1080p` to reach `CANCELLED` with `Transcode` restart button
- Restart `1080p` from the CANCELLED task row
- Wait for `1080p` to reach `PENDING` (blocked by Free `instance=1` — 720p is occupying the slot)
- Verify `?` help icon with tooltip `Why isn't my video transcoding?` on the PENDING 1080p row
- Cancel both `1080p` and `720p` pending tasks
- Wait for both tasks to reach `CANCELLED` with Transcode restart button
- Sign out

#### Phase 4 — Admin upgrades to Premium

- Login as admin
- Filter users by isolated user's email prefix
- Set tariff to `Premium` for the filtered user
- Sign out

#### Phase 5 — Premium tariff: restart both tasks, download, delete

- Login as isolated user
- Open video details
- Click `Transcode` restart on CANCELLED `1080p` row
- Click `Transcode` restart on CANCELLED `720p` row
- Wait until both tasks are `PENDING|PROCESSING|STARTING` with Cancel buttons
- Wait until both tasks reach `COMPLETED` with Download links (up to 5 min)
- For each task (`1080p`, `720p`): verify `download` attribute contains height+`.mp4`, click Download, verify `< 400` response
- Delete the uploaded video from Videos list
- Sign out

#### Phase 6 — Admin deletes isolated user

- Login as admin
- Filter and delete the isolated user
- Sign out

**Cleanup in `finally`**: if video or user were not deleted during the test run, cleanup is attempted automatically.

---

## Download filename format

All completed tasks produce download links with the `download` attribute set to:

```
{videoTitle}-{audioCodec}-{videoCodec}-{height}p.{format}
```

Example: `2022_10_04_Two_Maxes-04-renamed-aac-h264-720p.mp4`

This attribute updates in realtime when the video is renamed (via Mercure SSE `app:video` message updating `dto.video`).

---

## Realtime updates (SSE) in tests

Tests `04`–`08` and `prod/01` do **not reload the page** during status polling. All task status/progress updates are received via Mercure SSE and applied by `applyTaskRealtimeUpdate` directly to `state.dto.value.tasks`. Tests poll `readPresetTaskState` (which reads DOM) to observe realtime changes.

Mercure messages are captured to `mercure-sse.json` attachment via `window.__mercure_messages` for debugging.

---

## Execution order

```
01.admin.login.js
02.upload.video.js
03.admin.crud.js
04.transcode.flow.js
05.task.state.flow.js
06.multi.preset.flow.js
07.parallel.transcode.js
08.tariff.js
```

## Data dependencies

| Test | Creates | Requires |
|---|---|---|
| `02` | Video `…-02` (renamed) | — |
| `03` | Presets (Standard/FHD), Tariffs (Free/Premium), user `test@test.com`; marks `…-02` deleted | `02` uploaded the video |
| `04` | Video `…-04` (uploads, transcodes, renames, deletes) | `03` created presets + `test@test.com` |
| `05` | Video `…-05` (uploads, transcodes FHD, cancel/restart) | `03` created FHD preset; assigns Premium to admin temporarily |
| `06` | Video `…-06`; upgrades `test@test.com` to Premium | `03` created `test@test.com` with Free; `05` left admin on some tariff |
| `07` | Video `…-07` | `test@test.com` has Premium (from `06`); `Standard` preset exists |
| `08` | Videos `…-08-*`; creates 4 tariff variants; deletes old `…-05` video | Baseline Free tariff, Standard preset, `test@test.com` |

## Local run in release stack

```bash
cd /root/video-transcoder/develop
bash release.check.sh
```

Artifacts are saved under `develop/release.check/<PROJECT_NAME>/playwright`.

## Record a new test

- Start docker-compose environment
- Start local X server (e.g. VcXsrv)
- `docker exec -it relcheck_0_0_3_XXXXXXXX-playwright-1 bash`
- `export DISPLAY=192.168.2.70:0`
- `npx playwright codegen http://nginx`
