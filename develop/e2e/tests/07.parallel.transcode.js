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
  presetBlock,
  clickDownloadAndVerifyMp4,
  expectRowDownloadFilename,
  logoutToPublic,
  shot,
} = require('../helpers');

test('parallel transcode: 1080p and 720p run simultaneously within Standard preset (Premium, 2 workers)', async ({ page }, testInfo) => {
  const capture = attachConsoleCapture(page, testInfo, { maxBodyChars: 4000 });
  await capture.start();

  const sourceVideoFileName = '2022_10_04_Two_Maxes.mp4';
  const uploadedVideoName = '2022_10_04_Two_Maxes-07.mp4';
  const baseName = uploadedVideoName.substring(0, uploadedVideoName.lastIndexOf('.'));
  const singlePreset = 'Standard video Quality';
  // Both heights run simultaneously on Premium tariff (instance=2, two workers)
  const heights = ['1080p', '720p'];

  // Helper: find task row in the transcoding tasks table by height text in column 1
  function taskRowByHeight(heightLabel) {
    return page.locator('#transcoding-tasks-section table tbody tr', { hasText: heightLabel });
  }

  try {
    // Step 1 — Login as test user (has Premium tariff after test 06)
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

    // Step 6 — Click 1080p and 720p resolution buttons within "Standard video Quality" preset
    //          Premium tariff (delay=0) schedules them immediately
    const block = presetBlock(page, singlePreset);
    await expect(block).toBeVisible({ timeout: UI_TIMEOUT });
    await block.getByRole('button', { name: /1080/ }).first().click({ timeout: UI_TIMEOUT });
    await shot(page, testInfo, '06-transcode-1080p-clicked.png');
    await block.getByRole('button', { name: /720/ }).first().click({ timeout: UI_TIMEOUT });
    await shot(page, testInfo, '06-transcode-720p-clicked.png');

    // Step 7 — Poll every 1 s until BOTH height tasks are simultaneously PROCESSING with progress > 0
    //          This confirms the two worker replicas picked them up in parallel
    await expect.poll(async () => {
      let processingCount = 0;
      for (const h of heights) {
        const row = taskRowByHeight(h).first();
        if (await row.count() === 0) return false;
        const statusRaw = (await row.locator('td').nth(2).innerText()).trim();
        const status = statusRaw.replace(/\s+\?\s*$/, '').trim();
        const progressText = (await row.locator('td').nth(3).innerText()).trim();
        const progressMatch = progressText.match(/(\d+)\s*%/);
        const progress = progressMatch ? Number(progressMatch[1]) : 0;
        if (status === 'PROCESSING' && progress > 0) processingCount++;
      }
      return processingCount >= 2;
    }, { timeout: 90000, intervals: [1000] }).toBeTruthy();
    await shot(page, testInfo, '07-both-processing-with-progress.png');

    // Step 8 — Poll every 1 s until BOTH height tasks reach COMPLETED
    await expect.poll(async () => {
      let completedCount = 0;
      for (const h of heights) {
        const row = taskRowByHeight(h).first();
        if (await row.count() === 0) return false;
        const status = (await row.locator('td').nth(2).innerText()).trim();
        if (status === 'COMPLETED') completedCount++;
      }
      return completedCount >= 2;
    }, { timeout: 120000, intervals: [1000] }).toBeTruthy();
    await shot(page, testInfo, '08-both-completed.png');

    // Step 9 — Verify Download button is visible for each height task row
    for (const h of heights) {
      await expect(
        taskRowByHeight(h).first().getByRole('link', { name: 'Download' })
      ).toBeVisible({ timeout: UI_TIMEOUT });
    }
    await shot(page, testInfo, '09-download-buttons-visible.png');

    // Step 10 — Download each file and verify filename "{baseName}-aac-h264-{height}.{format}"
    const heightSuffix = {
      '1080p': 'aac-h264-1080p.mp4',
      '720p':  'aac-h264-720p.mp4',
    };
    for (const h of heights) {
      const row = taskRowByHeight(h).first();
      await expectRowDownloadFilename(row, `${baseName}-${heightSuffix[h]}`);
      await clickDownloadAndVerifyMp4(page, row);
      await shot(page, testInfo, `10-download-verified-${h}.png`);
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
