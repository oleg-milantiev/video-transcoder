const { test, expect } = require('@playwright/test');
const {
  UI_TIMEOUT,
  // NAV_TIMEOUT,
  getAdminCredentials,
  loginAsAdmin,
  logoutToPublic,
  expectTabsVisible,
  expectUploadDashboardVisible,
  openVideosTab,
  openTasksTab,
  expectEmptyVideos,
  expectEmptyTasks,
  openAdminDashboardFromHome,
  ensureAdminMenuSectionsVisible,
  mainTableBodyForHeading,
  // createOrUpdatePreset,
  // createOrUpdateTariffByTitle,
  createUserWithTariff,
  // assignTariffToUser,
  shot,
} = require('../helpers');

// ── Presets used across the whole test suite ──────────────────────────────────
// const PRESETS = [
//   {
//     title: 'Standard video Quality',
//     format: 'mp4',
//     videoCodec: 'h264',
//     audioCodec: 'aac',
//     bitrateJson: { 360: 1.5, 720: 3.0, 1080: 6.0, 2160: 20.0 },
//   },
//   {
//     title: 'High video Quality, High Efficiency Audio',
//     format: 'mp4',
//     videoCodec: 'h265',
//     audioCodec: 'opus',
//     bitrateJson: { 360: 1.0, 480: 1.5, 720: 2.5, 1080: 5.0 },
//   },
// ];

// ── Tariffs used across the whole test suite ──────────────────────────────────
// const FREE_TARIFF = {
//   delay: 3600,
//   instance: 1,
//   videoDuration: 3600,
//   videoSize: 100,
//   maxWidth: 1920,
//   maxHeight: 1080,
//   storageGb: 1,
//   storageHour: 24,
// };

// const PREMIUM_TARIFF = {
//   delay: 0,
//   instance: 2,
//   videoDuration: 86400,
//   videoSize: 1024,
//   maxWidth: 3840,
//   maxHeight: 2160,
//   storageGb: 100,
//   storageHour: 720,
// };

// ── Test users created by setup ───────────────────────────────────────────────
const TEST_01_EMAIL    = 'test-01@test.com';
const TEST_01_PASSWORD = 'test-01';

test('setup: prepare users, tariffs and presets for the full test suite', async ({ page }, testInfo) => {
  const { email: adminEmail, password: adminPassword } = getAdminCredentials();

  // ── Phase 1: login admin and smoke-check home and tabs ─────
  await loginAsAdmin(page);
  await shot(page, testInfo, '01-login-as-admin.png');

  await expectTabsVisible(page);
  await shot(page, testInfo, '02-tabs-visible.png');

  await expectUploadDashboardVisible(page);
  await shot(page, testInfo, '03-upload-tab-uppy.png');

  await openVideosTab(page);
  await expectEmptyVideos(page);
  await shot(page, testInfo, '04-videos-empty.png');

  await openTasksTab(page);
  await expectEmptyTasks(page);
  await shot(page, testInfo, '05-tasks-empty.png');

  // ── Phase 2: admin area — verify sections, create tariffs ─────────

  await expect(page.getByRole('link', { name: 'Admin', exact: true })).toBeVisible({ timeout: UI_TIMEOUT });
  await openAdminDashboardFromHome(page);
  await shot(page, testInfo, '06-open-admin.png');

  await expect(page.getByRole('heading', { name: 'Users' }).first()).toBeVisible({ timeout: UI_TIMEOUT });
  await ensureAdminMenuSectionsVisible(page);

  const usersTbody = mainTableBodyForHeading(page, 'Users');
  await expect(usersTbody).toContainText(adminEmail, { timeout: UI_TIMEOUT });
  await shot(page, testInfo, '07-admin-users-visible.png');

  // Presets
  // for (const preset of PRESETS) {
  //   await createOrUpdatePreset(page, preset, testInfo);
  // }
  // await shot(page, testInfo, '08-presets-ready.png');

  // Tariffs
  // await createOrUpdateTariffByTitle(page, 'Free',    FREE_TARIFF,    testInfo, '09a-tariff-free.png');
  // await createOrUpdateTariffByTitle(page, 'Premium', PREMIUM_TARIFF, testInfo, '09b-tariff-premium.png');

  // ── Phase 3: create test-* users and assign tariffs ─────────────

  await createUserWithTariff(page, TEST_01_EMAIL, TEST_01_PASSWORD, 'Free');
  await shot(page, testInfo, '10-test-01-user-created.png');

  // await assignTariffToUser(page, adminEmail, 'Free', testInfo, '11-admin-free-tariff.png');

  // ── Phase 4: sign out ─────────────────────────────────────────────────────────

  await logoutToPublic(page);
  await shot(page, testInfo, '20-sign-out.png');
});
