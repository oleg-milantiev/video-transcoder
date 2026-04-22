const { test, expect } = require('@playwright/test');
const { attachConsoleCapture } = require('../../consoleCapture');
const {
  UI_TIMEOUT,
  NAV_TIMEOUT,
  shot,
  openHome,
  openUploadTab,
  openVideosTab,
  openTasksTab,
  expectTabsVisible,
  expectUploadDashboardVisible,
  expectUploadHintText,
  expectEmptyVideos,
  expectEmptyTasks,
  loginAsAdmin,
  logoutToPublic,
  loginAsCredentials,
  openAdminDashboardFromHome,
  createUserWithTariff,
  filterUsersByEmail,
  setTariffForFilteredUser,
  deleteUserByEmail,
  uploadFixtureAsName,
  expectVideosTableVisible,
  expectVideoRowHasCoreValues,
  activeVideoRowByTitle,
  videoRowByTitle,
  waitForVideoDetailsVisible,
  expectVideoDetailsTitle,
  waitForPosterAndMeta,
  getAllPresetTitles,
  presetRow,
  presetBlock,
  tasksTable,
  taskRowByPresetAndHeight,
  activeTaskRowByPresetAndHeight,
  readPresetTaskStateByHeight,
  expectPresetStatusHelpIcon,
  clickAndAcceptConfirm,
  buildRunContext,
} = require('../helpers');

// Single preset with multiple height buttons
const STANDARD_PRESET = 'Standard video Quality';
// The two heights we exercise in this test (highest first)
const REQUIRED_TASKS = [
  { title: STANDARD_PRESET, height: 1080 },
  { title: STANDARD_PRESET, height: 720 },
];

function normalizeStatus(status) {
  return String(status || '').trim().toUpperCase();
}

// Height-aware UI state reader.
// Transcode (restart) button lives in the CANCELLED task-table row.
// Cancel / Download / Status / Progress live in the active task-table row.
async function readPresetUiState(page, presetTitle, height) {
  // Active (non-cancelled) row for this preset+height
  const activeRow = activeTaskRowByPresetAndHeight(page, presetTitle, height);
  const activeVisible = (await activeRow.count()) > 0 && await activeRow.isVisible().catch(() => false);

  // First matching row (may be cancelled) — used to find the Transcode restart button
  const anyRow = taskRowByPresetAndHeight(page, presetTitle, height);
  const anyVisible = (await anyRow.count()) > 0 && await anyRow.isVisible().catch(() => false);

  let status = 'NO TASK';
  let progress = -1;
  let hasCancel = false;
  let hasDownload = false;
  let hasTranscode = false;

  if (activeVisible) {
    const cancelButton = activeRow.getByRole('button', { name: 'Cancel' });
    const downloadLink = activeRow.getByRole('link', { name: 'Download' });
    hasCancel = (await cancelButton.count()) > 0 && await cancelButton.first().isVisible().catch(() => false);
    hasDownload = (await downloadLink.count()) > 0 && await downloadLink.first().isVisible().catch(() => false);
    const { status: s, progress: p } = await readPresetTaskStateByHeight(page, presetTitle, height, { preferActive: true });
    status = normalizeStatus(s);
    progress = p;
  } else if (anyVisible) {
    // Only cancelled rows exist — status is CANCELLED, Transcode restart button may be present
    const transcodeButton = anyRow.getByRole('button', { name: 'Transcode' });
    hasTranscode = (await transcodeButton.count()) > 0 && await transcodeButton.first().isVisible().catch(() => false);
    const { status: s, progress: p } = await readPresetTaskStateByHeight(page, presetTitle, height);
    status = normalizeStatus(s);
    progress = p;
  } else {
    // No task at all — Transcode button is in the preset block
    const block = presetBlock(page, presetTitle);
    const btn = block.locator('button.btn-outline-primary:not([disabled])').first();
    hasTranscode = (await btn.count()) > 0 && await btn.isVisible().catch(() => false);
  }

  return { presetTitle, height, rawStatus: status, status, progress, hasTranscode, hasCancel, hasDownload };
}

async function waitForPresetState(page, presetTitle, height, matcher, description, timeout = 180000, pollMs = 2000) {
  const startedAt = Date.now();
  let lastState = null;
  while (Date.now() - startedAt < timeout) {
    lastState = await readPresetUiState(page, presetTitle, height);
    if (matcher(lastState)) return lastState;
    await page.waitForTimeout(pollMs);
  }
  throw new Error(`${description} for "${presetTitle}" ${height}p not reached. Last: ${JSON.stringify(lastState)}`);
}

// tasks = [{ title, height }, ...]
async function waitForAllPresets(page, tasks, matcher, description, timeout = 900000, pollMs = 3000) {
  const startedAt = Date.now();
  let states = [];
  while (Date.now() - startedAt < timeout) {
    states = [];
    let allMatched = true;
    for (const task of tasks) {
      const state = await readPresetUiState(page, task.title, task.height);
      states.push(state);
      if (!matcher(state)) allMatched = false;
    }
    if (allMatched) return states;
    await page.waitForTimeout(pollMs);
  }
  throw new Error(`${description} not reached. Last states: ${JSON.stringify(states)}`);
}

async function openVideoDetailsByTitle(page, title) {
  await openVideosTab(page);
  await expectVideosTableVisible(page);
  const row = activeVideoRowByTitle(page, title);
  await expect(row).toBeVisible({ timeout: UI_TIMEOUT });
  await row.click({ timeout: UI_TIMEOUT });
  await waitForVideoDetailsVisible(page);
  return row;
}

// Start a task for a specific height. Tries the Transcode restart button in a CANCELLED task row
// first; falls back to the first available preset block button (when no prior task exists).
async function startTaskForHeight(page, presetTitle, height) {
  const cancelledRow = taskRowByPresetAndHeight(page, presetTitle, height);
  if ((await cancelledRow.count()) > 0 && await cancelledRow.isVisible().catch(() => false)) {
    const restartBtn = cancelledRow.getByRole('button', { name: 'Transcode' });
    if ((await restartBtn.count()) > 0 && await restartBtn.isVisible().catch(() => false)) {
      await restartBtn.click({ timeout: UI_TIMEOUT });
      return;
    }
  }
  // No prior task — click first available button in preset block (highest resolution)
  const block = presetBlock(page, presetTitle);
  await block.locator('button.btn-outline-primary:not([disabled])').first().click({ timeout: UI_TIMEOUT });
}

async function clickDownloadAndVerify(page, presetTitle, height) {
  const row = taskRowByPresetAndHeight(page, presetTitle, height);
  await expect(row).toBeVisible({ timeout: UI_TIMEOUT });
  const downloadLink = row.getByRole('link', { name: 'Download' });
  await expect(downloadLink).toBeVisible({ timeout: UI_TIMEOUT });

  const href = await downloadLink.getAttribute('href');
  if (!href) throw new Error('Download link href is empty');

  const downloadPromise = page.waitForEvent('download', { timeout: 20000 });
  await downloadLink.click({ timeout: UI_TIMEOUT });
  const download = await downloadPromise;

  // filename: videoTitle-audioCodec-videoCodec-heightp.format
  const suggested = download.suggestedFilename();
  expect(suggested).toContain(String(height) + 'p');
  expect(suggested).toMatch(/\.mp4$/i);

  const downloadUrl = new URL(href, page.url()).toString();
  const response = await page.request.get(downloadUrl, {
    failOnStatusCode: false,
    maxRedirects: 0,
    timeout: NAV_TIMEOUT,
  });
  expect(response.status()).toBeLessThan(400);
}

async function ensureLoggedOut(page) {
  await page.goto('/logout', { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT }).catch(() => {});
  await openHome(page);
  const signOutLink = page.getByRole('link', { name: 'Sign out' });
  if ((await signOutLink.count()) > 0 && await signOutLink.first().isVisible().catch(() => false)) {
    await signOutLink.first().click({ timeout: UI_TIMEOUT });
  }
}

async function deleteVideoFromListIfPresent(page, videoTitle, testInfo, screenshotName) {
  await openVideosTab(page);
  await expectVideosTableVisible(page);
  const row = videoRowByTitle(page, videoTitle);
  if ((await row.count()) === 0) return false;
  await expect(row).toBeVisible({ timeout: UI_TIMEOUT });
  const deleteButton = row.getByRole('button', { name: 'Delete' });
  if ((await deleteButton.count()) === 0) return false;
  await clickAndAcceptConfirm(page, deleteButton, 'Delete this video?');
  await expect.poll(async () => (await row.locator('td.video-title-deleted').count()) > 0).toBeTruthy();
  await shot(page, testInfo, screenshotName);
  return true;
}

test.describe('prod-safe isolated smoke', () => {
  test.setTimeout(35 * 60 * 1000);

  test('creates isolated user, verifies safe flow, upgrades tariff, downloads outputs, and cleans up', async ({ page }, testInfo) => {
    page.setDefaultTimeout(UI_TIMEOUT);
    page.setDefaultNavigationTimeout(NAV_TIMEOUT);

    const capture = attachConsoleCapture(page, testInfo, { maxBodyChars: 4000 });
    await capture.start();

    const run = buildRunContext();
    const expectedUploadHint = '0 MB / 1 GB';
    let userCreated = false;
    let videoDeleted = false;
    let userDeleted = false;

    try {
      // Phase 1 — Admin creates a fresh isolated user for this day.
      await loginAsAdmin(page);
      await expectTabsVisible(page);
      await shot(page, testInfo, '01-admin-login.png');

      await openAdminDashboardFromHome(page);
      await shot(page, testInfo, '02-admin-dashboard.png');

      await deleteUserByEmail(page, run.userLocalPart, testInfo, '02b-admin-preclean-existing-user.png').catch(() => false);

      await createUserWithTariff(page, run.userEmail, run.userPassword, 'Free');
      userCreated = true;
      await shot(page, testInfo, '03b-admin-user-created.png');

      await logoutToPublic(page);
      await shot(page, testInfo, '04-admin-sign-out.png');

      // Phase 2 — Fresh user sees empty state, upload area and uploads a video.
      await loginAsCredentials(page, run.userEmail, run.userPassword);
      await expectTabsVisible(page);
      await openUploadTab(page);
      await expectUploadDashboardVisible(page);
      await expectUploadHintText(page, expectedUploadHint);
      await shot(page, testInfo, '05-user-upload-empty-state.png');

      await openVideosTab(page);
      await expectEmptyVideos(page);
      await shot(page, testInfo, '06-user-empty-videos.png');

      await openTasksTab(page);
      await expectEmptyTasks(page);
      await shot(page, testInfo, '07-user-empty-tasks.png');

      await uploadFixtureAsName(page, run.sourceVideoFileName, run.uploadFileName);
      await shot(page, testInfo, '08-user-upload-complete.png');

      await openVideosTab(page);
      await expectVideosTableVisible(page);
      const uploadedRow = activeVideoRowByTitle(page, run.videoBaseName);
      await expectVideoRowHasCoreValues(uploadedRow, run.videoBaseName);
      await shot(page, testInfo, '09-user-video-visible-in-list.png');

      await uploadedRow.click({ timeout: UI_TIMEOUT });
      await waitForVideoDetailsVisible(page);
      await expectVideoDetailsTitle(page, run.videoBaseName);
      await waitForPosterAndMeta(page, testInfo, '10-poster-meta-attempt');
      await shot(page, testInfo, '10b-video-details-ready.png');

      // Verify the Standard video Quality preset block is visible
      const blockPreset = presetBlock(page, STANDARD_PRESET);
      await expect(blockPreset).toBeVisible({ timeout: UI_TIMEOUT });

      // Verify preset appears in getAllPresetTitles
      const allPresetTitles = await getAllPresetTitles(page);
      if (!allPresetTitles.includes(STANDARD_PRESET)) {
        throw new Error(`Required preset "${STANDARD_PRESET}" not found. Available: ${allPresetTitles.join(', ')}`);
      }

      // Click 1080p button (first / highest resolution, nth(0))
      const btn1080 = blockPreset.locator('button.btn-outline-primary:not([disabled])').nth(0);
      await expect(btn1080).toBeVisible({ timeout: UI_TIMEOUT });
      await btn1080.click({ timeout: UI_TIMEOUT });
      await shot(page, testInfo, '10c-1080p-transcode-clicked.png');

      // Wait briefly then click 720p (now nth(0) of still-enabled buttons)
      await page.waitForTimeout(400);
      const btn720 = blockPreset.locator('button.btn-outline-primary:not([disabled])').nth(0);
      await expect(btn720).toBeVisible({ timeout: UI_TIMEOUT });
      await btn720.click({ timeout: UI_TIMEOUT });
      await shot(page, testInfo, '10d-720p-transcode-clicked.png');

      // Phase 3 — Free tariff: 1080p starts immediately (PROCESSING), 720p stays PENDING.
      // Monitor the 1080p task specifically.
      await waitForPresetState(
        page, STANDARD_PRESET, 1080,
        (state) => state.status === 'PROCESSING' && state.hasCancel,
        '1080p processing with cancel button',
      );
      await shot(page, testInfo, '13-1080p-processing.png');

      await waitForPresetState(
        page, STANDARD_PRESET, 1080,
        (state) => state.status === 'PROCESSING' && state.progress > 30,
        '1080p progress above 30%',
        45000,
      );
      await shot(page, testInfo, '14-1080p-progress-above-30.png');

      // Cancel the 1080p task (Cancel button is in the active task row)
      const processing1080Row = activeTaskRowByPresetAndHeight(page, STANDARD_PRESET, 1080);
      await processing1080Row.getByRole('button', { name: 'Cancel' }).click({ timeout: UI_TIMEOUT });
      await shot(page, testInfo, '15-1080p-cancel-clicked.png');

      await waitForPresetState(
        page, STANDARD_PRESET, 1080,
        (state) => state.status === 'CANCELLED' && state.hasTranscode,
        '1080p cancelled with transcode restart button',
      );
      await shot(page, testInfo, '16-1080p-cancelled.png');

      // Restart 1080p — Transcode button is in the CANCELLED task row
      await startTaskForHeight(page, STANDARD_PRESET, 1080);
      await shot(page, testInfo, '17-1080p-requeued.png');

      // 720p is now PROCESSING (or was started); new 1080p should become PENDING
      await waitForPresetState(
        page, STANDARD_PRESET, 1080,
        (state) => state.status === 'PENDING' && state.hasCancel,
        '1080p pending after requeue (tariff delay/instance limit)',
      );
      await expectPresetStatusHelpIcon(page, STANDARD_PRESET, {
        statusText: 'PENDING',
        tooltipText: "Why isn't my video transcoding?",
      });
      await shot(page, testInfo, '18-1080p-pending-with-tooltip.png');

      // Cancel the pending 1080p task
      const pending1080Row = activeTaskRowByPresetAndHeight(page, STANDARD_PRESET, 1080);
      await pending1080Row.getByRole('button', { name: 'Cancel' }).click({ timeout: UI_TIMEOUT });
      await shot(page, testInfo, '19-1080p-pending-cancel-clicked.png');

      await waitForPresetState(
        page, STANDARD_PRESET, 1080,
        (state) => state.status === 'CANCELLED' && state.hasTranscode,
        '1080p cancelled after pending cancellation',
      );
      await shot(page, testInfo, '20-1080p-pending-cancelled.png');

      await logoutToPublic(page);
      await shot(page, testInfo, '21-user-sign-out-before-upgrade.png');

      // Phase 4 — Admin filters the isolated user and upgrades tariff to Premium.
      await loginAsAdmin(page);
      await expectTabsVisible(page);
      await openAdminDashboardFromHome(page);
      await filterUsersByEmail(page, run.userLocalPart, testInfo, '22-admin-users-filtered-before-upgrade.png');
      await setTariffForFilteredUser(page, run.userLocalPart, 'Premium', testInfo, '23-admin-user-upgraded-to-premium.png');
      await logoutToPublic(page);
      await shot(page, testInfo, '24-admin-sign-out-after-upgrade.png');

      // Phase 5 — Premium tariff: start 1080p and 720p, wait for completion, download, delete video.
      await loginAsCredentials(page, run.userEmail, run.userPassword);
      await expectTabsVisible(page);
      await openVideoDetailsByTitle(page, run.videoBaseName);
      await shot(page, testInfo, '25-user-video-card-reopened.png');

      // Start transcoding for each required task (restart from cancelled row or start fresh)
      for (const task of REQUIRED_TASKS) {
        await startTaskForHeight(page, task.title, task.height);
        await shot(page, testInfo, `26-transcode-started-${task.height}p.png`);
        await page.waitForTimeout(500);
      }

      await waitForAllPresets(
        page,
        REQUIRED_TASKS,
        (state) => state.hasCancel && (state.status === 'PENDING' || state.status === 'PROCESSING' || state.status === 'STARTING'),
        'all required tasks to become pending/processing with cancel buttons',
        240000,
        2000,
      );
      await shot(page, testInfo, '27-required-tasks-started.png');

      await waitForAllPresets(
        page,
        REQUIRED_TASKS,
        (state) => state.status === 'COMPLETED' && state.hasDownload,
        'all required tasks to complete with download links',
        60 * 1000,
        5000,
      );
      await shot(page, testInfo, '28-required-tasks-completed.png');

      for (const task of REQUIRED_TASKS) {
        await clickDownloadAndVerify(page, task.title, task.height);
        await shot(page, testInfo, `29-download-verified-${task.height}p.png`);
      }

      await openHome(page);
      await openVideosTab(page);
      await expectVideosTableVisible(page);
      const deleted = await deleteVideoFromListIfPresent(page, run.videoBaseName, testInfo, '30-user-video-deleted.png');
      expect(deleted).toBe(true);
      videoDeleted = true;

      await logoutToPublic(page);
      await shot(page, testInfo, '31-user-final-sign-out.png');

      // Phase 6 — Admin removes the isolated user.
      await loginAsAdmin(page);
      await openAdminDashboardFromHome(page);
      const deletedUser = await deleteUserByEmail(page, run.userLocalPart, testInfo, '32-admin-user-deleted.png');
      expect(deletedUser).toBe(true);
      userDeleted = true;

      await logoutToPublic(page);
      await shot(page, testInfo, '33-admin-final-sign-out.png');
    } finally {
      try {
        const sseMessages = await page.evaluate(() => (window.__mercure_messages || []));
        await testInfo.attach('mercure-sse.json', {
          body: Buffer.from(JSON.stringify(sseMessages, null, 2), 'utf-8'),
          contentType: 'application/json',
        });
      } catch (error) {
        // ignore attachment errors
      }

      if (!videoDeleted && userCreated) {
        try {
          await ensureLoggedOut(page);
          await loginAsCredentials(page, run.userEmail, run.userPassword);
          await expectTabsVisible(page);
          videoDeleted = await deleteVideoFromListIfPresent(page, run.videoBaseName, testInfo, 'zz-cleanup-video-deleted.png');
          await logoutToPublic(page).catch(() => {});
        } catch (error) {
          // ignore cleanup errors
        }
      }

      if (!userDeleted && userCreated) {
        try {
          await ensureLoggedOut(page);
          await loginAsAdmin(page);
          await openAdminDashboardFromHome(page);
          userDeleted = await deleteUserByEmail(page, run.userLocalPart, testInfo, 'zz-cleanup-user-deleted.png');
          await logoutToPublic(page).catch(() => {});
        } catch (error) {
          // ignore cleanup errors
        }
      }

      await capture.flushAndAttach();
    }
  });
});
