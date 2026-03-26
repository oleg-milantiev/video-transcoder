const fs = require('fs');
const path = require('path');
const { expect } = require('@playwright/test');
const { UI_TIMEOUT, UPLOAD_TIMEOUT } = require('./constants');
const { openUploadTab, openVideosTab, expectUploadDashboardVisible, expectVideosTableVisible } = require('./mainApp');

function fixturePath(fileName) {
  return path.join('/work/e2e', fileName);
}

async function chooseAndUpload(page, filePayload) {
  const fileChooserPromise = page.waitForEvent('filechooser');
  await page.locator('#drag-drop-area .uppy-Dashboard-browse').click({ timeout: UI_TIMEOUT });
  const fileChooser = await fileChooserPromise;
  await fileChooser.setFiles(filePayload);
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

module.exports = {
  fixturePath,
  uploadFixture,
  uploadFixtureAsName,
};

