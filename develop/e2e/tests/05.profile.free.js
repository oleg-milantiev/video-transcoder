/**
 * 05 · Profile page (free): empty state, tariff plan blocks, upload + transcode, storage statistics
 *
 * PHASE 1 — EMPTY STATE
 *   - Guest: "Profile" footer link must NOT be visible.
 *   - Login as test-05 (Free tariff: 1 GB storage, 24 h retention, 1 parallel task).
 *   - "Profile" footer link is NOW visible.
 *   - Navigate to /profile and wait for data to load.
 *
 *   Account block:
 *     - h2 heading "Account"
 *     - Email = test-05-free@test.com
 *     - Member since: non-empty date
 *     - Current plan badge = "Free" + "Upgrade" link present
 *
 *   Tariff block (Free card — left):
 *     - h2 heading "Tariff"
 *     - 6 included features (✔ span.text-success):
 *         Up to 500 MB per video, Up to 10 minutes duration, Up to 1080p resolution,
 *         5 GB, 24 Hours storage, Standard transcoding speed, MP4 output format
 *     - 5 excluded features (⊘ span.text-danger):
 *         Instant transcoding, Multiple simultaneous tasks, Priority queue,
 *         Custom presets, HLS streaming output
 *     - Card footer button: "Current plan" (disabled)
 *
 *   Tariff block (Premium card — right):
 *     - h2 heading "Premium"
 *     - 10 included features (✔ span.text-success)
 *     - 1 excluded feature (⊘ span.text-danger): HLS streaming output
 *     - Card footer button: "Upgrade to Premium"
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
 * PHASE 2 — AFTER UPLOAD + TRANSCODE
 *   - Upload fixture as video-1 and video-2 (suffix -1 / -2).
 *   - Delete video-1 from Videos list.
 *   - Open video-2 details → wait for poster + all meta fields.
 *   - Transcode tab → Custom goal → click 144p on "Standard video Quality".
 *   - Transcode tab → Custom goal → click 240p on "Standard video Quality".
 *   - Poll until the 144p task reaches COMPLETED (Free tariff: instance=1,
 *     so 240p stays PENDING while 144p runs).
 *   - Navigate back to /profile.
 *
 *   Videos & Transcoding block (filled):
 *     - Videos active / total: 1 / 1
 *     - Transcoding active / total: 2 / 2
 *     - Currently transcoding: 0 (144p done, 240p queued)
 *     - Tasks in queue: 1
 *     - Completed transcoding: 1
 *     - Next encoding starts at: a non-empty date/time string
 *
 *   Storage block (filled):
 *     - Progress bar contains "used of 1 GB", a positive %, and "free"
 *     - Used > 0 B, Free < 1 GB, Total quota = 1 GB, Expiring in 24h > 0 B
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
  pollUntilHeightCompleted,
  FREE_INCLUDED,
  FREE_EXCLUDED,
  PREMIUM_INCLUDED,
  PREMIUM_EXCLUDED,
  profileCard,
  profileInfoRowValue,
  expectProfileInfoRowValue,
  waitForProfileLoaded,
  checkFeatureItems,
  shot,
} = require('../helpers');

const EMAIL    = 'test-05@test.com';
const PASSWORD = 'test-05';
const PRESET   = 'Standard video Quality';
const SRC      = '2022_10_04_Two_Maxes.mp4';
const videoName  = (n) => `2022_10_04_Two_Maxes-05-${n}.mp4`;
const videoTitle = (n) => `2022_10_04_Two_Maxes-05-${n}`;

test('05 · free profile: empty state, tariff cards, upload + transcode, storage stats', async ({ page }, testInfo) => {
  // 2 uploads + 144p transcode + buffer = generous 6-minute budget
  test.setTimeout(6 * 60_000);

  // ══ PHASE 1: empty state ════════════════════════════════════════════════════

  // ── Guest: "Profile" link must NOT appear in footer ──────────────────────────
  await openHome(page);
  await expect(page.locator('footer').getByRole('link', { name: 'Profile' })).toHaveCount(0, { timeout: UI_TIMEOUT });
  await shot(page, testInfo, 'test05-00-guest-no-profile-link.png');

  // ── Login ────────────────────────────────────────────────────────────────────
  await loginAs(page, EMAIL, PASSWORD);
  await expect(page.locator('footer').getByRole('link', { name: 'Profile' })).toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, 'test05-01-logged-in-profile-link-visible.png');

  // ── Navigate to /profile and wait for data ────────────────────────────────────
  await page.goto('/profile', { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
  await waitForProfileLoaded(page);
  await shot(page, testInfo, 'test05-02-profile-loaded.png');

  // ── Account block ─────────────────────────────────────────────────────────────
  await expect(page.locator('h2', { hasText: 'Account' }).first()).toBeVisible({ timeout: UI_TIMEOUT });
  await expectProfileInfoRowValue(page, 'Email', EMAIL);
  await expect(profileInfoRowValue(page, 'Member since')).not.toContainText('-', { timeout: UI_TIMEOUT });
  const currentPlanCell = profileInfoRowValue(page, 'Current plan');
  await expect(currentPlanCell).toContainText('Free', { timeout: UI_TIMEOUT });
  await expect(currentPlanCell.getByRole('link', { name: /Upgrade/i })).toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, 'test05-03-account-block.png');

  // ── Tariff block: Free card ───────────────────────────────────────────────────
  const freeCard = profileCard(page, 'Tariff');
  await expect(freeCard).toBeVisible({ timeout: UI_TIMEOUT });
  await checkFeatureItems(page, freeCard, FREE_INCLUDED, FREE_EXCLUDED);
  await expect(freeCard.locator('.card-footer button', { hasText: 'Current plan' })).toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, 'test05-04-tariff-free-card.png');

  // ── Tariff block: Premium card ────────────────────────────────────────────────
  const premiumCard = profileCard(page, 'Premium');
  await expect(premiumCard).toBeVisible({ timeout: UI_TIMEOUT });
  await checkFeatureItems(page, premiumCard, PREMIUM_INCLUDED, PREMIUM_EXCLUDED);
  await expect(premiumCard.locator('.card-footer button', { hasText: 'Upgrade to Premium' })).toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, 'test05-05-tariff-premium-card.png');

  // ── Videos & Transcoding block (empty) ───────────────────────────────────────
  await expect(page.locator('h2', { hasText: 'Videos & Transcoding' }).first()).toBeVisible({ timeout: UI_TIMEOUT });
  await expectProfileInfoRowValue(page, 'Videos active / total', '0 / 0');
  await expectProfileInfoRowValue(page, 'Transcoding active / total', '0 / 0');
  await expectProfileInfoRowValue(page, 'Currently transcoding', '0');
  await expectProfileInfoRowValue(page, 'Tasks in queue', '0');
  await expectProfileInfoRowValue(page, 'Completed transcoding', '0');
  await expectProfileInfoRowValue(page, 'Next encoding starts at', '-');
  await shot(page, testInfo, 'test05-06-videos-block-empty.png');

  // ── Storage block (empty) ─────────────────────────────────────────────────────
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
  await shot(page, testInfo, 'test05-07-storage-block-empty.png');

  // ══ PHASE 2: upload + transcode + profile stats check ═══════════════════════

  // ── Upload video-1 and video-2 ────────────────────────────────────────────────
  await openHome(page);
  await uploadFixtureAs(page, SRC, videoName(1));
  await shot(page, testInfo, 'test05-08-video1-uploaded.png');

  await uploadFixtureAs(page, SRC, videoName(2));
  await shot(page, testInfo, 'test05-09-video2-uploaded.png');

  // ── Delete video-1 ────────────────────────────────────────────────────────────
  await openVideosTab(page);
  await expectVideosTableVisible(page);
  const row1 = videoRowByTitle(page, videoTitle(1));
  await clickAndAcceptConfirm(page, row1.getByRole('button', { name: 'Delete' }), 'Delete this video?');
  await expect(row1.locator('td.video-title-deleted')).toBeVisible({ timeout: 15000 });
  await shot(page, testInfo, 'test05-10-video1-deleted.png');

  // ── Open video-2 details and wait for poster + meta ───────────────────────────
  const row2 = videoRowByTitle(page, videoTitle(2));
  await row2.click({ timeout: UI_TIMEOUT });
  await waitForVideoDetailsVisible(page);
  await expectDetailsValue(page, 'Title');
  await expectDetailsValue(page, 'Created');
  await waitForPosterAndMeta(page, testInfo, 'test05-11-video2-details');

  // ── Transcode 144p ────────────────────────────────────────────────────────────
  await clickHeightButtonInPreset(page, PRESET, 144);
  await shot(page, testInfo, 'test05-12-144p-started.png');

  // ── Transcode 240p ────────────────────────────────────────────────────────────
  await clickHeightButtonInPreset(page, PRESET, 240);
  await shot(page, testInfo, 'test05-13-240p-started.png');

  // ── Wait for 144p COMPLETED (240p will be PENDING — Free: instance=1) ─────────
  await pollUntilHeight144Completed(page);
  await shot(page, testInfo, 'test05-14-144p-completed.png');

  // ══ Check profile with filled stats ════════════════════════════════════════

  await page.goto('/profile', { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
  await waitForProfileLoaded(page);
  await shot(page, testInfo, 'test05-15-profile-after-transcode.png');

  // ── Videos & Transcoding block (filled) ──────────────────────────────────────
  await expectProfileInfoRowValue(page, 'Videos active / total', '1 / 2');
  await expectProfileInfoRowValue(page, 'Transcoding active / total', '2 / 2');
  await expectProfileInfoRowValue(page, 'Currently transcoding', '0');
  await expectProfileInfoRowValue(page, 'Tasks in queue', '1');
  await expectProfileInfoRowValue(page, 'Completed transcoding', '1');
  // "Next encoding starts at" must contain an actual date, not "-"
  await expect(profileInfoRowValue(page, 'Next encoding starts at')).not.toContainText('-', { timeout: UI_TIMEOUT });
  await shot(page, testInfo, 'test05-16-videos-block-filled.png');

  // ── Storage block (filled) ────────────────────────────────────────────────────
  const storageCardFilled = profileCard(page, 'Storage');
  await expect(storageCardFilled).toContainText('used of 1 GB', { timeout: UI_TIMEOUT });
  await expect(storageCardFilled).toContainText('%', { timeout: UI_TIMEOUT });
  await expect(storageCardFilled).toContainText('free', { timeout: UI_TIMEOUT });
  await expect(profileInfoRowValue(page, 'Used')).not.toContainText('0 B', { timeout: UI_TIMEOUT });
  await expect(profileInfoRowValue(page, 'Free')).not.toContainText('1 GB', { timeout: UI_TIMEOUT });
  await expectProfileInfoRowValue(page, 'Total quota', '1 GB');
  await expect(profileInfoRowValue(page, 'Expiring in 24h')).not.toContainText('0 B', { timeout: UI_TIMEOUT });
  await expect(storageCardFilled).toContainText('Videos older than 24 hours will be automatically removed.', { timeout: UI_TIMEOUT });
  await shot(page, testInfo, 'test05-17-storage-block-filled.png');

  // ── Logout ────────────────────────────────────────────────────────────────────
  await logoutToPublic(page);
  await shot(page, testInfo, 'test05-99-logout.png');
});
