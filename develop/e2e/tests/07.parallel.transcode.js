const { test, expect } = require('@playwright/test');
const { attachConsoleCapture } = require('../consoleCapture');
const {
  UI_TIMEOUT,
  NAV_TIMEOUT,
  loginAsTest,
  uploadFixtureAsName,
  openVideosTab,
  expectVideosTableVisible,
  videoRowByTitle,
  waitForVideoDetailsVisible,
  waitForPosterAndMeta,
  clickTranscodeForPreset,
  waitForAllPresetsProcessingWithProgress,
  waitForAllPresetsToComplete,
  presetRow,
  expectRowDownloadFilename,
  clickDownloadAndVerifyMp4,
  logoutToPublic,
  shot,
} = require('../helpers');

test('parallel transcode: FHD and 720p process simultaneously on Premium tariff (2 workers)', async ({ page }, testInfo) => {
  const capture = attachConsoleCapture(page, testInfo, { maxBodyChars: 4000 });
  await capture.start();

  const sourceVideoFileName = '2022_10_04_Two_Maxes.mp4';
  const uploadedVideoName = '2022_10_04_Two_Maxes-07.mp4';
  const baseName = uploadedVideoName.substring(0, uploadedVideoName.lastIndexOf('.'));
  const parallelPresets = ['FHD', '720p'];

  try {
    // Step 1 — Login as test user (has Premium tariff, instance=2 after test 06)
    await loginAsTest(page);
    await shot(page, testInfo, '01-login-test.png');

    // Step 2 — Upload source video under the -07 suffix
    await uploadFixtureAsName(page, sourceVideoFileName, uploadedVideoName);
    await shot(page, testInfo, '02-uploaded.png');

    // Step 3 — Open Videos tab and find the -07 row
    await openVideosTab(page);
    await expectVideosTableVisible(page);
    const videoRow = videoRowByTitle(page, baseName);
    await expect(videoRow).toBeVisible({ timeout: NAV_TIMEOUT });
    await shot(page, testInfo, '03-video-in-list.png');

    // Step 4 — Open video card
    await videoRow.click({ timeout: UI_TIMEOUT });
    await waitForVideoDetailsVisible(page);
    await shot(page, testInfo, '04-video-details.png');

    // Step 5 — Wait for poster and meta to be ready
    await waitForPosterAndMeta(page, testInfo, '05-poster-meta-attempt');
    await shot(page, testInfo, '05-poster-meta-ready.png');

    // Step 6 — Click Transcode on FHD and 720p (both will be scheduled immediately: Premium delay=0)
    for (const title of parallelPresets) {
      await clickTranscodeForPreset(page, title);
      await shot(page, testInfo, `06-transcode-clicked-${title}.png`);
    }

    // Step 7 — Poll every 1s until BOTH presets are simultaneously PROCESSING with progress > 0
    //          This confirms the two worker replicas picked them up in parallel.
    await waitForAllPresetsProcessingWithProgress(page, parallelPresets);
    await shot(page, testInfo, '07-both-processing-with-progress.png');

    // Step 8 — Poll every 1s until BOTH presets reach COMPLETED
    await waitForAllPresetsToComplete(page, parallelPresets, 120, 1000);
    await shot(page, testInfo, '08-both-completed.png');

    // Step 9 — Verify Download button is visible for each preset
    for (const title of parallelPresets) {
      await expect(presetRow(page, title).getByRole('link', { name: 'Download' })).toBeVisible({ timeout: UI_TIMEOUT });
    }
    await shot(page, testInfo, '09-download-buttons-visible.png');

    // Step 10 — Download each file and verify filename matches "{baseName} - {presetTitle}"
    for (const title of parallelPresets) {
      const row = presetRow(page, title);
      const expectedFilename = `${baseName} - ${title}`;
      await expectRowDownloadFilename(row, expectedFilename);
      await clickDownloadAndVerifyMp4(page, row);
      await shot(page, testInfo, `10-download-verified-${title}.png`);
    }

    // Step 11 — Sign out
    await logoutToPublic(page);
    await shot(page, testInfo, '11-sign-out.png');
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

