/**
 * 17 · tariff.limit.filesize
 *
 * - Login as test-17 (Free-filesize tariff: videoSize=3 MB)
 * - Attempt to upload the fixture video (≈6.44 MB — exceeds the 3 MB limit)
 * - The upload is rejected with "exceeds maximum allowed size" error
 */

const { test, expect } = require('@playwright/test');
const {
  UI_TIMEOUT,
  loginAs,
  logoutToPublic,
  uploadFixtureAs,
  expectUploadHintText,
  shot,
} = require('../helpers');

const EMAIL    = 'test-17@test.com';
const PASSWORD = 'test-17';
const SRC      = '2022_10_04_Two_Maxes.mp4';
const VIDEO_NAME  = '2022_10_04_Two_Maxes-17.mp4';

test('17 · tariff.limit.filesize: upload exceeds 3 MB limit → rejected', async ({ page }, testInfo) => {
  test.setTimeout(3 * 60_000);

  // ── Login ────────────────────────────────────────────────────────────────────
  await loginAs(page, EMAIL, PASSWORD);
  await shot(page, testInfo, '17-01-login.png');

  // ── Upload hint shows the 3 MB limit ─────────────────────────────────────────
  await expectUploadHintText(page, '3 MB');
  await shot(page, testInfo, '17-02-upload-hint-3mb.png');

  // ── Upload fails with size error ──────────────────────────────────────────────
  await uploadFixtureAs(page, SRC, VIDEO_NAME, { expectedErrorText: 'exceeds maximum allowed size' });
  await shot(page, testInfo, '17-03-upload-rejected.png');

  // ── Logout ───────────────────────────────────────────────────────────────────
  await logoutToPublic(page);
  await shot(page, testInfo, '17-99-logout.png');
});

