const { test } = require('@playwright/test');
const {
  UI_TIMEOUT,
  loginAs,
  expectUploadDashboardVisible,
  openVideosTab,
  expectVideosTableVisible,
  videoRowByTitle,
  expectVideoRowHasCoreValues,
  uploadFixture,
  waitForVideoDetailsVisible,
  expectDetailsValue,
  clickBackButton,
  renameVideoFromDetails,
  expectVideoDetailsTitle,
  waitForPosterAndMeta,
  logoutToPublic,
  shot,
} = require('../helpers');

const TEST_01_EMAIL    = 'test-01@test.com';
const TEST_01_PASSWORD = 'test-01';

test('01 · upload video, verify details and rename', async ({ page }, testInfo) => {
  const fileName        = '2022_10_04_Two_Maxes.mp4';
  const baseFileName    = fileName.substring(0, fileName.lastIndexOf('.'));
  const renamedFileName = `${baseFileName}-01`;

  // Step 1 — Login as test-01
  await loginAs(page, TEST_01_EMAIL, TEST_01_PASSWORD);

  // Step 2 — Upload tab is shown by default; verify Uppy dashboard is ready
  await expectUploadDashboardVisible(page);
  await shot(page, testInfo, '01-upload-tab-ready.png');

  // Step 3 — Upload via Uppy file picker
  await uploadFixture(page, fileName);
  await shot(page, testInfo, '02-uppy-upload-complete.png');

  // Step 4 — Open Videos tab and verify the uploaded row appears
  await openVideosTab(page);
  await expectVideosTableVisible(page);
  const videoRow = videoRowByTitle(page, baseFileName);
  await expectVideoRowHasCoreValues(videoRow, baseFileName);
  await shot(page, testInfo, '03-video-row-in-table.png');

  // Step 5 — Open video details
  await videoRow.click({ timeout: UI_TIMEOUT });
  await waitForVideoDetailsVisible(page);

  await expectDetailsValue(page, 'Title');
  await expectDetailsValue(page, 'Created');
  await expectDetailsValue(page, 'Expires');
  await waitForPosterAndMeta(page, testInfo);
  await shot(page, testInfo, '04-video-details-filled.png');

  // Step 6 — Rename video via SweetAlert2 modal
  await renameVideoFromDetails(page, renamedFileName);
  await expectVideoDetailsTitle(page, renamedFileName);
  await shot(page, testInfo, '05-video-renamed-in-details.png');

  // Step 7 — Go back to Videos list and confirm the renamed row
  await clickBackButton(page);
  await expectVideosTableVisible(page);
  const renamedVideoRow = videoRowByTitle(page, renamedFileName);
  await expectVideoRowHasCoreValues(renamedVideoRow, renamedFileName);
  await shot(page, testInfo, '06-video-row-renamed-in-table.png');

  // Step 8 — Re-open details by clicking the renamed row and confirm title persists
  await renamedVideoRow.click({ timeout: UI_TIMEOUT });
  await waitForVideoDetailsVisible(page);
  await expectVideoDetailsTitle(page, renamedFileName);
  await shot(page, testInfo, '07-video-details-title-persists.png');

  // Step 9 — Sign out
  await logoutToPublic(page);
  await shot(page, testInfo, '08-sign-out.png');
});

