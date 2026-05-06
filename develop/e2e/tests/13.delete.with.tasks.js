/**
 * 13 · delete.with.tasks
 *
 * - Login as test-13 (Free tariff)
 * - Upload one video
 * - Open video details, wait for poster + meta
 * - Transcode tab → Custom → start 144p
 * - Wait for 144p COMPLETED
 * - Download + verify filename
 * - Go to video list
 * - Delete the video
 * - Verify it is deleted (td.video-title-deleted)
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
  videoRowByTitle,
  waitForVideoDetailsVisible,
  waitForPosterAndMeta,
  clickHeightButtonInPreset,
  taskRowByHeight,
  expectRowDownloadFilename,
  clickDownloadAndVerifyMp4,
  clickAndAcceptConfirm,
  shot,
} = require('../helpers');

const EMAIL    = 'test-13@test.com';
const PASSWORD = 'test-13';
const PRESET   = 'Standard video Quality';
const SRC      = '2022_10_04_Two_Maxes.mp4';
const VIDEO_NAME  = '2022_10_04_Two_Maxes-13.mp4';
const VIDEO_TITLE = '2022_10_04_Two_Maxes-13';
const DOWNLOAD_FILENAME = `${VIDEO_TITLE}-aac-h264-144p.mp4`;

test('13 · delete.with.tasks: transcode 144p, download, then delete video', async ({ page }, testInfo) => {
  test.setTimeout(8 * 60_000);

  // ── Login ────────────────────────────────────────────────────────────────────
  await loginAs(page, EMAIL, PASSWORD);
  await shot(page, testInfo, '13-01-login.png');

  // ── Upload ───────────────────────────────────────────────────────────────────
  await uploadFixtureAs(page, SRC, VIDEO_NAME);
  await shot(page, testInfo, '13-02-uploaded.png');

  // ── Open video list and verify video ─────────────────────────────────────────
  await openVideosTab(page);
  await expectVideosTableVisible(page);
  const videoRow = activeVideoRowByTitle(page, VIDEO_TITLE);
  await expect(videoRow).toBeVisible({ timeout: NAV_TIMEOUT });
  await shot(page, testInfo, '13-03-video-in-list.png');

  // ── Open video details ───────────────────────────────────────────────────────
  await videoRow.click({ timeout: UI_TIMEOUT });
  await waitForVideoDetailsVisible(page);
  await waitForPosterAndMeta(page, testInfo, '13-04-poster-meta');

  // ── Start 144p transcode ─────────────────────────────────────────────────────
  await clickHeightButtonInPreset(page, PRESET, 144);
  await shot(page, testInfo, '13-05-144p-started.png');

  // ── Wait for 144p COMPLETED ───────────────────────────────────────────────────
  await expect.poll(async () => {
    const row = taskRowByHeight(page, '144p').first();
    if (await row.count() === 0) return false;
    const status = (await row.locator('td').nth(2).innerText()).trim();
    return status === 'COMPLETED';
  }, { timeout: 300000, intervals: [1000] }).toBeTruthy();
  await shot(page, testInfo, '13-06-144p-completed.png');

  // ── Download + verify filename ────────────────────────────────────────────────
  const completedRow = taskRowByHeight(page, '144p').first();
  await expect(completedRow.getByRole('link', { name: 'Download' })).toBeVisible({ timeout: UI_TIMEOUT });
  await expectRowDownloadFilename(completedRow, DOWNLOAD_FILENAME);
  await clickDownloadAndVerifyMp4(page, completedRow);
  await shot(page, testInfo, '13-07-download-verified.png');

  // ── Go to video list ─────────────────────────────────────────────────────────
  await openVideosTab(page);
  await expectVideosTableVisible(page);

  // ── Delete the video ─────────────────────────────────────────────────────────
  const listRow = videoRowByTitle(page, VIDEO_TITLE);
  await expect(listRow).toBeVisible({ timeout: NAV_TIMEOUT });
  await clickAndAcceptConfirm(page, listRow.getByRole('button', { name: 'Delete' }), 'Delete this video?');
  await expect(listRow.locator('td.video-title-deleted')).toBeVisible({ timeout: 15000 });
  await shot(page, testInfo, '13-08-video-deleted.png');

  // ── Logout ───────────────────────────────────────────────────────────────────
  await logoutToPublic(page);
  await shot(page, testInfo, '13-99-logout.png');
});

