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
  readPresetTaskState,
  expectPresetStatusHelpIcon,
  clickAndAcceptConfirm,
  buildRunContext,
} = require('../helpers');

const REQUIRED_PRESETS = ['180p', 'HD, 3Mbps', 'Full HD, 6Mbps'];

function normalizeStatus(status) {
  return String(status || '').trim().toUpperCase();
}

async function readPresetUiState(page, title) {
  const row = presetRow(page, title);
  await expect(row).toBeVisible({ timeout: UI_TIMEOUT });

  const transcodeButton = row.getByRole('button', { name: 'Transcode' });
  const cancelButton = row.getByRole('button', { name: 'Cancel' });
  const downloadLink = row.getByRole('link', { name: 'Download' });
  const { status, progress } = await readPresetTaskState(page, title);

  return {
    row,
    title,
    rawStatus: status,
    status: normalizeStatus(status),
    progress,
    hasTranscode: (await transcodeButton.count()) > 0 && await transcodeButton.first().isVisible().catch(() => false),
    hasCancel: (await cancelButton.count()) > 0 && await cancelButton.first().isVisible().catch(() => false),
    hasDownload: (await downloadLink.count()) > 0 && await downloadLink.first().isVisible().catch(() => false),
  };
}

async function waitForPresetState(page, title, matcher, description, timeout = 180000, pollMs = 2000) {
  const startedAt = Date.now();
  let lastState = null;

  while (Date.now() - startedAt < timeout) {
    lastState = await readPresetUiState(page, title);
    if (matcher(lastState)) {
      return lastState;
    }
    await page.waitForTimeout(pollMs);
  }

  throw new Error(`${description} for preset "${title}" not reached. Last state: ${JSON.stringify(lastState)}`);
}

async function waitForAllPresets(page, titles, matcher, description, timeout = 900000, pollMs = 3000) {
  const startedAt = Date.now();
  let states = [];

  while (Date.now() - startedAt < timeout) {
    states = [];
    let allMatched = true;

    for (const title of titles) {
      const state = await readPresetUiState(page, title);
      states.push(state);
      if (!matcher(state)) {
        allMatched = false;
      }
    }

    if (allMatched) {
      return states;
    }

    await page.waitForTimeout(pollMs);
  }

  throw new Error(`${description} not reached. Last states: ${JSON.stringify(states)}`);
}

function resolveRequiredPresets(allTitles) {
  for (const presetName of REQUIRED_PRESETS) {
    if (!allTitles.includes(presetName)) {
      throw new Error(`Required preset "${presetName}" was not found. Available presets: ${allTitles.join(', ')}`);
    }
  }

  const resolved = {};
  for (const presetName of REQUIRED_PRESETS) {
    resolved[presetName] = presetName;
  }

  return resolved;
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

async function clickDownloadAndExpectExactFilename(page, row, expectedFilename) {
  const downloadLink = row.getByRole('link', { name: 'Download' });
  await expect(downloadLink).toBeVisible({ timeout: UI_TIMEOUT });

  const href = await downloadLink.getAttribute('href');
  if (!href) {
    throw new Error('Download link href is empty');
  }

  const downloadPromise = page.waitForEvent('download', { timeout: 15000 });
  await downloadLink.click({ timeout: UI_TIMEOUT });
  const download = await downloadPromise;

  expect(download.suggestedFilename()).toBe(expectedFilename);

  const downloadUrl = new URL(href, page.url()).toString();
  const response = await page.request.get(downloadUrl, {
    failOnStatusCode: false,
    maxRedirects: 0,
    timeout: NAV_TIMEOUT,
  });
  expect(response.status()).toBeLessThan(400);

  const location = response.headers().location || '';
  if (location) {
    expect(location.toLowerCase()).toContain('.mp4');
  } else {
    expect(downloadUrl.toLowerCase()).toContain('.mp4');
  }
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
  if ((await row.count()) === 0) {
    return false;
  }

  await expect(row).toBeVisible({ timeout: UI_TIMEOUT });
  const deleteButton = row.getByRole('button', { name: 'Delete' });
  if ((await deleteButton.count()) === 0) {
    return false;
  }

  await clickAndAcceptConfirm(page, deleteButton, 'Delete this video?');
  await expect.poll(async () => (await row.locator('td.video-title-deleted').count()) > 0).toBeTruthy();
  await shot(page, testInfo, screenshotName);
  return true;
}

test.describe('prod-safe isolated smoke', () => {
  test.setTimeout(5 * 60 * 1000);

  test('creates isolated user, verifies safe flow, upgrades tariff, downloads outputs, and cleans up', async ({ page }, testInfo) => {
    page.setDefaultTimeout(UI_TIMEOUT);
    page.setDefaultNavigationTimeout(NAV_TIMEOUT);

    const capture = attachConsoleCapture(page, testInfo, { maxBodyChars: 4000 });
    await capture.start();

    const run = buildRunContext();
    const expectedUploadHint = 'Storage: 0% used (0 MB of 1 GB). Max resolution: 1920×1280. Max file size: 100 MB.';
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

      const allPresetTitles = await getAllPresetTitles(page);
      const requiredPresets = resolveRequiredPresets(allPresetTitles);

      for (const actualTitle of Object.values(requiredPresets)) {
        const row = presetRow(page, actualTitle);
        const taskState = await readPresetTaskState(page, actualTitle);
        await expect(row.locator('td').nth(4)).toContainText('Expected size:', { timeout: UI_TIMEOUT });
        await expect(row.getByRole('button', { name: 'Transcode' })).toBeVisible({ timeout: UI_TIMEOUT });
        expect(taskState.status).toBe('No task');
      }
      await shot(page, testInfo, '11-required-presets-present.png');

      // Phase 3 — Free tariff: one task starts immediately, the next quick retry stays pending.
      const fullHdTitle = requiredPresets['Full HD, 6Mbps'];
      await presetRow(page, fullHdTitle).getByRole('button', { name: 'Transcode' }).click({ timeout: UI_TIMEOUT });
      await shot(page, testInfo, '12-full-hd-transcode-clicked.png');

      await waitForPresetState(
        page,
        fullHdTitle,
        (state) => state.status === 'PROCESSING' && state.hasCancel,
        'processing state with cancel button',
      );
      await shot(page, testInfo, '13-full-hd-processing.png');

      await waitForPresetState(
        page,
        fullHdTitle,
        (state) => state.status === 'PROCESSING' && state.progress > 30,
        'processing progress above 30%',
        45000,
      );
      await shot(page, testInfo, '14-full-hd-progress-above-30.png');

      await presetRow(page, fullHdTitle).getByRole('button', { name: 'Cancel' }).click({ timeout: UI_TIMEOUT });
      await shot(page, testInfo, '15-full-hd-cancel-clicked.png');

      await waitForPresetState(
        page,
        fullHdTitle,
        (state) => state.status === 'CANCELLED' && state.hasTranscode,
        'cancelled state with transcode button',
      );
      await shot(page, testInfo, '16-full-hd-cancelled.png');

      await presetRow(page, fullHdTitle).getByRole('button', { name: 'Transcode' }).click({ timeout: UI_TIMEOUT });
      await shot(page, testInfo, '17-full-hd-requeued.png');

      await waitForPresetState(
        page,
        fullHdTitle,
        (state) => state.status === 'PENDING' && state.hasCancel,
        'pending state with cancel button after requeue',
      );
      await expectPresetStatusHelpIcon(page, fullHdTitle, {
        statusText: 'PENDING',
        tooltipText: "Why isn't my video transcoding?",
      });
      await shot(page, testInfo, '18-full-hd-pending-with-tooltip.png');

      await presetRow(page, fullHdTitle).getByRole('button', { name: 'Cancel' }).click({ timeout: UI_TIMEOUT });
      await shot(page, testInfo, '19-full-hd-pending-cancel-clicked.png');

      await waitForPresetState(
        page,
        fullHdTitle,
        (state) => state.status === 'CANCELLED' && state.hasTranscode,
        'cancelled state with transcode button after pending cancellation',
      );
      await shot(page, testInfo, '20-full-hd-pending-cancelled.png');

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

      // Phase 5 — Premium tariff: start all required presets, wait for completion, download, delete video.
      await loginAsCredentials(page, run.userEmail, run.userPassword);
      await expectTabsVisible(page);
      await openVideoDetailsByTitle(page, run.videoBaseName);
      await shot(page, testInfo, '25-user-video-card-reopened.png');

      const requiredPresetTitles = Object.values(requiredPresets);
      for (const actualTitle of requiredPresetTitles) {
        const row = presetRow(page, actualTitle);
        await row.getByRole('button', { name: 'Transcode' }).click({ timeout: UI_TIMEOUT });
        await shot(page, testInfo, `26-transcode-clicked-${actualTitle.replace(/[^a-zA-Z0-9._-]+/g, '_')}.png`);
      }

      await waitForAllPresets(
        page,
        requiredPresetTitles,
        (state) => state.hasCancel && (state.status === 'PENDING' || state.status === 'PROCESSING' || state.status === 'STARTING'),
        'all required presets to become pending/processing with cancel buttons',
        240000,
        2000,
      );
      await shot(page, testInfo, '27-required-presets-started.png');

      await waitForAllPresets(
        page,
        requiredPresetTitles,
        (state) => state.status === 'COMPLETED' && state.hasDownload,
        'all required presets to complete with download links',
        20 * 60 * 1000,
        5000,
      );
      await shot(page, testInfo, '28-required-presets-completed.png');

      for (const actualTitle of requiredPresetTitles) {
        const row = presetRow(page, actualTitle);
        await clickDownloadAndExpectExactFilename(page, row, `${run.videoBaseName} - ${actualTitle}.mp4`);
        await shot(page, testInfo, `29-download-verified-${actualTitle.replace(/[^a-zA-Z0-9._-]+/g, '_')}.png`);
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

