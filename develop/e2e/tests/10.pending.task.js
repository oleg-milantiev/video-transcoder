/**
 * 10 · pending.task
 *
 * - Login as test-10 (Free tariff — instance=1, one parallel task)
 * - Upload one video
 * - Open video details, wait for poster + meta
 * - Transcode tab → Custom → start 144p
 * - Go back to Transcode → Custom → start 240p
 * - Wait for 144p COMPLETED, download + verify filename
 * - Verify 240p shows PENDING with "?" help icon and tooltip "Why isn't my video transcoding?"
 * - Open video list — verify that the video with a PENDING task cannot be deleted
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
  pollUntilHeightCompleted,
  taskRowByPresetAndHeight,
  expectRowDownloadFilename,
  clickDownloadAndVerifyMp4,
  shot,
} = require('../helpers');

const EMAIL    = 'test-10@test.com';
const PASSWORD = 'test-10';
const PRESET   = 'Standard video Quality';
const SRC      = '2022_10_04_Two_Maxes.mp4';
const VIDEO_NAME  = '2022_10_04_Two_Maxes-10.mp4';
const VIDEO_TITLE = '2022_10_04_Two_Maxes-10';
const DOWNLOAD_144_FILENAME = `${VIDEO_TITLE}-aac-h264-144p.mp4`;

test('10 · pending.task: dual transcode, pending state with tooltip, no-delete guard', async ({ page }, testInfo) => {
  test.setTimeout(10 * 60_000);

  // ── Login ────────────────────────────────────────────────────────────────────
  await loginAs(page, EMAIL, PASSWORD);
  await shot(page, testInfo, '10-01-login.png');

  // ── Upload ───────────────────────────────────────────────────────────────────
  await uploadFixtureAs(page, SRC, VIDEO_NAME);
  await shot(page, testInfo, '10-02-uploaded.png');

  // ── Open video list and verify video ─────────────────────────────────────────
  await openVideosTab(page);
  await expectVideosTableVisible(page);
  const videoRow = activeVideoRowByTitle(page, VIDEO_TITLE);
  await expect(videoRow).toBeVisible({ timeout: NAV_TIMEOUT });
  await shot(page, testInfo, '10-03-video-in-list.png');

  // ── Open video details ───────────────────────────────────────────────────────
  await videoRow.click({ timeout: UI_TIMEOUT });
  await waitForVideoDetailsVisible(page);
  await waitForPosterAndMeta(page, testInfo, '10-04-poster-meta');

  // ── Start 144p transcode ─────────────────────────────────────────────────────
  await clickHeightButtonInPreset(page, PRESET, 144);
  await shot(page, testInfo, '10-05-144p-started.png');

  // ── Start 240p transcode (goes PENDING immediately since instance=1) ──────────
  await clickHeightButtonInPreset(page, PRESET, 240);
  await shot(page, testInfo, '10-06-240p-started.png');

  // ── Wait for 144p COMPLETED ───────────────────────────────────────────────────
  await pollUntilHeightCompleted(page, PRESET, 144, 60, 5000);
  await shot(page, testInfo, '10-07-144p-completed.png');

  // ── Download 144p + verify filename ──────────────────────────────────────────
  const row144 = taskRowByPresetAndHeight(page, PRESET, 144);
  await expect(row144.getByRole('link', { name: 'Download' })).toBeVisible({ timeout: UI_TIMEOUT });
  await expectRowDownloadFilename(row144, DOWNLOAD_144_FILENAME);
  await clickDownloadAndVerifyMp4(page, row144);
  await shot(page, testInfo, '10-08-144p-download-verified.png');

  // ── 240p should still be PENDING with help icon ───────────────────────────────
  const row240 = taskRowByPresetAndHeight(page, PRESET, 240);
  const statusCell240 = row240.locator('td').nth(2);
  await expect(statusCell240).toContainText('PENDING', { timeout: UI_TIMEOUT });
  const helpIcon = statusCell240.getByRole('img').first();
  await expect(helpIcon).toBeVisible({ timeout: UI_TIMEOUT });
  await helpIcon.hover({ timeout: UI_TIMEOUT });
  await expect(helpIcon).toHaveAttribute(
    'title',
    /Why isn't my video transcoding\?/i,
    { timeout: UI_TIMEOUT }
  );
  await expect(helpIcon).toHaveAttribute(
    'aria-label',
    /Why isn't my video transcoding\?/i,
    { timeout: UI_TIMEOUT }
  );
  await shot(page, testInfo, '10-09-240p-pending-with-tooltip.png');

  // ── Video list: video with PENDING task cannot be deleted ─────────────────────
  await openVideosTab(page);
  await expectVideosTableVisible(page);
  const listRow = videoRowByTitle(page, VIDEO_TITLE);
  await expect(listRow).toBeVisible({ timeout: NAV_TIMEOUT });
  await expect(listRow.getByRole('button', { name: 'Delete' })).toHaveCount(0, { timeout: UI_TIMEOUT });
  await shot(page, testInfo, '10-10-no-delete-with-pending.png');

  // ── Logout ───────────────────────────────────────────────────────────────────
  await logoutToPublic(page);
  await shot(page, testInfo, '10-99-logout.png');
});

