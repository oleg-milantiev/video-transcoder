# E2E (Playwright)

This directory contains release smoke tests running against the release Docker Compose stack.

## Helpers and test style

- Shared UI interactions and selectors are centralized in `helpers/`.
- Tests in `tests/` should read like scenario steps and avoid low-level locators.
- Prefer helper calls such as:
  - `loginAs(page, email, password)` / `loginAsAdmin(page)` / `logoutToPublic(page)`
  - `uploadFixtureAs(page, srcName, uploadAsName)` / `uploadFixtureAs(page, src, name, { expectedErrorText })`
  - `createOrUpdateTariffByTitle(page, 'Free', { delay: 3600, instance: 1, ... }, testInfo, 'screenshot.png')`
  - `createUserWithTariff(page, email, password, tariffTitle)`

### Helper modules

- `helpers/auth.js` — login/logout helpers: `loginAsAdmin`, `loginAs`, `logoutToPublic`, `getAdminCredentials`, `getTestCredentials`
- `helpers/mainApp.js` — Upload/Videos/Tasks tab navigation: `openUploadTab`, `openVideosTab`, `openTasksTab`, `expectTabsVisible`, `expectUploadDashboardVisible`, `expectVideosTableVisible`, `expectTasksTableVisible`, `expectEmptyVideos`, `expectEmptyTasks`, `videoRowByTitle`, `activeVideoRowByTitle`, `expectVideoRowHasCoreValues`
- `helpers/upload.js` — Uppy upload actions:
  - `uploadFixture(page, fileName)` — opens Upload tab and uploads by filename
  - `uploadFixtureAs(page, sourceFileName, uploadAsName, { expectedErrorText })` — unified helper: reads file, opens Upload tab, triggers upload; if `expectedErrorText` is set the upload is expected to fail and Videos tab is NOT opened; otherwise navigates to Videos tab after completion
  - `expectUploadHintText(page, expectedText)` — checks the TariffHint text
- `helpers/video.js` — Video Details page, preset blocks, task-row helpers:
  - `waitForVideoDetailsVisible(page, { requirePresets })` — waits for `📄 Details` nav-link + optional preset h6 blocks
  - `expectDetailsValue(page, label)` — checks a `<dt>/<dd>` pair is non-empty
  - `renameVideoFromDetails(page, newTitle)` — opens SweetAlert2 rename modal and submits
  - `expectVideoDetailsTitle(page, expectedTitle)` — polls `<h6>` until it matches expected title
  - `clickBackButton(page)` — clicks the `Close` button (✕) in video details
  - `switchToVideoTab(page, tabName)` — clicks the Details/Transcode/etc. nav tab
  - `switchToCustomGoal(page)` — clicks the Custom goal card; skips if already active
  - `presetBlock(page, presetTitle)` — locates the `<h6>`-parent `div` for a preset
  - `clickHeightButtonInPreset(page, presetTitle, height)` — clicks the resolution button for the given height in a preset block
  - `tasksTable(page)` — locates `#transcoding-tasks-section table`
  - `taskRowByPreset(page, presetTitle)` — first task row matching presetTitle (any status)
  - `activeTaskRowByPreset(page, presetTitle)` — first non-CANCELLED row for preset
  - `taskRowByPresetAndHeight(page, presetTitle, height)` — row matching preset AND `{height}p`
  - `activeTaskRowByPresetAndHeight(page, presetTitle, height)` — non-CANCELLED row for preset+height
  - `taskRowByHeight(page, heightLabel)` — task row by height label only (e.g. `'1080p'`), no preset filter
  - `presetRow` — alias for `taskRowByPreset`
  - `readPresetTaskState(page, presetTitle, opts)` — reads `{ status, progress }` from task row (strips `?` icon suffix)
  - `readPresetTaskStateByHeight(page, presetTitle, height, opts)` — same but height-specific
  - `waitForPresetTaskStatus(page, presetTitle, targetStatus, { maxAttempts, pollMs, preferActive })` — polls until a single preset's task reaches `targetStatus`
  - `pollUntilHeightCompleted(page, presetTitle, height, opts)` — polls until a height-specific task reaches COMPLETED
  - `pollUntilCompletedWithProgressTracking(page, presetTitle, { maxAttempts, pollMs, preferActive })` — polls until `COMPLETED` while tracking that progress increases at least once
  - `waitForPosterAndMeta(page, testInfo, prefix)` — polls until poster `<img>` is fully loaded and `duration` meta is present (up to 5 retries × 5 s + page reload)
  - `getAllPresetTitles(page)` — returns all visible preset titles from `h6` blocks
  - `expectAllPresetsToShowTranscodeWithExpectedSize(page)` — checks each preset block shows buttons with `~X MB` size hints
  - `expectPresetStatusHelpIcon(page, presetTitle, { statusText, tooltipText })` — checks `?` help icon is visible in status cell
  - `clickTranscodeForPreset(page, presetTitle)` — clicks the first enabled resolution button in a preset block
  - `waitForAllPresetsToComplete(page, presetTitles, maxAttempts, delayMs)` — polls until all preset titles reach COMPLETED
  - `waitForAllPresetsProcessingWithProgress(page, presetTitles, maxAttempts, pollMs)` — polls until all presets are PROCESSING with progress > 0
  - `waitForDeletedVideoDetailsWithoutPoster(page, expectedTitle, maxAttempts, delayMs)` — polls for deleted badge + absent poster in details
  - `verifyDeletedVideoInListAndDetails(page, testInfo, uploadedFileName, screenshotPrefix, opts)` — polls the Videos list until the row shows `td.video-title-deleted` + "No poster", then opens Details and waits for deleted state without poster
  - `expectFlashPopupTitle(page, titleText, timeout)` — waits for `.app-flash-toast .app-flash-title`
- `helpers/admin.js` — EasyAdmin CRUD helpers: `openAdminSection`, `openAdminDashboardFromHome`, `ensureAdminMenuSectionsVisible`, `adminMenuLink`, `createOrUpdatePreset`, `createOrUpdateTariffByTitle`, `assignTariffToUser`, `createUserWithTariff`, `mainTableBodyForHeading`, `dismissVisibleAdminModal`, `dismissAllVisibleModals`
- `helpers/dialogs.js` — `clickAndAcceptConfirm(page, clickableLocator, expectedNativeMessagePart)` — handles both native `dialog` events and Bootstrap confirm buttons
- `helpers/download.js` — `clickDownloadAndVerifyMp4(page, row)` (clicks Download, verifies `< 400` status and `.mp4` URL), `expectDownloadFilename(page, filename)`, `expectRowDownloadFilename(row, filename)`
- `helpers/screenshot.js` — `shot(page, testInfo, name)` — consistent screenshot capture
- `helpers/constants.js` — `UI_TIMEOUT=8000`, `NAV_TIMEOUT=15000`, `UPLOAD_TIMEOUT=30000`
- `helpers/tariff.js` — tariff page helpers: `tariffCard`, `waitForTariffsLoaded`, `FREE_INCLUDED`, `FREE_EXCLUDED`, `PREMIUM_INCLUDED`, `PREMIUM_EXCLUDED`, `ENTERPRISE_INCLUDED`, `ENTERPRISE_EXCLUDED`
- `helpers/profile.js` — profile page helpers: `waitForProfileLoaded`
- `helpers/capture.js` — diagnostic helpers:
  - `attachSseMessages(page, testInfo)` — attaches `mercure-sse.json` from `window.__mercure_messages`; safe to call in `finally`

### Prod-only helpers (`prod/helpers/`)

- `prod/helpers/index.js` — re-exports all shared helpers + `loginAsCredentials`
- `prod/helpers/admin.js` — `filterUsersByEmail`, `setTariffForFilteredUser`, `deleteUserByEmail`, `deleteFilteredUser`

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

## Preset block UI (Transcode Builder)

The **Transcode Builder** section renders:
- A row of **Goal** cards (Social media, Max quality, Fast & compact, For PC, Archive, Custom) — clicking a card filters the preset list below.
- For each matching preset: a `<div>` with:
  - `<h6>` heading: `{presetTitle} ({videoCodec}/{audioCodec}/{format})`
  - Row of `<button class="btn-outline-primary">` with `{width}x{height}` labels
  - `~X MB` size hint under each button (calculated from bitrate × duration)
  - Buttons filtered by `tariff.height`; buttons for already-existing tasks are struck-through and disabled

The section is **hidden** if `_width` or `_height` are absent in video meta. The deleted video details page shows the section with all buttons disabled.

**Free tariff** — only `Standard video Quality` (h264/aac/mp4) is visible; resolutions capped at 1080p.  
**Premium tariff** — all 7 presets visible; all resolution buttons enabled; WebM format available; quality defaults to "good" for Social media.

---

## Test Users

All test users are created by `setup.js` via the admin panel.

| User | Password | Tariff | Used by |
|---|---|---|---|
| `test-01@test.com` | `test-01` | Free | `01` |
| `test-02@test.com` | `test-02` | Free-100M (0.1 GB storage) | `02` |
| `test-03@test.com` | `test-03` | Free | `03` |
| `test-04@test.com` | `test-04` | Free | `04` |
| `test-05@test.com` | `test-05` | Free | `05` |
| `test-06@test.com` | `test-06` | Premium | `06`, `07` |
| `test-07@test.com` | `test-07` | Free | `07` |
| `test-09@test.com` | `test-09` | Premium (instance=2, delay=0) | `09` |
| `test-10@test.com` | `test-10` | Free | `10` |
| `test-11@test.com` | `test-11` | Free | `11` |
| `test-12@test.com` | `test-12` | Free | `12` |
| `test-13@test.com` | `test-13` | Free | `13` |
| `test-14@test.com` | `test-14` | Free | `14` |
| `test-15@test.com` | `test-15` | Free-duration (videoDuration=2 s) | `15` |
| `test-16@test.com` | `test-16` | Free-resolution (maxWidth=320, maxHeight=180) | `16` |
| `test-17@test.com` | `test-17` | Free-filesize (videoSize=3 MB) | `17` |
| `test-18@test.com` | `test-18` | Free | `18` |
| `test-19@test.com` | `test-19` | Premium | `19` |

---

## What is covered

Tests run sequentially (`workers: 1`). Each test is self-contained — it creates its own video, performs its scenario, and logs out. No test depends on data produced by another.

---

### `setup.js` — one-time environment bootstrap

Runs first. Creates all tariff variants and test users via the EasyAdmin panel.

**Phase 1** — smoke-check admin home: login, verify Upload/Videos/Tasks tabs + Uppy dashboard, verify empty Videos and Tasks.

**Phase 2** — create tariff variants:

| Tariff | Changed field | Value |
|---|---|---|
| `Free-100M` | `storageGb` | 0.1 GB |
| `Free-duration` | `videoDuration` | 2 s |
| `Free-resolution` | `maxWidth`, `maxHeight` | 320, 180 |
| `Free-filesize` | `videoSize` | 3 MB |

**Phase 3** — create test users `test-01` … `test-19` with appropriate tariffs.

**Phase 4** — logout.

---

### `01.upload.video.js` — upload, details, rename, back-navigation

- Login as `test-01` (Free).
- Verify Uppy dashboard ready.
- Upload `2022_10_04_Two_Maxes.mp4`.
- Open Videos tab → find row → verify core values.
- Open Video Details → verify `Title`, `Created`, `Expires` are non-empty.
- Wait for poster + `duration` meta.
- Rename video to `…-01` via SweetAlert2 modal.
- Verify renamed title in Details and Videos list.
- Go back → verify Videos list.
- Logout.

---

### `02.storage.badge.js` — storage exhaustion, bulk delete

- Login as `test-02` (Free-100M: 0.1 GB ≈ 102 MB).
- Verify badge starts at `0 MB / 102 MB`.
- Upload fixture 15 times; after each upload verify badge "now" increases.
- After 15 uploads verify "Storage is running low" warning.
- 16th upload must fail with `exceeds maximum allowed size`.
- Open Videos tab → verify 15 rows.
- Delete the 15th video → badge "now" must decrease.
- Re-upload 16th → badge increases again.
- Delete all videos on page 1, then page 2.
- Verify badge returns to `0 MB / 102 MB`.
- Logout.

---

### `03.contact.us.js` — Contact Us form on all pages

- Login as `test-03`.
- For each page (home, terms, privacy, profile, tariffs):
  - Click "Contact Us" → Cancel → modal closes.
  - Click "Contact Us" → Send without text → validation `Message must not be empty`.
  - Fill text → Send → `Request received!` → OK.
- On Tariffs page: same full flow via Enterprise card "Contact us" button.
- Logout.

---

### `04.texts.js` — static texts: home, Terms of Service, Privacy Policy

- As guest: find "Why Sign Up?" heading + 4 feature cards; click "Sign In to Get Started" → login form.
- As guest and as `test-04`:
  - Footer: verify Terms and Privacy links.
  - Click Terms → `h1 "Terms of Service"` + sections 1–9.
  - Click Privacy → `h1 "Privacy Policy"` + sections 1–10.
- Logout.

---

### `05.profile.free.js` — profile page: Free tariff

**Phase 1 — empty state:**
- Guest: "Profile" footer link NOT visible.
- Login as `test-05` (Free).
- Navigate to `/profile`:
  - Account: email, member since, plan badge `Free`, `Upgrade` link.
  - Tariff: Free card (6 ✔, 5 ⊘, `Current plan` disabled) + Premium card (`Upgrade to Premium`).
  - Videos & Transcoding: all counters 0.
  - Storage: `0 MB used of 1 GB`, all stats zero, retention note.

**Phase 2 — after upload + transcode:**
- Upload fixture twice (`-1`, `-2`), delete video-1.
- Open video-2 → Custom goal → start 144p then 240p.
- Wait for 144p COMPLETED (240p stays PENDING: Free instance=1).
- Navigate to `/profile`:
  - Videos active/total: 1/1; Transcoding: 2/2; queue: 1; completed: 1; next encoding: non-empty.
  - Storage used > 0, retention stats updated.
- Logout.

---

### `06.profile.premium.js` — profile page: Premium tariff

**Phase 1 — empty state:**
- Login as `test-06` (Premium: instance=2, storageGb=1).
- Navigate to `/profile`:
  - Account: plan badge `Premium`, no Upgrade link.
  - Tariff: Your Plan (10 ✔, 1 ⊘, `Current plan` disabled) + Subscription card (no payments yet).
  - Videos & Transcoding: all counters 0.
  - Storage: `0 MB used of 1 GB`, all zeros.

**Phase 2 — after parallel transcode:**
- Upload twice (`-1`, `-2`), delete video-1.
- Open video-2 → Custom → start 144p + 240p; wait for BOTH COMPLETED (Premium: parallel).
- Navigate to `/profile`:
  - Videos 1/2; Transcoding 2/2; queue: 0; completed: 2; next encoding: –.
  - Storage: used ~18 MB, Expiring in 24h non-zero.
- Logout.

---

### `07.tariffs.js` — tariffs page: Free user and Premium user

**Phase 1 — Free user (`test-07`):**
- Free card: 6 ✔, 5 ⊘, `Current plan` ribbon + disabled button.
- Premium card: `Upgrade to Premium` button.
- Enterprise card: `Contact us` button.
- From `/profile` → click `Upgrade` → lands on `/tariffs`.

**Phase 2 — Premium user (`test-06`):**
- Premium card: `Current plan` ribbon + disabled button.
- Free card: no ribbon.
- Enterprise card: `Contact us` button.
- Logout.

---

### `08.admin.js` — admin panel section smoke test

- Login as admin → open EasyAdmin.
- For each of 7 sections (Users, Tariffs, Payments, Videos, Presets, Tasks, Logs):
  - Sidebar link visible; click → URL and `h1` match.
  - `Users`, `Tariffs`, `Presets`: datagrid table with 1+ rows.
  - `Payments`: "No results found."
  - `Videos`, `Tasks`, `Logs`: any state.
- Logout.

---

### `09.task.cancel.restart.js` — cancel at 30%, restart, complete, check disabled button

- Login as `test-09` (Premium: instance=2, delay=0).
- Upload `…-09.mp4`; open video details.
- Transcode tab → Custom → start 1080p in `Standard video Quality`.
- Poll until PROCESSING → wait for progress ≥ 30% → Cancel.
- Poll until CANCELLED → verify no Download link, Transcode restart button visible.
- Click `Transcode` (restart) → poll until COMPLETED, verify progress increased.
- Download + verify filename `…-09-aac-h264-1080p.mp4`.
- Switch to Transcode tab → Custom goal → verify 1080p button is **disabled** (already transcoded).
- Logout.

---

### `10.pending.task.js` — PENDING state, tooltip, no-delete guard

- Login as `test-10` (Free: instance=1).
- Upload `…-10.mp4`; open video details.
- Custom → start 144p; go back → Custom → start 240p.
- Wait for 144p COMPLETED → download + verify `…-10-aac-h264-144p.mp4`.
- Verify 240p row: `PENDING` + `?` icon with tooltip `Why isn't my video transcoding?`.
- Videos list → verify video with active PENDING task cannot be deleted.
- Logout.

---

### `11.rename.download.js` — rename + download (TBD)

- Login as `test-11` (Free). Placeholder test; no meaningful assertions yet. Logout.

---

### `12.delete.without.tasks.js` — delete video without tasks, verify disabled transcode buttons

- Login as `test-12` (Free).
- Upload `…-12.mp4`; open video details; wait for poster + meta.
- Go back to Videos list → delete video → verify `td.video-title-deleted`.
- Click deleted row → video details open.
- Transcode tab → Custom goal (click if not already active).
- Verify every `button.btn-outline-primary` in preset section is **disabled**.
- Logout.

---

### `13.delete.with.tasks.js` — transcode, download, then delete video

- Login as `test-13` (Free).
- Upload `…-13.mp4`; open video details.
- Custom → start 144p → wait for COMPLETED.
- Download + verify `…-13-aac-h264-144p.mp4`.
- Videos list → delete video → verify `td.video-title-deleted`.
- Logout.

---

### `14.transcode.progress.js` — progress tracking: 1080p full run

- Login as `test-14` (Free).
- Upload `…-14.mp4`; open video details.
- Custom → start 1080p → poll until PROCESSING; verify progress increases.
- Wait for COMPLETED → download + verify `…-14-aac-h264-1080p.mp4`.
- Logout.

---

### `15.tariff.limit.duration.js` — duration limit: video auto-deleted

- Login as `test-15` (Free-duration: videoDuration=2 s).
- Upload fixture (longer than 2 s) — server auto-deletes after upload.
- Verify `td.video-title-deleted` in list + "No poster".
- Open details → confirm deleted state, no poster.
- Logout.

---

### `16.tariff.limit.resolution.js` — resolution limit: video auto-deleted

- Login as `test-16` (Free-resolution: maxWidth=320, maxHeight=180).
- Upload fixture (higher resolution than 320×180) — server auto-deletes.
- Verify deleted in list + details, no poster.
- Logout.

---

### `17.tariff.limit.filesize.js` — file size limit: upload rejected

- Login as `test-17` (Free-filesize: videoSize=3 MB).
- Attempt to upload fixture (≈6.44 MB) → rejected by Uppy with `exceeds maximum allowed size`.
- Logout.

---

### `18.transcode.builder.js` — transcode builder UI: Free tariff

- Login as `test-18` (Free).
- Upload `…-18.mp4`; open video details → Transcode tab.
- For each Goal (Social media, Max quality, Fast & compact, For PC, Archive, Custom):
  - Click the Goal card (skip if already active).
  - **Social media**: 1 preset block; quality selector shows `normal`; resolution buttons have `~X MB` hints; 240p/360p enabled, 1080p+ disabled by tariff.
  - **Max quality / Fast & compact / For PC / Archive**: respective preset block(s); enabled buttons.
  - **Custom**: only `Standard video Quality` (h264/aac/mp4); 8 resolution buttons (144p–2160p); 1440p and 2160p disabled (Free maxHeight=1080).
- Logout.

---

### `19.transcode.builder.js` — transcode builder UI: Premium tariff

- Login as `test-19` (Premium).
- Upload `…-19.mp4`; open video details → Transcode tab.
- For each Goal (Social media, Max quality, Fast & compact, For PC, Archive, Custom):
  - **Social media**: quality selector shows `good` (Premium default); WebM preset available with "Smaller size" badge; all buttons up to 2160p enabled.
  - **Max quality / Fast & compact / For PC / Archive**: all presets for the Goal; all resolution buttons enabled.
  - **Custom**: all 7 preset blocks visible (h264/aac/mp4, h265/aac/mp4, h265/opus/mp4, vp9/opus/webm, av1/opus/webm, av1/opus/mp4, vp8/opus/webm); each has 8 resolution buttons (144p–2160p); all enabled (no resolution cap on Premium).
- Logout.

---

## Download filename format

All completed tasks produce download links with the `download` attribute set to:

```
{videoTitle}-{audioCodec}-{videoCodec}-{height}p.{format}
```

Example: `2022_10_04_Two_Maxes-09-aac-h264-1080p.mp4`

This attribute updates in realtime when the video is renamed (via Mercure SSE `app:video` message).

---

## Realtime updates (SSE) in tests

Tests do **not reload the page** during status polling. All task status/progress updates are received via Mercure SSE and applied by `applyTaskRealtimeUpdate` directly to `state.dto.value.tasks`. Tests poll `readPresetTaskState` (which reads DOM) to observe realtime changes.

Mercure messages are captured to `mercure-sse.json` attachment via `window.__mercure_messages` for debugging.

---

## Execution order

```
setup.js              ← one-time bootstrap: tariffs + users
01.upload.video.js
02.storage.badge.js
03.contact.us.js
04.texts.js
05.profile.free.js
06.profile.premium.js
07.tariffs.js
08.admin.js
09.task.cancel.restart.js
10.pending.task.js
11.rename.download.js
12.delete.without.tasks.js
13.delete.with.tasks.js
14.transcode.progress.js
15.tariff.limit.duration.js
16.tariff.limit.resolution.js
17.tariff.limit.filesize.js
18.transcode.builder.js
19.transcode.builder.js
```

---

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

---

## `prod/tests/01.prod.safe.js` — isolated production smoke

Self-contained test with automatic cleanup. Designed to run safely in production without side effects.
Timeout: **35 minutes**.

### Phases

#### Phase 1 — Admin creates isolated user

- Admin logs in, deletes any pre-existing isolated user with today's email (idempotent).
- Creates fresh user `{prod-YYYYMMDD}@example.test` with `Free` tariff.

#### Phase 2 — Fresh user: upload + verify empty state

- Login as isolated user; verify empty Videos/Tasks; verify upload hint `0 MB / 1 GB`.
- Upload fixture; open details; wait for poster + meta.
- Click `1080p` then `720p` in `Standard video Quality`.

#### Phase 3 — Free tariff: 1080p starts, 720p waits PENDING

- Wait for 1080p PROCESSING → wait for > 30% progress → Cancel.
- Wait for CANCELLED → restart via `Transcode` button.
- New 1080p becomes PENDING (blocked by 720p, Free instance=1).
- Verify `?` tooltip on pending row; cancel both pending tasks.

#### Phase 4 — Admin upgrades to Premium

- Admin filters isolated user → sets tariff `Premium`.

#### Phase 5 — Premium: restart both, complete, download, delete

- Restart CANCELLED 1080p and 720p; wait for both COMPLETED.
- Verify download filenames; click Download for each.
- Delete video from Videos list.

#### Phase 6 — Admin deletes isolated user

**Cleanup in `finally`**: if video or user were not deleted during the test run, cleanup is attempted automatically.

