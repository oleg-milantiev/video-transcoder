/**
 * 15 · tariff.limit.duration
 *
 * - Login as test-15 (Free-duration tariff: videoDuration=2s)
 * - Upload the fixture video (longer than 2 seconds)
 * - The server auto-deletes the video because it exceeds the duration limit
 * - Verify the video appears in the list as deleted (no poster)
 * - Open the video details — confirm deleted state, no poster image
 */

const { test } = require('@playwright/test');
const {
  loginAs,
  logoutToPublic,
  uploadFixtureAs,
  verifyDeletedVideoInListAndDetails,
  shot,
} = require('../helpers');

const EMAIL    = 'test-15@test.com';
const PASSWORD = 'test-15';
const SRC      = '2022_10_04_Two_Maxes.mp4';
const VIDEO_NAME  = '2022_10_04_Two_Maxes-15.mp4';

test('15 · tariff.limit.duration: video exceeds duration limit → auto-deleted', async ({ page }, testInfo) => {
  test.setTimeout(5 * 60_000);

  // ── Login ────────────────────────────────────────────────────────────────────
  await loginAs(page, EMAIL, PASSWORD);
  await shot(page, testInfo, '15-01-login.png');

  // ── Upload (succeeds but server will auto-delete due to duration > 2s) ────────
  await uploadFixtureAs(page, SRC, VIDEO_NAME);
  await shot(page, testInfo, '15-02-uploaded.png');

  // ── Verify auto-deleted in list + details ────────────────────────────────────
  await verifyDeletedVideoInListAndDetails(page, testInfo, VIDEO_NAME, '15-03-auto-deleted');

  // ── Logout ───────────────────────────────────────────────────────────────────
  await logoutToPublic(page);
  await shot(page, testInfo, '15-99-logout.png');
});

