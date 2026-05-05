const { test } = require('@playwright/test');
const {
  UI_TIMEOUT,
  loginAsAdmin,
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

test('upload video and verify details flow', async ({ page }, testInfo) => {
  const fileName = '2022_10_04_Two_Maxes.mp4';
  const baseFileName = fileName.substring(0, fileName.lastIndexOf('.'));
  const renamedBaseFileName = `${baseFileName}-02`;

  await loginAsAdmin(page);

  await expectUploadDashboardVisible(page);
  await shot(page, testInfo, '01-upload-tab-ready.png');

  await uploadFixture(page, fileName);
  await shot(page, testInfo, '02-uppy-upload-complete.png');

  await openVideosTab(page);
  await expectVideosTableVisible(page);
  const videoRow = videoRowByTitle(page, baseFileName);

  await expectVideoRowHasCoreValues(videoRow, baseFileName);
  await shot(page, testInfo, '03-video-row-in-table.png');

  await videoRow.click({ timeout: UI_TIMEOUT });
  await waitForVideoDetailsVisible(page);

  await expectDetailsValue(page, 'Title');
  await expectDetailsValue(page, 'Created');
  await waitForPosterAndMeta(page, testInfo);
  await shot(page, testInfo, '04-video-details-filled.png');

  await renameVideoFromDetails(page, renamedBaseFileName);
  await expectVideoDetailsTitle(page, renamedBaseFileName);
  await shot(page, testInfo, '05-video-renamed-in-details.png');

  await clickBackButton(page);
  await expectVideosTableVisible(page);
  const renamedVideoRow = videoRowByTitle(page, renamedBaseFileName);
  await expectVideoRowHasCoreValues(renamedVideoRow, renamedBaseFileName);
  await shot(page, testInfo, '06-video-row-renamed-in-table.png');

  await renamedVideoRow.click({ timeout: UI_TIMEOUT });
  await waitForVideoDetailsVisible(page);
  await expectVideoDetailsTitle(page, renamedBaseFileName);

  await logoutToPublic(page);
  await shot(page, testInfo, '08-sign-out-and-sign-in-links.png');
});

