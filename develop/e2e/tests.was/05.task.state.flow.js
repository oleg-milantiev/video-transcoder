const { test, expect } = require('@playwright/test');
const {
  UI_TIMEOUT,
  NAV_TIMEOUT,
  getAdminCredentials,
  loginAsAdmin,
  openAdminDashboardFromHome,
  assignTariffToUser,
  uploadFixtureAsName,
  openVideosTab,
  expectVideosTableVisible,
  activeVideoRowByTitle,
  waitForVideoDetailsVisible,
  presetBlock,
  presetRow,
  readPresetTaskState,
  waitForPresetTaskStatus,
  pollUntilCompletedWithProgressTracking,
  logoutToPublic,
  shot,
  switchToVideoTab,
  switchToCustomGoal,
  attachSseMessages,
} = require('../helpers');

test('task state flow with FHD preset: progress, cancel, restart, complete', async ({ page }, testInfo) => {
  // Step 1 — Configure local timeouts for this long-flow test
  page.setDefaultTimeout(UI_TIMEOUT);
  page.setDefaultNavigationTimeout(NAV_TIMEOUT);

  const { email } = getAdminCredentials();
  const sourceVideoFileName = '2022_10_04_Two_Maxes.mp4';
  const uploadedVideoName = '2022_10_04_Two_Maxes-05.mp4';
  const baseFileName = uploadedVideoName.substring(0, uploadedVideoName.lastIndexOf('.'));
  const presetTitle = 'High video Quality, High Efficiency Audio';
  // h265/opus/mp4 — slower codec ensures we can catch PROCESSING state before COMPLETED

  try {
    // Step 3 — Login as admin
    await loginAsAdmin(page);
    await shot(page, testInfo, '01-login-success.png');

    // Step 4 — Ensure two quick transcodes are allowed in this scenario (assign Premium tariff)
    await openAdminDashboardFromHome(page);
    await assignTariffToUser(page, email, 'Premium');
    await shot(page, testInfo, '01b-admin-premium-tariff-assigned.png');

    // Step 5 — Re-login to refresh security token context after tariff update
    await page.goto('/logout', { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
    await loginAsAdmin(page);
    await page.goto('/?tab=videos', { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });

    // Step 6 — Upload the test video with a custom name
    await uploadFixtureAsName(page, sourceVideoFileName, uploadedVideoName);
    await shot(page, testInfo, '01c-reupload-with-05-name.png');

    await openVideosTab(page);
    await expectVideosTableVisible(page);

    const videoRow = activeVideoRowByTitle(page, baseFileName);
    await expect(videoRow).toBeVisible({ timeout: UI_TIMEOUT });
    await videoRow.click({ timeout: UI_TIMEOUT });

    // Step 7 — Open video details and verify the FHD preset block is present
    await waitForVideoDetailsVisible(page);
    await switchToVideoTab(page, 'transcode');
    await switchToCustomGoal(page);
    await expect(presetBlock(page, presetTitle)).toBeVisible({ timeout: UI_TIMEOUT });
    await shot(page, testInfo, '02-video-details-with-fhd-preset.png');

    // 4th button = 1280×720
    const startButton = presetBlock(page, presetTitle).locator('button.btn-outline-primary:not([disabled])').nth(3);
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
    let cancellationSent = false;

    // Step 9 — Monitor progress; when PROCESSING appears, send Cancel to test cancel-in-processing flow
    for (let attempt = 1; attempt <= 10; attempt += 1) {
      const state = await readPresetTaskState(page, presetTitle);

      if (state.progress > prevProgress) {
        prevProgress = state.progress;
      }

      if (state.status === 'COMPLETED') {
        throw new Error('Task completed before cancellation was sent; flow cannot validate cancel-in-processing.');
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

    // Step 10 — Wait for CANCELLED state to be reached and verify UI shows cancelled status
    await waitForPresetTaskStatus(page, presetTitle, 'CANCELLED', { maxAttempts: 60, pollMs: 6000 });

    const cancelledRow = presetRow(page, presetTitle);
    await expect(cancelledRow.getByRole('link', { name: 'Download' })).toHaveCount(0, { timeout: UI_TIMEOUT });
    await expect(cancelledRow.getByRole('button', { name: 'Transcode' })).toBeVisible({ timeout: UI_TIMEOUT });
    await shot(page, testInfo, '05-task-cancelled.png');

    // Step 11 — Restart the transcode after cancellation
    await cancelledRow.getByRole('button', { name: 'Transcode' }).click({ timeout: UI_TIMEOUT });

    await expect
      .poll(async () => (await readPresetTaskState(page, presetTitle, { preferActive: true })).status, {
        timeout: 45000,
        intervals: [1000, 2000, 5000],
      })
      .toMatch(/PENDING|PROCESSING|COMPLETED/);

    // Step 12 — Wait for completion of restarted task and verify progress increased during run
    const { completed, sawProgressIncrease } = await pollUntilCompletedWithProgressTracking(
      page, presetTitle, { maxAttempts: 10, pollMs: 5000, preferActive: true }
    );

    expect(completed).toBe(true);
    expect(sawProgressIncrease).toBe(true);

    const completedRow = presetRow(page, presetTitle);
    await expect(completedRow.getByRole('link', { name: 'Download' })).toBeVisible({ timeout: UI_TIMEOUT });
    await shot(page, testInfo, '06-restart-completed-with-download.png');

    // Step 13 — Sign out and finish the test
    await logoutToPublic(page);
    await shot(page, testInfo, '07-sign-out.png');
  } finally {
    await attachSseMessages(page, testInfo);
    await capture.flushAndAttach();
  }
});
