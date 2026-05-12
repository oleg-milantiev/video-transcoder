const { test, expect } = require('@playwright/test');
const {
  UI_TIMEOUT,
  shot,
  openHome,
  openVideosTab,
  openTasksTab,
  expectTabsVisible,
  expectUploadHintText,
  expectEmptyVideos,
  expectEmptyTasks,
  loginAs,
  ensureLoggedOut,
  logoutToPublic,
  uploadFixtureAsName,
  expectVideosTableVisible,
  expectVideoRowHasCoreValues,
  activeVideoRowByTitle,
  waitForVideoDetailsVisible,
  expectVideoDetailsTitle,
  waitForPosterAndMeta,
  assertPresetAvailable,
  clickHeightButtonInPreset,
  expectPresetStatusHelpIcon,
  startTaskForHeight,
  cancelActiveTaskForHeight,
  waitForPresetState,
  waitForAllPresets,
  deleteVideoFromListIfPresent,
  clickDownloadAndVerifyByHeight,
  attachSseMessages,
} = require('../../helpers');
// Single preset with multiple height buttons
const STANDARD_PRESET = 'Standard video Quality';
// The two heights we exercise in this test (highest first)
const REQUIRED_TASKS = [
  { title: STANDARD_PRESET, height: 1080 },
  { title: STANDARD_PRESET, height: 720 },
];
test.describe('prod-safe isolated smoke', () => {
  test.setTimeout(5 * 60 * 1000);
  test('free-tariff behavior, then premium full transcoding flow', async ({ page }, testInfo) => {
    page.setDefaultTimeout(UI_TIMEOUT);

    const freeEmail = process.env.TEST_EMAIL_FREE;
    const freePassword = process.env.TEST_PASSWORD_FREE;
    const premiumEmail = process.env.TEST_EMAIL_PREMIUM;
    const premiumPassword = process.env.TEST_PASSWORD_PREMIUM;

    if (!freeEmail || !freePassword || !premiumEmail || !premiumPassword) {
      throw new Error(
        'Required env vars missing: TEST_EMAIL_FREE, TEST_PASSWORD_FREE, TEST_EMAIL_PREMIUM, TEST_PASSWORD_PREMIUM must all be set. Run app:smoke:prepare first.',
      );
    }

    // Derive video names from the free-user email, e.g. "prod-20260512-free@test.com" → "prod-20260512"
    const videoBaseName = freeEmail.replace(/-free@test\.com$/, '');
    const sourceVideoFileName = process.env.PROD_SOURCE_VIDEO || '2022_10_04_Two_Maxes.mp4';
    const uploadFileName = `${videoBaseName}.mp4`;

    const expectedUploadHint = '0 MB / 1 GB';
    let freeVideoDeleted = false;
    let premiumVideoDeleted = false;
    try {
      // ── Phase A — Free user: empty state, upload, start tasks ────────────────
      await loginAs(page, freeEmail, freePassword);
      await expectTabsVisible(page);
      await expectUploadHintText(page, expectedUploadHint);
      await shot(page, testInfo, '01-free-user-empty-state.png');
      await openVideosTab(page);
      await expectEmptyVideos(page);
      await shot(page, testInfo, '02-free-user-empty-videos.png');
      await openTasksTab(page);
      await expectEmptyTasks(page);
      await shot(page, testInfo, '03-free-user-empty-tasks.png');
      await uploadFixtureAsName(page, sourceVideoFileName, uploadFileName);
      await shot(page, testInfo, '04-free-user-upload-complete.png');
      await openVideosTab(page);
      await expectVideosTableVisible(page);
      const freeUploadedRow = activeVideoRowByTitle(page, videoBaseName);
      await expectVideoRowHasCoreValues(freeUploadedRow, videoBaseName);
      await shot(page, testInfo, '05-free-user-video-visible.png');
      await freeUploadedRow.click({ timeout: UI_TIMEOUT });
      await waitForVideoDetailsVisible(page);
      await expectVideoDetailsTitle(page, videoBaseName);
      await waitForPosterAndMeta(page, testInfo, '06-free-poster-meta-attempt');
      await shot(page, testInfo, '06b-free-video-details-ready.png');
      await assertPresetAvailable(page, STANDARD_PRESET);
      await clickHeightButtonInPreset(page, STANDARD_PRESET, 1080);
      await shot(page, testInfo, '07-free-1080p-clicked.png');
      await page.waitForTimeout(400);
      await clickHeightButtonInPreset(page, STANDARD_PRESET, 720);
      await shot(page, testInfo, '08-free-720p-clicked.png');
      // ── Phase B — Free tariff behavior: 1 slot, pending/processing ───────────
      await waitForPresetState(
        page, STANDARD_PRESET, 1080,
        (s) => s.status === 'PROCESSING' && s.hasCancel,
        '1080p processing with cancel button',
      );
      await shot(page, testInfo, '09-free-1080p-processing.png');
      await waitForPresetState(
        page, STANDARD_PRESET, 1080,
        (s) => s.status === 'PROCESSING' && s.progress > 30,
        '1080p progress above 30%',
        45000,
      );
      await shot(page, testInfo, '10-free-1080p-progress-above-30.png');
      // Cancel 1080p
      await cancelActiveTaskForHeight(page, STANDARD_PRESET, 1080);
      await shot(page, testInfo, '11-free-1080p-cancel-clicked.png');
      await waitForPresetState(
        page, STANDARD_PRESET, 1080,
        (s) => s.status === 'CANCELLED' && s.hasTranscode,
        '1080p cancelled with transcode restart button',
      );
      await shot(page, testInfo, '12-free-1080p-cancelled.png');
      // Restart 1080p — it should go PENDING because 720p is now using the slot
      await startTaskForHeight(page, STANDARD_PRESET, 1080);
      await shot(page, testInfo, '13-free-1080p-requeued.png');
      await waitForPresetState(
        page, STANDARD_PRESET, 1080,
        (s) => s.status === 'PENDING' && s.hasCancel,
        '1080p pending after requeue (free tariff: 1 slot)',
      );
      await expectPresetStatusHelpIcon(page, STANDARD_PRESET, {
        statusText: 'PENDING',
        tooltipText: "Why isn't my video transcoding?",
      });
      await shot(page, testInfo, '14-free-1080p-pending-with-tooltip.png');
      // Cancel both tasks
      await cancelActiveTaskForHeight(page, STANDARD_PRESET, 1080);
      await shot(page, testInfo, '15-free-1080p-cancel-clicked.png');
      await cancelActiveTaskForHeight(page, STANDARD_PRESET, 720);
      await shot(page, testInfo, '15-free-720p-cancel-clicked.png');
      await waitForAllPresets(
        page,
        REQUIRED_TASKS,
        (s) => s.status === 'CANCELLED' && s.hasTranscode,
        'all free-user tasks cancelled',
        10000,
        2000,
      );
      await shot(page, testInfo, '16-free-tasks-cancelled.png');
      // Delete free user's video and sign out
      await openHome(page);
      await logoutToPublic(page);
      await shot(page, testInfo, '18-free-user-signed-out.png');
      // ── Phase C — Premium user: upload fresh video, start both tasks ─────────
      await loginAs(page, premiumEmail, premiumPassword);
      await expectTabsVisible(page);
      await shot(page, testInfo, '19-premium-user-logged-in.png');
      await openVideosTab(page);
      await expectEmptyVideos(page);
      await shot(page, testInfo, '20-premium-user-empty-videos.png');
      await uploadFixtureAsName(page, sourceVideoFileName, uploadFileName);
      await shot(page, testInfo, '21-premium-user-upload-complete.png');
      await openVideosTab(page);
      await expectVideosTableVisible(page);
      const premiumUploadedRow = activeVideoRowByTitle(page, videoBaseName);
      await expectVideoRowHasCoreValues(premiumUploadedRow, videoBaseName);
      await shot(page, testInfo, '22-premium-user-video-visible.png');
      await premiumUploadedRow.click({ timeout: UI_TIMEOUT });
      await waitForVideoDetailsVisible(page);
      await expectVideoDetailsTitle(page, videoBaseName);
      await waitForPosterAndMeta(page, testInfo, '23-premium-poster-meta-attempt');
      await shot(page, testInfo, '23b-premium-video-details-ready.png');
      await assertPresetAvailable(page, STANDARD_PRESET);
      await clickHeightButtonInPreset(page, STANDARD_PRESET, 1080);
      await shot(page, testInfo, '24-premium-1080p-clicked.png');
      await page.waitForTimeout(400);
      await clickHeightButtonInPreset(page, STANDARD_PRESET, 720);
      await shot(page, testInfo, '24-premium-720p-clicked.png');
      // ── Phase D — Premium: both tasks run concurrently, complete, download ───
      await waitForAllPresets(
        page,
        REQUIRED_TASKS,
        (s) => s.hasCancel && (s.status === 'PENDING' || s.status === 'PROCESSING' || s.status === 'STARTING'),
        'premium: both tasks started with cancel buttons',
        10000,
        2000,
      );
      await shot(page, testInfo, '25-premium-tasks-started.png');
      await waitForAllPresets(
        page,
        REQUIRED_TASKS,
        (s) => s.status === 'COMPLETED' && s.hasDownload,
        'premium: both tasks completed with download links',
        5 * 60 * 1000,
        2500,
      );
      await shot(page, testInfo, '26-premium-tasks-completed.png');
      for (const task of REQUIRED_TASKS) {
        await clickDownloadAndVerifyByHeight(page, task.title, task.height);
        await shot(page, testInfo, `27-premium-download-${task.height}p.png`);
      }
      await openHome(page);
      await logoutToPublic(page);
      await shot(page, testInfo, '29-premium-user-signed-out.png');
    } finally {
      await attachSseMessages(page, testInfo);
      if (!freeVideoDeleted) {
        try {
          await ensureLoggedOut(page);
          await loginAs(page, freeEmail, freePassword);
          await expectTabsVisible(page);
          await logoutToPublic(page).catch(() => {});
        } catch {
          // ignore cleanup errors
        }
      }
      if (!premiumVideoDeleted) {
        try {
          await ensureLoggedOut(page);
          await loginAs(page, premiumEmail, premiumPassword);
          await expectTabsVisible(page);
          await logoutToPublic(page).catch(() => {});
        } catch {
          // ignore cleanup errors
        }
      }
    }
  });
});
