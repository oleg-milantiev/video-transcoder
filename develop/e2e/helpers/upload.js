const fs = require('fs');
const path = require('path');
const { expect } = require('@playwright/test');
const { UI_TIMEOUT, UPLOAD_TIMEOUT } = require('./constants');
const { openUploadTab, openVideosTab, expectUploadDashboardVisible, expectVideosTableVisible } = require('./mainApp');

function fixturePath(fileName) {
  return path.join('/work/e2e', fileName);
}

function uploadRoot(page) {
  return page.locator('#drag-drop-area').first();
}

function uploadHint(page) {
  // TariffHint is rendered as a div with class 'bg-light bg-opacity-10 rounded-4 p-3 mt-3'
  // This container holds all the storage, max file size, max resolution, and concurrent tasks info
  return page.locator('div.bg-light.bg-opacity-10.rounded-4.p-3.mt-3').first();
}

async function expectUploadHintText(page, expectedText) {
  await openUploadTab(page);
  await expectUploadDashboardVisible(page);
  await expect(uploadHint(page)).toContainText(expectedText, { timeout: UI_TIMEOUT });
}

async function expectUploadErrorText(page, expectedText, timeout = UPLOAD_TIMEOUT) {
  await expect(uploadRoot(page)).toContainText(expectedText, { timeout });
}

async function chooseAndUpload(page, filePayload, { expectedErrorText = null } = {}) {
  const fileChooserPromise = page.waitForEvent('filechooser');
  await page.locator('#drag-drop-area .uppy-Dashboard-browse').click({ timeout: UI_TIMEOUT });
  const fileChooser = await fileChooserPromise;
  await fileChooser.setFiles(filePayload);

  if (expectedErrorText) {
    await expectUploadErrorText(page, expectedErrorText);
    return;
  }

  await expect(page.locator('#drag-drop-area .uppy-StatusBar-content[role="status"][title="Complete"]')).toBeVisible({
    timeout: UPLOAD_TIMEOUT,
  });
}

async function uploadFixture(page, sourceFileName) {
  await openUploadTab(page);
  await expectUploadDashboardVisible(page);
  await chooseAndUpload(page, fixturePath(sourceFileName));
}

async function uploadFixtureAs(page, sourceFileName, uploadAsName, { expectedErrorText = null } = {}) {
  const fileBuffer = fs.readFileSync(fixturePath(sourceFileName));

  await openUploadTab(page);
  await expectUploadDashboardVisible(page);

  // If Uppy already has files queued (shows "Add more" button), click it first
  const addMore = page.locator('button.uppy-DashboardContent-addMore');
  if (await addMore.isVisible({ timeout: 1000 }).catch(() => false)) {
    await addMore.click({ force: true });
  }
  await chooseAndUpload(page, {
    name: uploadAsName,
    mimeType: 'video/mp4',
    buffer: fileBuffer,
  }, { expectedErrorText });

  if (!expectedErrorText) {
    await openVideosTab(page);
    await expectVideosTableVisible(page);
  }
}

/** @deprecated Use uploadFixtureAs(page, sourceFileName, uploadAsName) */
async function uploadFixtureAsName(page, sourceFileName, uploadAsName) {
  return uploadFixtureAs(page, sourceFileName, uploadAsName);
}

/** @deprecated Use uploadFixtureAs(page, sourceFileName, uploadAsName, { expectedErrorText }) */
async function uploadFixtureAsNameExpectingFailure(page, sourceFileName, uploadAsName, expectedErrorText) {
  return uploadFixtureAs(page, sourceFileName, uploadAsName, { expectedErrorText });
}

module.exports = {
  fixturePath,
  uploadRoot,
  uploadHint,
  expectUploadHintText,
  expectUploadErrorText,
  uploadFixture,
  uploadFixtureAs,
  uploadFixtureAsName,
  uploadFixtureAsNameExpectingFailure,
};

