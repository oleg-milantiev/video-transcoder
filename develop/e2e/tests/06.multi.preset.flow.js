const { test, expect } = require('@playwright/test');
const { attachConsoleCapture } = require('../consoleCapture');
const {
  UI_TIMEOUT,
  NAV_TIMEOUT,
  loginAsTest,
  loginAsAdmin,
  uploadFixtureAsName,
  openVideosTab,
  expectVideosTableVisible,
  videoRowByTitle,
  waitForVideoDetailsVisible,
  expectDetailsValue,
  waitForPosterAndMeta,
  clickTranscodeForPreset,
  expectPresetStatus,
  expectPresetStatusHelpIcon,
  waitForAllPresetsToComplete,
  presetRow,
  logoutToPublic,
  openAdminDashboardFromHome,
  assignTariffToUser,
  openHome,
  clickDownloadAndVerifyMp4,
  expectRowDownloadFilename,
  shot,
} = require('../helpers');

test('multi-preset flow: upload, trigger tasks, admin tariff + new preset, full transcode to download', async ({ page }, testInfo) => {
  const capture = attachConsoleCapture(page, testInfo, { maxBodyChars: 4000 });
  await capture.start();

  const sourceVideoFileName = '2022_10_04_Two_Maxes.mp4';
  const uploadedVideoName = '2022_10_04_Two_Maxes-06.mp4';
  const baseName = uploadedVideoName.substring(0, uploadedVideoName.lastIndexOf('.'));
  const testUserEmail = 'test@test.com';
  const allPresets = ['Standard video Quality'];
  const singlePreset = 'Standard video Quality';

  try {
    // ── Phase 1: Login as test, upload, verify video card ─────────────────────

    // Step 1 — Login as test user
    await loginAsTest(page);
    await shot(page, testInfo, '01-login-test.png');

    // Step 2 — Upload source video under the -06 suffix
    await uploadFixtureAsName(page, sourceVideoFileName, uploadedVideoName);
    await shot(page, testInfo, '02-uploaded.png');

    // Step 3 — Open Videos tab and confirm the -06 video appears in the list
    await openVideosTab(page);
    await expectVideosTableVisible(page);
    const videoRow = videoRowByTitle(page, baseName);
    await expect(videoRow).toBeVisible({ timeout: NAV_TIMEOUT });
    await shot(page, testInfo, '03-video-in-list.png');

    // Step 4 — Click the row and verify core detail fields
    await videoRow.click({ timeout: UI_TIMEOUT });
    await waitForVideoDetailsVisible(page);
    await expectDetailsValue(page, 'Title');
    await expectDetailsValue(page, 'Created');
    await shot(page, testInfo, '04-video-details.png');

    // Step 5 — Wait for poster image and meta duration to be ready
    await waitForPosterAndMeta(page, testInfo, '05-poster-meta-attempt');
    await shot(page, testInfo, '05-poster-meta-ready.png');

    // Step 6 — Click Transcode twice on the single Free-tier preset to create two PENDING tasks
    await clickTranscodeForPreset(page, singlePreset);
    await shot(page, testInfo, '06-transcode-first-click.png');
    await clickTranscodeForPreset(page, singlePreset);
    await shot(page, testInfo, '06-transcode-second-click.png');

    // Step 7 — Wait 3 seconds (tasks are PENDING: tariff allows only one concurrent transcode per hour)
    await page.waitForTimeout(3000);

    // Step 8 — Verify tasks for "Standard video Quality" are PENDING
    await expectPresetStatus(page, singlePreset, 'PENDING');
    await shot(page, testInfo, '08-preset-pending.png');

    // Step 9 — Verify the ? help icon is shown near PENDING and exposes the pending-transcode tooltip
    await expectPresetStatusHelpIcon(page, singlePreset, {
      statusText: 'PENDING',
      tooltipText: "Why isn't my video transcoding?",
    });
    await shot(page, testInfo, '09-pending-status-tooltip.png');

    // Step 10 — Sign out
    await logoutToPublic(page);
    await shot(page, testInfo, '10-sign-out-test.png');

    // ── Phase 2: Admin — assign Premium tariff ───────────────────────────────

    // Step 11 — Login as admin and open admin dashboard
    await loginAsAdmin(page);
    await shot(page, testInfo, '11-admin-login.png');
    await openAdminDashboardFromHome(page);

    // Step 12 — Change test@test.com tariff to Premium
    await assignTariffToUser(page, testUserEmail, 'Premium', testInfo, '12-premium-assigned.png');

    // Step 13 — Return to main page and sign out
    await openHome(page);
    await shot(page, testInfo, '13-home-after-admin.png');
    await logoutToPublic(page);
    await shot(page, testInfo, '13-sign-out-admin.png');

    // ── Phase 3: Test user — full transcode flow ──────────────────────────────

    // Step 14 — Login as test user again
    await loginAsTest(page);
    await shot(page, testInfo, '14-login-test-again.png');

    // Step 15 — Navigate to Videos, find the -06 video and open its card
    await openVideosTab(page);
    await expectVideosTableVisible(page);
    const videoRow2 = videoRowByTitle(page, baseName);
    await expect(videoRow2).toBeVisible({ timeout: NAV_TIMEOUT });
    await videoRow2.click({ timeout: UI_TIMEOUT });
    await waitForVideoDetailsVisible(page);
    await shot(page, testInfo, '15-video-card-reopened.png');

    // Step 16 — Click Transcode on the first available resolution of "Standard video Quality"
    // now unlocked by Premium tariff (e.g. 1080p — 720p is always disabled as origin resolution);
    // this triggers the scheduler which starts all eligible tasks
    await clickTranscodeForPreset(page, singlePreset);
    await shot(page, testInfo, '16-transcode-clicked.png');

    // Step 17 — Poll until "Standard video Quality" reaches COMPLETED
    await waitForAllPresetsToComplete(page, allPresets);
    await shot(page, testInfo, '17-all-presets-completed.png');

    // Step 18 — Verify Download button is visible for every preset row
    for (const title of allPresets) {
      await expect(presetRow(page, title).getByRole('link', { name: 'Download' })).toBeVisible({ timeout: UI_TIMEOUT });
    }
    await shot(page, testInfo, '18-all-download-buttons-visible.png');

    // Step 19 — Download each file and verify filename matches codec+resolution format
    // Premium unlocked 2160p; that was the resolution clicked in step 16
    const presetDownloadSuffix = {
      'Standard video Quality': 'aac-h264-2160p.mp4',
    };
    for (const title of allPresets) {
      const row = presetRow(page, title);
      const suffix = presetDownloadSuffix[title] || title;
      const expectedFilename = `${baseName}-${suffix}`;
      await expectRowDownloadFilename(row, expectedFilename);
      await clickDownloadAndVerifyMp4(page, row);
      await shot(page, testInfo, `19-download-verified-${title}.png`);
    }

    // Step 20 — Sign out
    await logoutToPublic(page);
    await shot(page, testInfo, '20-sign-out-final.png');
  } finally {
    try {
      const sseMessages = await page.evaluate(() => (window.__mercure_messages || []));
      await testInfo.attach('mercure-sse.json', {
        body: Buffer.from(JSON.stringify(sseMessages, null, 2), 'utf-8'),
        contentType: 'application/json',
      });
    } catch (e) {
      // ignore
    }
    await capture.flushAndAttach();
  }
});
