/**
 * 14 · transcode.progress
 *
 * - Login as test-14 (Free tariff)
 * - Upload one video
 * - Open video details, wait for poster + meta
 * - Transcode tab → Custom → start 1080p
 * - Verify the task enters PROCESSING
 * - Track that progress percentage grows at least once
 * - Wait until COMPLETED
 * - Download + verify filename
 */

const { test, expect } = require('@playwright/test');
const {
  UI_TIMEOUT,
  NAV_TIMEOUT,
  loginAs,
  logoutToPublic,
  uploadFixtureAs,
  openVideosTab,
  expectVideosTableVisible,
  activeVideoRowByTitle,
  waitForVideoDetailsVisible,
  waitForPosterAndMeta,
  clickHeightButtonInPreset,
  taskRowByHeight,
  expectRowDownloadFilename,
  clickDownloadAndVerifyMp4,
  shot,
} = require('../helpers');

const EMAIL    = 'test-14@test.com';
const PASSWORD = 'test-14';
const PRESET   = 'Standard video Quality';
const SRC      = '2022_10_04_Two_Maxes.mp4';
const VIDEO_NAME  = '2022_10_04_Two_Maxes-14.mp4';
const VIDEO_TITLE = '2022_10_04_Two_Maxes-14';
const HEIGHT = 1080;
const DOWNLOAD_FILENAME = `${VIDEO_TITLE}-aac-h264-${HEIGHT}p.mp4`;

test('14 · transcode.progress: 1080p, watch progress grow, complete, download', async ({ page }, testInfo) => {
  test.setTimeout(10 * 60_000);

  // ── Login ────────────────────────────────────────────────────────────────────
  await loginAs(page, EMAIL, PASSWORD);
  await shot(page, testInfo, '14-01-login.png');

  // ── Upload ───────────────────────────────────────────────────────────────────
  await uploadFixtureAs(page, SRC, VIDEO_NAME);
  await shot(page, testInfo, '14-02-uploaded.png');

  // ── Open video list and verify video ─────────────────────────────────────────
  await openVideosTab(page);
  await expectVideosTableVisible(page);
  const videoRow = activeVideoRowByTitle(page, VIDEO_TITLE);
  await expect(videoRow).toBeVisible({ timeout: NAV_TIMEOUT });
  await shot(page, testInfo, '14-03-video-in-list.png');

  // ── Open video details ───────────────────────────────────────────────────────
  await videoRow.click({ timeout: UI_TIMEOUT });
  await waitForVideoDetailsVisible(page);
  await waitForPosterAndMeta(page, testInfo, '14-04-poster-meta');

  // ── Start 1080p transcode ─────────────────────────────────────────────────────
  await clickHeightButtonInPreset(page, PRESET, HEIGHT);
  await shot(page, testInfo, '14-05-1080p-started.png');

  // ── Wait for PROCESSING with progress > 0 ───────────────────────────────────
  await expect.poll(async () => {
    const row = taskRowByHeight(page, '1080p').first();
    if (await row.count() === 0) return false;
    const statusRaw = (await row.locator('td').nth(2).innerText()).trim();
    const status = statusRaw.replace(/\s+\?\s*$/, '').trim();
    const progressText = (await row.locator('td').nth(3).innerText()).trim();
    const progressMatch = progressText.match(/(\d+)\s*%/);
    const progress = progressMatch ? Number(progressMatch[1]) : 0;
    return status === 'PROCESSING' && progress > 0;
  }, { timeout: 120000, intervals: [1000] }).toBeTruthy();
  await shot(page, testInfo, '14-06-processing.png');

  // ── Wait for COMPLETED, track that progress increased ────────────────────────
  let prevProgress = 0;
  let sawProgressIncrease = false;

  await expect.poll(async () => {
    const row = taskRowByHeight(page, '1080p').first();
    if (await row.count() === 0) return false;
    const status = (await row.locator('td').nth(2).innerText()).trim();
    const progressText = (await row.locator('td').nth(3).innerText()).trim();
    const progressMatch = progressText.match(/(\d+)\s*%/);
    const progress = progressMatch ? Number(progressMatch[1]) : 0;
    if (progress > prevProgress) {
      if (prevProgress > 0) sawProgressIncrease = true;
      prevProgress = progress;
    }
    return status === 'COMPLETED';
  }, { timeout: 300000, intervals: [1000] }).toBeTruthy();

  expect(sawProgressIncrease).toBe(true);
  await shot(page, testInfo, '14-07-completed.png');

  // ── Download + verify filename ────────────────────────────────────────────────
  const completedRow = taskRowByHeight(page, '1080p').first();
  await expect(completedRow.getByRole('link', { name: 'Download' })).toBeVisible({ timeout: UI_TIMEOUT });
  await expectRowDownloadFilename(completedRow, DOWNLOAD_FILENAME);
  await clickDownloadAndVerifyMp4(page, completedRow);
  await shot(page, testInfo, '14-08-download-verified.png');

  // ── Logout ───────────────────────────────────────────────────────────────────
  await logoutToPublic(page);
  await shot(page, testInfo, '14-99-logout.png');
});
