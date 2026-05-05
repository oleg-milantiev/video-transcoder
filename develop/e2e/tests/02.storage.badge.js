/**
 * 02 · Storage badge: 15× upload, storage full, delete, re-upload, bulk delete
 *
 * Scenario:
 *  - login test-02 (tariff: Free-02 → storageGb=0.1 ≈ 102 MB)
 *  - verify badge starts at "0 MB / 102 MB"
 *  - upload fixture 15 times; after each upload the badge "now" must increase
 *  - verify "Storage is running low" warning appears after 15 uploads
 *  - 16th upload must fail ("exceeds maximum allowed size")
 *  - go to Videos list, verify 15 videos
 *  - delete the last (15th) video → badge now must decrease (storage freed)
 *  - re-upload 16th → badge now must increase again
 *  - go to Videos list → delete all on page 1, verify all deleted
 *  - navigate to page 2 → delete all, verify all deleted
 *  - verify badge is back to "0 MB / 102 MB"
 *  - logout
 */

const { test } = require('@playwright/test');
const {
  loginAs,
  logoutToPublic,
  openVideosTab,
  expectVideosTableVisible,
  videoRowByTitle,
  expectVideosTotalCount,
  deleteAllVideosOnPage,
  expectAllVideosOnPageAreDeleted,
  clickVideosNextPage,
  uploadFixtureAs,
  clickAndAcceptConfirm,
  expectStorageBadgeContains,
  waitForStorageNowToExceed,
  waitForStorageNowBelow,
  shot,
} = require('../helpers');

const SRC= '2022_10_04_Two_Maxes.mp4';

/** Upload name for the i-th video (1-based) */
const videoName = (i) => `2022_10_04_Two_Maxes-02-${i}.mp4`;
/** Videos-list title (strip .mp4 extension) */
const videoTitle = (i) => `2022_10_04_Two_Maxes-02-${i}`;

test('02 · storage badge: 15× upload → full → delete → re-upload → bulk delete → empty', async ({ page }, testInfo) => {
  // ── 5-minute timeout: 15 sequential uploads ─────────────────────────────────
  test.setTimeout(5 * 60_000);

  // ── Step 1: Login ────────────────────────────────────────────────────────────
  await loginAs(page, 'test-02@test.com', 'test-02');
  await shot(page, testInfo, '00-login.png');

  // ── Step 2: Verify badge starts at zero ──────────────────────────────────────
  // Free-02 tariff: storageGb=0.1 → formatBytes(107374182) = "102 MB"
  await expectStorageBadgeContains(page, '0 MB / 102 MB');
  await shot(page, testInfo, '01-badge-empty.png');

  // ── Step 3: Upload 15 videos, verify badge increases after every upload ───────
  let prevMB = 0;
  for (let i = 1; i <= 15; i++) {
    await uploadFixtureAs(page, SRC, videoName(i));
    // wait for the Mercure app:storage SSE event to update the badge
    prevMB = await waitForStorageNowToExceed(page, prevMB);
    await shot(page, testInfo, `${String(i).padStart(2, '0')}b-upload-${i}-badge.png`);
  }

  // ── Step 4: Verify "Storage is running low" warning ───────────────────────────
  // After 15 × ~6.4 MB ≈ 96.6 MB uploaded, remaining ≈ 5.8 MB < videoSize (100 MB)
  await expectStorageBadgeContains(page, 'Storage is running low');
  await shot(page, testInfo, '16-badge-storage-low.png');

  // ── Step 5: 16th upload must fail ────────────────────────────────────────────
  // effectiveVideoSize = floor(~5.78 MB) = 5 MB; file is ~6.4 MB → rejected
  await uploadFixtureAs(page, SRC, videoName(16), { expectedErrorText: 'exceeds maximum allowed size' });
  await shot(page, testInfo, '17-16th-upload-rejected.png');

  // ── Step 6: Go to Videos list, verify 15 videos ──────────────────────────────
  await openVideosTab(page);
  await expectVideosTableVisible(page);
  await expectVideosTotalCount(page, 15);
  await shot(page, testInfo, '18-videos-list-15.png');

  // ── Step 7: Delete the last (15th) video → storage freed ─────────────────────
  const lastRow = videoRowByTitle(page, videoTitle(15));
  await clickAndAcceptConfirm(
    page,
    lastRow.getByRole('button', { name: 'Delete' }),
    'Delete this video?'
  );
  await lastRow.locator('td.video-title-deleted').waitFor({ state: 'visible', timeout: 15000 });
  const afterDeleteMB = await waitForStorageNowBelow(page, prevMB);
  await shot(page, testInfo, '19-after-delete-badge-freed.png');

  // ── Step 8: Re-upload video 16 — now succeeds ─────────────────────────────────
  await uploadFixtureAs(page, SRC, videoName(16));
  prevMB = await waitForStorageNowToExceed(page, afterDeleteMB);
  await shot(page, testInfo, '20-after-reupload-badge.png');

  // ── Step 9: Bulk-delete — page 1 ─────────────────────────────────────────────
  await openVideosTab(page);
  await expectVideosTableVisible(page);
  await deleteAllVideosOnPage(page);
  await expectAllVideosOnPageAreDeleted(page);
  await shot(page, testInfo, '21-page1-all-deleted.png');

  // ── Step 10: Bulk-delete — page 2 ────────────────────────────────────────────
  await clickVideosNextPage(page);
  await deleteAllVideosOnPage(page);
  await expectAllVideosOnPageAreDeleted(page);
  await shot(page, testInfo, '22-page2-all-deleted.png');

  // ── Step 11: Badge back to zero ───────────────────────────────────────────────
  await expectStorageBadgeContains(page, '0 MB / 102 MB');
  await shot(page, testInfo, '23-badge-back-to-zero.png');

  // ── Step 12: Logout ───────────────────────────────────────────────────────────
  await logoutToPublic(page);
  await shot(page, testInfo, '24-logout.png');
});
