/**
 * 16 · tariff.limit.resolution
 *
 * - Login as test-16 (Free-resolution tariff: maxWidth=320 maxHeight=180)
 * - Upload the fixture video (higher resolution than 320×180)
 * - The server auto-deletes the video because it exceeds the resolution limit
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

const EMAIL    = 'test-16@test.com';
const PASSWORD = 'test-16';
const SRC      = '2022_10_04_Two_Maxes.mp4';
const VIDEO_NAME  = '2022_10_04_Two_Maxes-16.mp4';

test('16 · tariff.limit.resolution: video exceeds resolution limit → auto-deleted', async ({ page }, testInfo) => {
  test.setTimeout(5 * 60_000);

  // ── Login ────────────────────────────────────────────────────────────────────
  await loginAs(page, EMAIL, PASSWORD);
  await shot(page, testInfo, '16-01-login.png');

  // ── Upload (succeeds but server will auto-delete due to resolution > 320×180) ─
  await uploadFixtureAs(page, SRC, VIDEO_NAME);
  await shot(page, testInfo, '16-02-uploaded.png');

  // ── Verify auto-deleted in list + details ────────────────────────────────────
  await verifyDeletedVideoInListAndDetails(page, testInfo, VIDEO_NAME, '16-03-auto-deleted');

  // ── Logout ───────────────────────────────────────────────────────────────────
  await logoutToPublic(page);
  await shot(page, testInfo, '16-99-logout.png');
});

