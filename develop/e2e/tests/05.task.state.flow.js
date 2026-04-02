const { test, expect } = require('@playwright/test');
const { attachConsoleCapture } = require('../consoleCapture');
const {
  UI_TIMEOUT,
  NAV_TIMEOUT,
  getAdminCredentials,
  openHome,
  openSignIn,
  fillSignInCredentials,
  submitSignIn,
  openAdminDashboardFromHome,
  assignTariffToUser,
  uploadFixtureAsName,
  openVideosTab,
  expectVideosTableVisible,
  activeVideoRowByTitle,
  waitForVideoDetailsVisible,
  presetRow,
  readPresetTaskState,
  logoutToPublic,
  shot,
} = require('../helpers');

test('task state flow with 4k preset: progress, cancel, restart, complete', async ({ page }, testInfo) => {
  // Step 1 — Configure local timeouts for this long-flow test and admin credentials
  // Local timeouts for this long-flow test only.
  page.setDefaultTimeout(UI_TIMEOUT);
  page.setDefaultNavigationTimeout(NAV_TIMEOUT);

  const { email, password } = getAdminCredentials();
  const sourceVideoFileName = '2022_10_04_Two_Maxes.mp4';
  const uploadedVideoName = '2022_10_04_Two_Maxes-05.mp4';
  const baseFileName = uploadedVideoName.substring(0, uploadedVideoName.lastIndexOf('.'));
  const presetTitle = 'FHD';

  // Step 2 — start console capture for this test
  const capture = attachConsoleCapture(page, testInfo, { maxBodyChars: 4000 });
  await capture.start();

  try {
    // Step 3 — Login as admin
    await openHome(page);
    await openSignIn(page);
    await fillSignInCredentials(page, email, password);
    await submitSignIn(page);
    await expect(page.getByRole('button', { name: 'Videos' })).toBeVisible({ timeout: UI_TIMEOUT });
    await shot(page, testInfo, '01-login-success.png');

    // Step 4 — Ensure two quick transcodes are allowed in this scenario (assign Premium tariff)
    await openAdminDashboardFromHome(page);
    await assignTariffToUser(page, email, 'Premium');
    await shot(page, testInfo, '01b-admin-premium-tariff-assigned.png');

    // Step 5 — Re-login to refresh security token context after tariff update
    await page.goto('/logout', { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
    await openSignIn(page);
    await fillSignInCredentials(page, email, password);
    await submitSignIn(page);
    await expect(page.getByRole('button', { name: 'Videos' })).toBeVisible({ timeout: UI_TIMEOUT });
    await page.goto('/?tab=videos', { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });

    // Step 6 — Upload the test video with a custom name
    await uploadFixtureAsName(page, sourceVideoFileName, uploadedVideoName);
    await shot(page, testInfo, '01c-reupload-with-05-name.png');

    await openVideosTab(page);
    await expectVideosTableVisible(page);

    const videoRow = activeVideoRowByTitle(page, baseFileName);
    await expect(videoRow).toBeVisible({ timeout: UI_TIMEOUT });
    await videoRow.click({ timeout: UI_TIMEOUT });

    // Step 7 — Open video details and verify the FHD preset is present
    await waitForVideoDetailsVisible(page);
    await expect(presetRow(page, presetTitle)).toBeVisible({ timeout: UI_TIMEOUT });
    await shot(page, testInfo, '02-video-details-with-fhd-preset.png');

    const startButton = presetRow(page, presetTitle).getByRole('button', { name: 'Transcode' });
    await expect(startButton).toBeVisible({ timeout: UI_TIMEOUT });
    // Step 8 — Start FHD transcode
    await startButton.click({ timeout: UI_TIMEOUT });

    await expect
      .poll(async () => (await readPresetTaskState(page, presetTitle)).status, {
        timeout: UI_TIMEOUT,
        intervals: [1000, 2000, 5000],
      })
      .toMatch(/PENDING|PROCESSING|COMPLETED/);
    await shot(page, testInfo, '03-transcode-started.png');

    let prevProgress = -1;
    let sawProgressIncrease = false;
    let cancellationSent = false;

    // Step 9 — Monitor progress; when PROCESSING appears, send Cancel to test cancel-in-processing flow
    for (let attempt = 1; attempt <= 10; attempt += 1) {
      const state = await readPresetTaskState(page, presetTitle);

      if (prevProgress >= 0 && state.progress > prevProgress) {
        sawProgressIncrease = true;
      }
      if (state.progress > prevProgress) {
        prevProgress = state.progress;
      }

      if (state.status === 'COMPLETED') {
        throw new Error('4k task completed before cancellation was sent; flow cannot validate cancel-in-processing.');
      }

      if (state.status === 'PROCESSING') {
        const cancelButton = presetRow(page, presetTitle).getByRole('button', { name: 'Cancel' });
        await expect(cancelButton).toBeVisible({ timeout: UI_TIMEOUT });
        await cancelButton.click({ timeout: UI_TIMEOUT });
        cancellationSent = true;
        break;
      }

      // wait for realtime update (worker emits every ~5s)
      await page.waitForTimeout(6000);
    }

    expect(cancellationSent).toBe(true);
    await shot(page, testInfo, '04-cancel-request-sent-after-progress-growth.png');

    let cancelled = false;
    // Step 10 — Wait for CANCELLED state to be reached and verify UI shows cancelled status
    for (let attempt = 1; attempt <= 60; attempt += 1) {
      const state = await readPresetTaskState(page, presetTitle);
      if (state.status === 'CANCELLED') {
        cancelled = true;
        break;
      }

      // wait for realtime update (worker emits every ~5s)
      await page.waitForTimeout(6000);
    }

    expect(cancelled).toBe(true);
    const cancelledRow = presetRow(page, presetTitle);
    await expect(cancelledRow.getByRole('link', { name: 'Download' })).toHaveCount(0, { timeout: UI_TIMEOUT });
    await expect(cancelledRow.getByRole('button', { name: 'Transcode' })).toBeVisible({ timeout: UI_TIMEOUT });
    await shot(page, testInfo, '05-task-cancelled.png');

    // Step 11 — Restart the transcode after cancellation
    await cancelledRow.getByRole('button', { name: 'Transcode' }).click({ timeout: UI_TIMEOUT });

    await expect
      .poll(async () => (await readPresetTaskState(page, presetTitle)).status, {
        timeout: 45000,
        intervals: [1000, 2000, 5000],
      })
      .toMatch(/PENDING|PROCESSING|COMPLETED/);

    let prevRestartProgress = -1;
    let sawRestartProgressIncrease = false;
    let completed = false;

    // Step 12 — Wait for completion of restarted task and verify progress increased during run
    for (let attempt = 1; attempt <= 10; attempt += 1) {
      const state = await readPresetTaskState(page, presetTitle);

      if (prevRestartProgress >= 0 && state.progress > prevRestartProgress) {
        sawRestartProgressIncrease = true;
      }
      if (state.progress > prevRestartProgress) {
        prevRestartProgress = state.progress;
      }

      if (state.status === 'COMPLETED') {
        completed = true;
        break;
      }

      // wait for realtime update (worker emits every ~5s)
      await page.waitForTimeout(5000);
    }

    expect(completed).toBe(true);
    expect(sawRestartProgressIncrease).toBe(true);

    const completedRow = presetRow(page, presetTitle);
    await expect(completedRow.getByRole('link', { name: 'Download' })).toBeVisible({ timeout: UI_TIMEOUT });
    await shot(page, testInfo, '06-restart-completed-with-download.png');

    // Step 13 — Sign out and finish the test
    await logoutToPublic(page);
    await shot(page, testInfo, '07-sign-out.png');
  } finally {
    // collect SSE messages captured by probe and attach them
    try {
      const sseMessages = await page.evaluate(() => (window.__mercure_messages || []));
      await testInfo.attach('mercure-sse.json', {
        body: Buffer.from(JSON.stringify(sseMessages, null, 2), 'utf-8'),
        contentType: 'application/json'
      });
    } catch (e) {
      // ignore
    }

    // flush and attach console log even when the test fails early
    await capture.flushAndAttach();
  }
});

