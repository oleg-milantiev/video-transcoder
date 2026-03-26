const { test } = require('@playwright/test');
const {
  UI_TIMEOUT,
  getAdminCredentials,
  openHome,
  openSignIn,
  fillSignInCredentials,
  submitSignIn,
  expectUploadDashboardVisible,
  openVideosTab,
  expectVideosTableVisible,
  videoRowByTitle,
  expectVideoRowHasCoreValues,
  uploadFixture,
  waitForVideoDetailsVisible,
  expectDetailsValue,
  waitForPosterAndMeta,
  logoutToPublic,
  shot,
} = require('../helpers');

test('upload video and verify details flow', async ({ page }, testInfo) => {
  const { adminEmail, adminPassword } = getAdminCredentials();
  const fileName = '2022_10_04_Two_Maxes.mp4';

  await openHome(page);
  await openSignIn(page);
  await fillSignInCredentials(page, adminEmail, adminPassword);
  await submitSignIn(page);

  await expectUploadDashboardVisible(page);
  await shot(page, testInfo, '01-upload-tab-ready.png');

  await uploadFixture(page, fileName);
  await shot(page, testInfo, '02-uppy-upload-complete.png');

  await openVideosTab(page);
  await expectVideosTableVisible(page);
  const videoRow = videoRowByTitle(page, fileName);

  await expectVideoRowHasCoreValues(videoRow, fileName);
  await shot(page, testInfo, '03-video-row-in-table.png');

  await videoRow.click({ timeout: UI_TIMEOUT });
  await waitForVideoDetailsVisible(page);

  await expectDetailsValue(page, 'Title');
  await expectDetailsValue(page, 'Extension');
  await expectDetailsValue(page, 'Created At');
  await shot(page, testInfo, '04-video-details-filled.png');

  await waitForPosterAndMeta(page, testInfo);

  await logoutToPublic(page);
  await shot(page, testInfo, '08-sign-out-and-sign-in-links.png');
});

