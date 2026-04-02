const { test, expect } = require('@playwright/test');
const { attachConsoleCapture } = require('../consoleCapture');
const {
  UI_TIMEOUT,
  NAV_TIMEOUT,
  getAdminCredentials,
  loginAsAdmin,
  logoutToPublic,
  openHome,
  openAdminDashboardFromHome,
  assignTariffToUser,
  createOrUpdateTariffByTitle,
  uploadFixtureAsName,
  uploadFixtureAsNameExpectingFailure,
  expectUploadHintText,
  openVideosTab,
  expectVideosTableVisible,
  expectVideoRowHasCoreValues,
  videoRowByTitle,
  activeVideoRowByTitle,
  waitForVideoDetailsVisible,
  expectVideoDetailsTitle,
  waitForPosterAndMeta,
  expectAllPresetsToShowTranscodeWithExpectedSize,
  expectPresetTranscodeDisabledWithHint,
  waitForDeletedVideoDetailsWithoutPoster,
  shot, createUserWithTariff, clickAndAcceptConfirm,
} = require('../helpers');

function baseName(fileName) {
  return fileName.substring(0, fileName.lastIndexOf('.'));
}

test('tariff restrictions: upload limits, invalid metadata deletion and transcode availability', async ({ page }, testInfo) => {
  const capture = attachConsoleCapture(page, testInfo, { maxBodyChars: 4000 });
  await capture.start();

  const { email: adminEmail } = getAdminCredentials();
  const sourceVideoFileName = '2022_10_04_Two_Maxes.mp4';
  const successfulUploadName = '2022_10_04_Two_Maxes-08-success.mp4';
  const successfulBaseName = baseName(successfulUploadName);
  const freeTariff = {
    delay: 3600,
    instance: 1,
    videoDuration: 3600,
    videoSize: 100,
    maxWidth: 1920,
    maxHeight: 1080,
    storageGb: 1,
    storageHour: 24,
  };
  const tariffCases = [
    {
      title: 'Free-filesize',
      tariff: { ...freeTariff, videoSize: 3 },
      uploadName: '2022_10_04_Two_Maxes-08-filesize.mp4',
      uploadHintText: 'Max file size: 3 MB.',
      expectedUploadError: 'exceeds maximum allowed size',
    },
    {
      title: 'Free-storage',
      tariff: { ...freeTariff, storageGb: 0.01 },
      uploadName: '2022_10_04_Two_Maxes-08-storage.mp4',
      uploadHintText: 'as storage is running low',
      expectedUploadError: 'exceeds maximum allowed size',
    },
    {
      title: 'Free-resolution',
      tariff: { ...freeTariff, maxWidth: 320, maxHeight: 180 },
      uploadName: '2022_10_04_Two_Maxes-08-resolution.mp4',
      uploadHintText: 'Max resolution: 320',
      expectDeletedWithoutPoster: true,
    },
    {
      title: 'Free-duration',
      tariff: { ...freeTariff, videoDuration: 2 },
      uploadName: '2022_10_04_Two_Maxes-08-duration.mp4',
      expectDeletedWithoutPoster: true,
    },
  ];

  const assignTariffAndRefreshSession = async (tariffTitle, screenshotName) => {
    await openHome(page);
    await openAdminDashboardFromHome(page);
    await assignTariffToUser(page, adminEmail, tariffTitle, testInfo, screenshotName);
    await logoutToPublic(page);
    await loginAsAdmin(page);
  };

  const verifyDeletedVideoFromListAndDetails = async (uploadedFileName, screenshotPrefix) => {
    const uploadedBaseName = baseName(uploadedFileName);

    let row;
    for (let attempt = 1; attempt <= 12; attempt += 1) {
      await openVideosTab(page);
      await expectVideosTableVisible(page);

      row = videoRowByTitle(page, uploadedBaseName);
      await expect(row).toBeVisible({ timeout: NAV_TIMEOUT });

      const isDeleted = (await row.locator('td.video-title-deleted').count()) > 0;
      const hasNoPoster = (((await row.textContent()) || '').includes('No poster'));

      if (isDeleted && hasNoPoster) {
        break;
      }

      if (attempt === 12) {
        throw new Error(`Video ${uploadedBaseName} did not reach deleted + no-poster state in Videos list after 12 checks`);
      }

      await page.waitForTimeout(5000);
      await page.reload({ waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
    }

    await shot(page, testInfo, `${screenshotPrefix}-list.png`);

    await row.click({ timeout: UI_TIMEOUT });
    await waitForDeletedVideoDetailsWithoutPoster(page, uploadedBaseName, 12, 5000);
    await shot(page, testInfo, `${screenshotPrefix}-details.png`);
  };

  try {
    // Phase 1 — Admin starts on Free tariff, uploads a valid source video and verifies presets UI.
    await loginAsAdmin(page);
    // delete old 05 video
    await openVideosTab(page);
    await expectVideosTableVisible(page);
    const listRow = videoRowByTitle(page, '2022_10_04_Two_Maxes-05');
    await expect(listRow).toBeVisible({ timeout: NAV_TIMEOUT });
    await clickAndAcceptConfirm(
        page,
        listRow.getByRole('button', { name: 'Delete' }),
        'Delete this video?'
    );
    await shot(page, testInfo, '01-delete-old-video.png');

    await assignTariffAndRefreshSession('Free', '02-admin-free-assigned.png');
    await shot(page, testInfo, '02b-admin-relogin-after-free.png');

    await uploadFixtureAsName(page, sourceVideoFileName, successfulUploadName);
    await shot(page, testInfo, '03-success-uploaded.png');

    await openVideosTab(page);
    await expectVideosTableVisible(page);
    const successfulRow = activeVideoRowByTitle(page, successfulBaseName);
    await expectVideoRowHasCoreValues(successfulRow, successfulBaseName);
    await shot(page, testInfo, '04-success-video-row.png');

    await successfulRow.click({ timeout: UI_TIMEOUT });
    await waitForVideoDetailsVisible(page);
    await expectVideoDetailsTitle(page, successfulBaseName);
    await waitForPosterAndMeta(page, testInfo, '05-success-poster-meta-attempt');
    await expectAllPresetsToShowTranscodeWithExpectedSize(page);
    await shot(page, testInfo, '05b-success-video-details-and-presets.png');

    // Phase 2 — Create tariff variants based on Free.
    await openHome(page);
    await openAdminDashboardFromHome(page);
    for (const [index, tariffCase] of tariffCases.entries()) {
      await createOrUpdateTariffByTitle(
        page,
        tariffCase.title,
        tariffCase.tariff,
        testInfo,
        `06-tariff-${index + 1}-${tariffCase.title}.png`,
      );
    }
    await shot(page, testInfo, '06b-tariff-variants-ready.png');

    // Phase 3 — Apply each tariff to admin, verify upload hint / failure / deletion behavior.
    for (const [index, tariffCase] of tariffCases.entries()) {
      await assignTariffAndRefreshSession(tariffCase.title, `07-${index + 1}-${tariffCase.title}-assigned.png`);

      if (tariffCase.uploadHintText) {
        await expectUploadHintText(page, tariffCase.uploadHintText);
        await shot(page, testInfo, `07-${index + 1}-${tariffCase.title}-upload-hint.png`);
      }

      if (tariffCase.expectedUploadError) {
        await uploadFixtureAsNameExpectingFailure(
          page,
          sourceVideoFileName,
          tariffCase.uploadName,
          tariffCase.expectedUploadError,
        );
        await shot(page, testInfo, `07-${index + 1}-${tariffCase.title}-upload-rejected.png`);
        continue;
      }

      await uploadFixtureAsName(page, sourceVideoFileName, tariffCase.uploadName);
      await shot(page, testInfo, `07-${index + 1}-${tariffCase.title}-uploaded.png`);
      await verifyDeletedVideoFromListAndDetails(tariffCase.uploadName, `07-${index + 1}-${tariffCase.title}-deleted`);
    }

    // Phase 4 — Low-storage tariff disables large transcodes for the valid video.
    await assignTariffAndRefreshSession('Free-storage', '08-admin-free-storage-reassigned.png');
    await openVideosTab(page);
    await expectVideosTableVisible(page);

    const successfulRowAgain = activeVideoRowByTitle(page, successfulBaseName);
    await expect(successfulRowAgain).toBeVisible({ timeout: NAV_TIMEOUT });
    await successfulRowAgain.click({ timeout: UI_TIMEOUT });
    await waitForVideoDetailsVisible(page);
    await expectVideoDetailsTitle(page, successfulBaseName);

    await expectPresetTranscodeDisabledWithHint(page, 'FHD', {
      expectedSizeText: 'Expected size: 4.7 MB',
      tooltipText: 'This video cannot be transcoded',
    });
    await shot(page, testInfo, '09-fhd-disabled-by-free-storage.png');

    await logoutToPublic(page);
    await shot(page, testInfo, '10-sign-out.png');
  } finally {
    try {
      const sseMessages = await page.evaluate(() => (window.__mercure_messages || []));
      await testInfo.attach('mercure-sse.json', {
        body: Buffer.from(JSON.stringify(sseMessages, null, 2), 'utf-8'),
        contentType: 'application/json',
      });
    } catch (e) {
      // ignore
    }

    await capture.flushAndAttach();
  }
});

