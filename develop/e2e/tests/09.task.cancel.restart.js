/**
 * 09 · task.cancel.restart
 *
 * - Login as test-09 (Premium tariff — instance=2, delay=0)
 * - Upload one video
 * - Open video details, wait for poster + meta
 * - Transcode tab → Custom → start 1280×720 (720p)
 * - Verify the task enters PROCESSING
 * - Wait until progress reaches 30%+, then cancel
 * - Verify CANCELLED state
 * - Click Transcode on the same row to restart
 * - Watch progress grow until COMPLETED
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
  readPresetTaskState,
  presetRow,
  activeTaskRowByPresetAndHeight,
  waitForPresetTaskStatus,
  expectRowDownloadFilename,
  clickDownloadAndVerifyMp4,
  shot,
} = require('../helpers');

const EMAIL    = 'test-09@test.com';
const PASSWORD = 'test-09';
const PRESET   = 'Standard video Quality';
const SRC      = '2022_10_04_Two_Maxes.mp4';
const VIDEO_NAME  = '2022_10_04_Two_Maxes-09.mp4';
const VIDEO_TITLE = '2022_10_04_Two_Maxes-09';
const HEIGHT = 720;
const DOWNLOAD_FILENAME = `${VIDEO_TITLE}-aac-h264-${HEIGHT}p.mp4`;

test('09 · task.cancel.restart: transcode 720p, cancel at 30%, restart, complete', async ({ page }, testInfo) => {
  test.setTimeout(12 * 60_000);

  // ── Login ────────────────────────────────────────────────────────────────────
  await loginAs(page, EMAIL, PASSWORD);
  await shot(page, testInfo, '09-01-login.png');

  // ── Upload ───────────────────────────────────────────────────────────────────
  await uploadFixtureAs(page, SRC, VIDEO_NAME);
  await shot(page, testInfo, '09-02-uploaded.png');

  // ── Open video list and verify video is there ────────────────────────────────
  await openVideosTab(page);
  await expectVideosTableVisible(page);
  const videoRow = activeVideoRowByTitle(page, VIDEO_TITLE);
  await expect(videoRow).toBeVisible({ timeout: NAV_TIMEOUT });
  await shot(page, testInfo, '09-03-video-in-list.png');

  // ── Open video details ───────────────────────────────────────────────────────
  await videoRow.click({ timeout: UI_TIMEOUT });
  await waitForVideoDetailsVisible(page);
  await waitForPosterAndMeta(page, testInfo, '09-04-poster-meta');

  // ── Start 720p transcode ─────────────────────────────────────────────────────
  await clickHeightButtonInPreset(page, PRESET, HEIGHT);
  await shot(page, testInfo, '09-05-720p-started.png');

  // ── Wait for PROCESSING ──────────────────────────────────────────────────────
  await expect.poll(
    async () => (await readPresetTaskState(page, PRESET)).status,
    { timeout: 60000, intervals: [1000, 2000, 3000] }
  ).toMatch(/PROCESSING|COMPLETED/);
  await shot(page, testInfo, '09-06-processing.png');

  // ── Wait for 30%+ progress then cancel ──────────────────────────────────────
  let cancelledAfterProgress = false;
  for (let attempt = 0; attempt < 120; attempt++) {
    const state = await readPresetTaskState(page, PRESET);
    if (state.status === 'COMPLETED') {
      throw new Error('Task completed before we could cancel at 30% — choose a slower codec or longer video');
    }
    if (state.status === 'PROCESSING' && state.progress >= 30) {
      const cancelBtn = presetRow(page, PRESET).getByRole('button', { name: 'Cancel' });
      await expect(cancelBtn).toBeVisible({ timeout: UI_TIMEOUT });
      await cancelBtn.click({ timeout: UI_TIMEOUT });
      cancelledAfterProgress = true;
      break;
    }
    await page.waitForTimeout(2000);
  }
  expect(cancelledAfterProgress).toBe(true);
  await shot(page, testInfo, '09-07-cancel-sent.png');

  // ── Wait for CANCELLED ───────────────────────────────────────────────────────
  await waitForPresetTaskStatus(page, PRESET, 'CANCELLED', { maxAttempts: 60, pollMs: 3000 });
  const cancelledRow = presetRow(page, PRESET);
  await expect(cancelledRow.getByRole('link', { name: 'Download' })).toHaveCount(0, { timeout: UI_TIMEOUT });
  await expect(cancelledRow.getByRole('button', { name: 'Transcode' })).toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, '09-08-cancelled.png');

  // ── Restart transcode ────────────────────────────────────────────────────────
  await cancelledRow.getByRole('button', { name: 'Transcode' }).click({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, '09-09-restart-clicked.png');

  // Wait for the new task to become active
  await expect.poll(
    async () => (await readPresetTaskState(page, PRESET, { preferActive: true })).status,
    { timeout: 45000, intervals: [1000, 2000, 3000] }
  ).toMatch(/PENDING|PROCESSING|COMPLETED/);

  // ── Track progress growth until COMPLETED ───────────────────────────────────
  let prevProgress = -1;
  let sawProgressIncrease = false;
  for (let attempt = 0; attempt < 120; attempt++) {
    const state = await readPresetTaskState(page, PRESET, { preferActive: true });
    if (state.progress > prevProgress && prevProgress >= 0) sawProgressIncrease = true;
    if (state.progress > prevProgress) prevProgress = state.progress;
    if (state.status === 'COMPLETED') break;
    await page.waitForTimeout(3000);
  }
  expect(sawProgressIncrease).toBe(true);
  await shot(page, testInfo, '09-10-restart-completed.png');

  // ── Download + verify filename ───────────────────────────────────────────────
  const completedRow = activeTaskRowByPresetAndHeight(page, PRESET, HEIGHT);
  await expect(completedRow.getByRole('link', { name: 'Download' })).toBeVisible({ timeout: UI_TIMEOUT });
  await expectRowDownloadFilename(completedRow, DOWNLOAD_FILENAME);
  await clickDownloadAndVerifyMp4(page, completedRow);
  await shot(page, testInfo, '09-11-download-verified.png');

  // ── Logout ───────────────────────────────────────────────────────────────────
  await logoutToPublic(page);
  await shot(page, testInfo, '09-99-logout.png');
});

