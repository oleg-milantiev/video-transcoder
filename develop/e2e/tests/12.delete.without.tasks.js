/**
 * 12 · delete.without.tasks
 *
 * - Login as test-12 (Free tariff)
 * - Upload one video
 * - Open video details, wait for poster + meta
 * - Go to video list
 * - Delete the uploaded video
 * - Verify it is deleted (td.video-title-deleted)
 * - Open video details
 * - Click to Transcode tab
 * - Click to Custom tab
 * - Verify all transcode buttons are disabled
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
  switchToVideoTab,
  switchToCustomGoal,
  clickAndAcceptConfirm,
  shot,
} = require('../helpers');

const EMAIL    = 'test-12@test.com';
const PASSWORD = 'test-12';
const SRC      = '2022_10_04_Two_Maxes.mp4';
const VIDEO_NAME  = '2022_10_04_Two_Maxes-12.mp4';
const VIDEO_TITLE = '2022_10_04_Two_Maxes-12';

test('12 · delete.without.tasks: upload, open details, delete from list', async ({ page }, testInfo) => {
  test.setTimeout(5 * 60_000);

  // ── Login ────────────────────────────────────────────────────────────────────
  await loginAs(page, EMAIL, PASSWORD);
  await shot(page, testInfo, '12-01-login.png');

  // ── Upload ───────────────────────────────────────────────────────────────────
  await uploadFixtureAs(page, SRC, VIDEO_NAME);
  await shot(page, testInfo, '12-02-uploaded.png');

  // ── Open video list and verify video ─────────────────────────────────────────
  await openVideosTab(page);
  await expectVideosTableVisible(page);
  const videoRow = activeVideoRowByTitle(page, VIDEO_TITLE);
  await expect(videoRow).toBeVisible({ timeout: NAV_TIMEOUT });
  await shot(page, testInfo, '12-03-video-in-list.png');

  // ── Open video details and wait for poster + meta ─────────────────────────────
  await videoRow.click({ timeout: UI_TIMEOUT });
  await waitForVideoDetailsVisible(page);
  await waitForPosterAndMeta(page, testInfo, '12-04-poster-meta');

  // ── Go back to video list ────────────────────────────────────────────────────
  await openVideosTab(page);
  await expectVideosTableVisible(page);

  // ── Delete the video ─────────────────────────────────────────────────────────
  const listRow = videoRowByTitle(page, VIDEO_TITLE);
  await expect(listRow).toBeVisible({ timeout: NAV_TIMEOUT });
  await clickAndAcceptConfirm(page, listRow.getByRole('button', { name: 'Delete' }), 'Delete this video?');
  await expect(listRow.locator('td.video-title-deleted')).toBeVisible({ timeout: 15000 });
  await shot(page, testInfo, '12-05-video-deleted.png');

  // ── Open video details of the deleted video ───────────────────────────────────
  await listRow.click({ timeout: UI_TIMEOUT });
  await waitForVideoDetailsVisible(page);
  await shot(page, testInfo, '12-06-deleted-details.png');

  // ── Click Transcode tab ───────────────────────────────────────────────────────
  await switchToVideoTab(page, 'transcode');
  await shot(page, testInfo, '12-07-transcode-tab.png');

  // ── Click Custom goal (skip if already active) ────────────────────────────────
  await switchToCustomGoal(page);
  await shot(page, testInfo, '12-08-custom-tab.png');

  // ── Verify all transcode (resolution) buttons are disabled ────────────────────
  const allTranscodeButtons = page.locator('button.btn-outline-primary');
  const count = await allTranscodeButtons.count();
  expect(count).toBeGreaterThan(0);
  for (let i = 0; i < count; i++) {
    await expect(allTranscodeButtons.nth(i)).toBeDisabled({ timeout: UI_TIMEOUT });
  }
  await shot(page, testInfo, '12-09-buttons-disabled.png');

  // ── Logout ───────────────────────────────────────────────────────────────────
  await logoutToPublic(page);
  await shot(page, testInfo, '12-99-logout.png');
});

