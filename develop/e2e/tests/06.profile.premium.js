/**
 * 06 · Profile page (premium): empty state, plan blocks, parallel upload + transcode, storage stats
 *
 * PHASE 1 — EMPTY STATE
 *   - Login as test-06 (Premium tariff: 1 GB storage, 24 h retention, 2 parallel tasks).
 *   - Navigate to /profile and wait for data to load.
 *
 *   Account block:
 *     - h2 heading "Account"
 *     - Email = test-06@test.com
 *     - Member since: non-empty date
 *     - Current plan badge = "Premium"  (no Upgrade link — user is already on Premium)
 *
 *   Tariff block (Your Plan card — left):
 *     - h2 heading "Your Plan"
 *     - 10 included features (✔ span.text-success): PREMIUM_INCLUDED
 *     - 1 excluded feature  (⊘ span.text-danger):  PREMIUM_EXCLUDED
 *     - Card footer button: "Current plan" (disabled)
 *
 *   Subscription card (right):
 *     - h2 heading "Subscription"
 *     - Valid until:     —
 *     - Days remaining:  —
 *     - "Payment History" label visible
 *     - "No payments yet." text visible
 *
 *   Videos & Transcoding block:
 *     - h2 heading "Videos & Transcoding"
 *     - All counters 0 / 0, "Next encoding starts at" = "-"
 *
 *   Storage block:
 *     - h2 heading "Storage"
 *     - Progress bar label: "0 MB used of 1 GB", "0%", "1 GB free"
 *     - InfoRows: Used=0 B, Free=1 GB, Total quota=1 GB, Expiring in 24h=0 B
 *     - Retention note: "Videos older than 24 hours will be automatically removed."
 *
 * PHASE 2 — AFTER UPLOAD + PARALLEL TRANSCODE
 *   - Upload fixture twice (-1 and -2 suffixes), delete video-1.
 *   - Open video-2 details → wait for poster + meta.
 *   - Start 144p transcode, then 240p transcode.
 *     (Premium: instance=2 → both run in parallel simultaneously.)
 *   - Wait until BOTH 144p and 240p reach COMPLETED.
 *   - Navigate to /profile and verify filled stats.
 *
 *   Videos & Transcoding block (filled):
 *     - Videos active / total:       1 / 2  (1 active, 1 soft-deleted)
 *     - Transcoding active / total:  2 / 2
 *     - Currently transcoding:       0  (both done)
 *     - Tasks in queue:              0  (no more pending)
 *     - Completed transcoding:       2
 *     - Next encoding starts at:     -  (nothing queued)
 *
 *   Storage block (filled):
 *     - Progress bar: "18 MB used of 1 GB", "2%", "1006 MB free"
 *     - Used=17.9 MB, Free=1006.1 MB, Total quota=1 GB, Expiring in 24h=17.7 MB
 *     - Retention note still present
 */

const { test, expect } = require('@playwright/test');
const {
  UI_TIMEOUT,
  NAV_TIMEOUT,
  openHome,
  loginAs,
  logoutToPublic,
  uploadFixtureAs,
  openVideosTab,
  expectVideosTableVisible,
  videoRowByTitle,
  clickAndAcceptConfirm,
  waitForVideoDetailsVisible,
  waitForPosterAndMeta,
  expectDetailsValue,
  clickHeightButtonInPreset,
  pollUntilHeightsCompleted,
  PREMIUM_INCLUDED,
  PREMIUM_EXCLUDED,
  profileCard,
  profileInfoRowValue,
  expectProfileInfoRowValue,
  waitForProfileLoaded,
  checkFeatureItems,
  shot,
} = require('../helpers');

const EMAIL    = 'test-06@test.com';
const PASSWORD = 'test-06';
const PRESET   = 'Standard video Quality';
const SRC      = '2022_10_04_Two_Maxes.mp4';
const videoName  = (n) => `2022_10_04_Two_Maxes-06-${n}.mp4`;
const videoTitle = (n) => `2022_10_04_Two_Maxes-06-${n}`;

test('06 · premium profile: empty state, plan cards, parallel transcode, storage stats', async ({ page }, testInfo) => {
  // 2 uploads + parallel 144p+240p transcode + buffer = 6-minute budget
  test.setTimeout(6 * 60_000);

  // ══ PHASE 1: empty state ══════════════════════════════════════════════════

  await loginAs(page, EMAIL, PASSWORD);
  await shot(page, testInfo, 'test06-00-logged-in.png');

  await page.goto('/profile', { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
  await waitForProfileLoaded(page);
  await shot(page, testInfo, 'test06-01-profile-loaded.png');

  // Account — current plan = Premium, no Upgrade link
  await expect(page.locator('h2', { hasText: 'Account' }).first()).toBeVisible({ timeout: UI_TIMEOUT });
  await expectProfileInfoRowValue(page, 'Email', EMAIL);
  await expect(profileInfoRowValue(page, 'Member since')).not.toContainText('-', { timeout: UI_TIMEOUT });
  const currentPlanCell = profileInfoRowValue(page, 'Current plan');
  await expect(currentPlanCell).toContainText('Premium', { timeout: UI_TIMEOUT });
  await expect(currentPlanCell.getByRole('link', { name: /Upgrade/i })).toHaveCount(0);
  await shot(page, testInfo, 'test06-02-account-block.png');

  // Your Plan card (left) — shows current Premium features
  const yourPlanCard = profileCard(page, 'Your Plan');
  await expect(yourPlanCard).toBeVisible({ timeout: UI_TIMEOUT });
  await checkFeatureItems(page, yourPlanCard, PREMIUM_INCLUDED, PREMIUM_EXCLUDED);
  await expect(yourPlanCard.locator('.card-footer button', { hasText: 'Current plan' })).toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, 'test06-03-your-plan-card.png');

  // Subscription card (right)
  const subCard = profileCard(page, 'Subscription');
  await expect(subCard).toBeVisible({ timeout: UI_TIMEOUT });
  await expectProfileInfoRowValue(page, 'Valid until', '—');
  await expectProfileInfoRowValue(page, 'Days remaining', '—');
  await expect(subCard).toContainText('Payment History', { timeout: UI_TIMEOUT });
  await expect(subCard).toContainText('No payments yet.', { timeout: UI_TIMEOUT });
  await shot(page, testInfo, 'test06-04-subscription-card.png');

  // Videos & Transcoding (empty)
  await expect(page.locator('h2', { hasText: 'Videos & Transcoding' }).first()).toBeVisible({ timeout: UI_TIMEOUT });
  await expectProfileInfoRowValue(page, 'Videos active / total', '0 / 0');
  await expectProfileInfoRowValue(page, 'Transcoding active / total', '0 / 0');
  await expectProfileInfoRowValue(page, 'Currently transcoding', '0');
  await expectProfileInfoRowValue(page, 'Tasks in queue', '0');
  await expectProfileInfoRowValue(page, 'Completed transcoding', '0');
  await expectProfileInfoRowValue(page, 'Next encoding starts at', '-');
  await shot(page, testInfo, 'test06-05-videos-block-empty.png');

  // Storage (empty)
  const storageCard = profileCard(page, 'Storage');
  await expect(storageCard).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(storageCard).toContainText('0 MB used of 1 GB', { timeout: UI_TIMEOUT });
  await expect(storageCard).toContainText('0%', { timeout: UI_TIMEOUT });
  await expect(storageCard).toContainText('1 GB free', { timeout: UI_TIMEOUT });
  await expectProfileInfoRowValue(page, 'Used', '0 B');
  await expectProfileInfoRowValue(page, 'Free', '1 GB');
  await expectProfileInfoRowValue(page, 'Total quota', '1 GB');
  await expectProfileInfoRowValue(page, 'Expiring in 24h', '0 B');
  await expect(storageCard).toContainText('Videos older than 24 hours will be automatically removed.', { timeout: UI_TIMEOUT });
  await shot(page, testInfo, 'test06-06-storage-block-empty.png');

  // ══ PHASE 2: upload + parallel transcode + profile stats ══════════════════

  await openHome(page);
  await uploadFixtureAs(page, SRC, videoName(1));
  await shot(page, testInfo, 'test06-07-video1-uploaded.png');
  await uploadFixtureAs(page, SRC, videoName(2));
  await shot(page, testInfo, 'test06-08-video2-uploaded.png');

  // Delete video-1
  await openVideosTab(page);
  await expectVideosTableVisible(page);
  const row1 = videoRowByTitle(page, videoTitle(1));
  await clickAndAcceptConfirm(page, row1.getByRole('button', { name: 'Delete' }), 'Delete this video?');
  await expect(row1.locator('td.video-title-deleted')).toBeVisible({ timeout: 15000 });
  await shot(page, testInfo, 'test06-09-video1-deleted.png');

  // Open video-2 details
  await videoRowByTitle(page, videoTitle(2)).click({ timeout: UI_TIMEOUT });
  await waitForVideoDetailsVisible(page);
  await expectDetailsValue(page, 'Title');
  await expectDetailsValue(page, 'Created');
  await waitForPosterAndMeta(page, testInfo, 'test06-10-video2-details');

  // Transcode 144p then 240p (Premium: instance=2 → both run in parallel)
  await clickHeightButtonInPreset(page, PRESET, 144);
  await shot(page, testInfo, 'test06-11-144p-started.png');
  await clickHeightButtonInPreset(page, PRESET, 240);
  await shot(page, testInfo, 'test06-12-240p-started.png');

  // Wait for BOTH to complete (parallel execution)
  await pollUntilHeightsCompleted(page, PRESET, [144, 240]);
  await shot(page, testInfo, 'test06-13-both-completed.png');

  // ══ Profile — filled stats ════════════════════════════════════════════════

  await page.goto('/profile', { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
  await waitForProfileLoaded(page);
  await shot(page, testInfo, 'test06-14-profile-after-transcode.png');

  // Videos & Transcoding (filled)
  await expectProfileInfoRowValue(page, 'Videos active / total', '1 / 2');
  await expectProfileInfoRowValue(page, 'Transcoding active / total', '2 / 2');
  await expectProfileInfoRowValue(page, 'Currently transcoding', '0');
  await expectProfileInfoRowValue(page, 'Tasks in queue', '0');
  await expectProfileInfoRowValue(page, 'Completed transcoding', '2');
  await expectProfileInfoRowValue(page, 'Next encoding starts at', '-');
  await shot(page, testInfo, 'test06-15-videos-block-filled.png');

  // Storage (filled) — 144p + 240p outputs for Premium
  const storageCardFilled = profileCard(page, 'Storage');
  await expect(storageCardFilled).toContainText('7 MB used of 1 GB', { timeout: UI_TIMEOUT });
  await expect(storageCardFilled).toContainText('1%', { timeout: UI_TIMEOUT });
  await expect(storageCardFilled).toContainText('1017 MB free', { timeout: UI_TIMEOUT });
  await expectProfileInfoRowValue(page, 'Used', '7.1 MB');
  await expectProfileInfoRowValue(page, 'Free', '1016.9 MB');
  await expectProfileInfoRowValue(page, 'Total quota', '1 GB');
  await expectProfileInfoRowValue(page, 'Expiring in 24h', '6.4 MB');
  await expect(storageCardFilled).toContainText('Videos older than 24 hours will be automatically removed.', { timeout: UI_TIMEOUT });
  await shot(page, testInfo, 'test06-16-storage-block-filled.png');

  await logoutToPublic(page);
  await shot(page, testInfo, 'test06-99-logout.png');
});
