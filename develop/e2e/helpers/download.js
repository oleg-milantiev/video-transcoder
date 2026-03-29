const { expect } = require('@playwright/test');
const { UI_TIMEOUT, NAV_TIMEOUT } = require('./constants');

async function clickDownloadAndVerifyMp4(page, row) {
  const downloadLink = row.getByRole('link', { name: 'Download' });
  await expect(downloadLink).toBeVisible({ timeout: UI_TIMEOUT });

  const href = await downloadLink.getAttribute('href', { timeout: UI_TIMEOUT });
  if (!href) {
    throw new Error('Download link href is empty');
  }

  const downloadPromise = page.waitForEvent('download', { timeout: 15000 }).catch(() => null);
  await downloadLink.click({ timeout: UI_TIMEOUT });

  const download = await downloadPromise;

  const downloadUrl = new URL(href, page.url()).toString();
  const response = await page.request.get(downloadUrl, {
    failOnStatusCode: false,
    maxRedirects: 0,
    timeout: NAV_TIMEOUT,
  });

  expect(response.status()).toBeLessThan(400);
  const location = (response.headers().location || '').toLowerCase();
  const locationRaw = response.headers().location || '';
  let resolvedMp4Url = '';
  if (location) {
    expect(location).toContain('.mp4');
    resolvedMp4Url = new URL(locationRaw, downloadUrl).toString();
  } else if (downloadUrl.toLowerCase().includes('.mp4')) {
    resolvedMp4Url = downloadUrl;
  }

  if (download) {
    const failure = await download.failure();
    expect(failure === null || failure === 'canceled').toBeTruthy();
    expect(download.suggestedFilename().toLowerCase()).toMatch(/\.mp4$/);
  }

  if (!resolvedMp4Url) {
    throw new Error('Could not resolve final mp4 URL from download response');
  }

  return resolvedMp4Url;
}

async function expectDownloadFilename(page, expectedFilename) {
  const downloadLink = page.locator('a:has-text("Download")').last();
  await downloadLink.waitFor({ state: 'attached', timeout: UI_TIMEOUT });

  await expect(downloadLink).toHaveAttribute('download', expectedFilename, {
    timeout: UI_TIMEOUT
  });
}

async function expectRowDownloadFilename(row, expectedFilename) {
  const downloadLink = row.getByRole('link', { name: 'Download' });
  await expect(downloadLink).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(downloadLink).toHaveAttribute('download', expectedFilename, { timeout: UI_TIMEOUT });
}

module.exports = { clickDownloadAndVerifyMp4, expectDownloadFilename, expectRowDownloadFilename };

