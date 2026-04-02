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
  return uploadRoot(page).locator('xpath=following-sibling::p[contains(@class, "text-muted")][1]').first();
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

async function uploadFixtureAsName(page, sourceFileName, uploadAsName) {
  const fileBuffer = fs.readFileSync(fixturePath(sourceFileName));

  await openUploadTab(page);
  await expectUploadDashboardVisible(page);
  await chooseAndUpload(page, {
    name: uploadAsName,
    mimeType: 'video/mp4',
    buffer: fileBuffer,
  });

  await openVideosTab(page);
  await expectVideosTableVisible(page);
}

async function uploadFixtureAsNameExpectingFailure(page, sourceFileName, uploadAsName, expectedErrorText) {
  const fileBuffer = fs.readFileSync(fixturePath(sourceFileName));

  await openUploadTab(page);
  await expectUploadDashboardVisible(page);
  await chooseAndUpload(page, {
    name: uploadAsName,
    mimeType: 'video/mp4',
    buffer: fileBuffer,
  }, { expectedErrorText });
}

module.exports = {
  fixturePath,
  uploadRoot,
  uploadHint,
  expectUploadHintText,
  expectUploadErrorText,
  uploadFixture,
  uploadFixtureAsName,
  uploadFixtureAsNameExpectingFailure,
};

