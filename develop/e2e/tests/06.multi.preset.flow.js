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
  getAllPresetTitles,
  clickTranscodeForPreset,
  expectPresetStatus,
  waitForAllPresetsToComplete,
  presetRow,
  logoutToPublic,
  openAdminDashboardFromHome,
  assignTariffToUser,
  createOrUpdatePreset,
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
  const newPreset = { title: '720p', width: 1280, height: 720, codec: 'h264', bitrate: 3 };
  const allPresets = ['180p', 'FHD', '720p'];

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
    await expectDetailsValue(page, 'Extension');
    await expectDetailsValue(page, 'Created At');
    await shot(page, testInfo, '04-video-details.png');

    // Step 5 — Wait for poster image and meta duration to be ready
    await waitForPosterAndMeta(page, testInfo, '05-poster-meta-attempt');
    await shot(page, testInfo, '05-poster-meta-ready.png');

    // Step 6 — Find all current presets and click Transcode on each
    const initialPresetTitles = await getAllPresetTitles(page);
    for (const title of initialPresetTitles) {
      await clickTranscodeForPreset(page, title);
      await shot(page, testInfo, `06-transcode-clicked-${title}.png`);
    }

    // Step 7 — Wait 3 seconds (tasks are PENDING due to tariff delay / no scheduler run)
    await page.waitForTimeout(3000);

    // Step 8 — Verify all presets are PENDING
    for (const title of initialPresetTitles) {
      await expectPresetStatus(page, title, 'PENDING');
    }
    await shot(page, testInfo, '08-all-presets-pending.png');

    // Step 9 — Sign out
    await logoutToPublic(page);
    await shot(page, testInfo, '09-sign-out-test.png');

    // ── Phase 2: Admin — assign Premium tariff + create 720p preset ───────────

    // Step 10 — Login as admin and open admin dashboard
    await loginAsAdmin(page);
    await shot(page, testInfo, '10-admin-login.png');
    await openAdminDashboardFromHome(page);

    // Step 11 — Change test@test.com tariff to Premium
    await assignTariffToUser(page, testUserEmail, 'Premium', testInfo, '11-premium-assigned.png');

    // Step 12 — Create new preset 720p (width=1280, height=720, bitrate=3)
    await createOrUpdatePreset(page, newPreset, testInfo);
    await shot(page, testInfo, '12-preset-720p-created.png');

    // Step 13 — Return to main page and sign out
    await openHome(page);
    await shot(page, testInfo, '13-home-after-admin.png');
    await logoutToPublic(page);
    await shot(page, testInfo, '13-sign-out-admin.png');

    // ── Phase 3: Test user — full transcode flow with all 3 presets ───────────

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

    // Step 16 — Verify preset statuses: 180p and FHD still PENDING, 720p has No task
    await expectPresetStatus(page, '180p', 'PENDING');
    await expectPresetStatus(page, 'FHD', 'PENDING');
    await expectPresetStatus(page, '720p', 'No task');
    await shot(page, testInfo, '16-statuses-180p-fhd-pending-720p-notask.png');

    // Step 17 — Click Transcode on 720p; this triggers the scheduler which starts all eligible tasks
    await clickTranscodeForPreset(page, '720p');
    await shot(page, testInfo, '17-transcode-720p-clicked.png');

    // Step 18 — Poll with 5s intervals until all three presets reach COMPLETED
    await waitForAllPresetsToComplete(page, allPresets);
    await shot(page, testInfo, '18-all-presets-completed.png');

    // Step 19 — Verify Download button is visible for every preset row
    for (const title of allPresets) {
      await expect(presetRow(page, title).getByRole('link', { name: 'Download' })).toBeVisible({ timeout: UI_TIMEOUT });
    }
    await shot(page, testInfo, '19-all-download-buttons-visible.png');

    // Step 20 — Download each file and verify filename matches "{baseName} - {presetName}"
    for (const title of allPresets) {
      const row = presetRow(page, title);
      const expectedFilename = `${baseName} - ${title}`;
      await expectRowDownloadFilename(row, expectedFilename);
      await clickDownloadAndVerifyMp4(page, row);
      await shot(page, testInfo, `20-download-verified-${title}.png`);
    }

    // Step 21 — Sign out
    await logoutToPublic(page);
    await shot(page, testInfo, '21-sign-out-final.png');
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
