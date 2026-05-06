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
  createOrUpdateTariffByTitle,
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

// ── Tariff for test-02: 0.1 GB storage ───────────────────────────────────────
// Fixture file = 2022_10_04_Two_Maxes.mp4 ≈ 6.44 MB (6 754 459 bytes)
//   15 × 6.44 MB ≈  96.6 MB  → fits inside 102.4 MB (0.1 GB)
//   16 × 6.44 MB ≈ 103.0 MB  → exceeds limit → upload rejected
const TARIFF_FREE_STORAGE_100M = {
  delay: 3600,
  instance: 1,
  videoDuration: 3600,
  videoSize: 100,
  maxWidth: 1920,
  maxHeight: 1080,
  storageGb: 0.1,
  storageHour: 24,
};

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
  await createOrUpdateTariffByTitle(page, 'Free-100M', TARIFF_FREE_STORAGE_100M, testInfo, '09-tariff-free-100m.png');
  // await createOrUpdateTariffByTitle(page, 'Premium', PREMIUM_TARIFF, testInfo, '09b-tariff-premium.png');

  // ── Phase 3: create test-* users and assign tariffs ─────────────


  await createUserWithTariff(page, 'test-01@test.com', 'test-01', 'Free');
  await createUserWithTariff(page, 'test-02@test.com', 'test-02', 'Free-100M');
  await createUserWithTariff(page, 'test-03@test.com', 'test-03', 'Free');
  await createUserWithTariff(page, 'test-04@test.com', 'test-04', 'Free');
  await createUserWithTariff(page, 'test-05-free@test.com', 'test-05-free', 'Free');
  await createUserWithTariff(page, 'test-05-premium@test.com', 'test-05-premium', 'Premium');
  await shot(page, testInfo, '10-test-users-created.png');

  // ── Phase 4: sign out ─────────────────────────────────────────────────────────

  await logoutToPublic(page);
  await shot(page, testInfo, '20-sign-out.png');
});
