const { expect } = require('@playwright/test');
const { UI_TIMEOUT, NAV_TIMEOUT } = require('./constants');
const { taskRowByPresetAndHeight } = require('./video');

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
  // Match completed task download links: <a class="btn ..." download="...">Download</a>
  const downloadLink = page.locator('a.btn[download]').last();
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

/**
 * Find the completed task row for preset+height, click the Download link,
 * verify the suggested filename contains `{height}p` and ends in `.mp4`,
 * and confirm the download URL responds with status < 400.
 */
async function clickDownloadAndVerifyByHeight(page, presetTitle, height) {
  const row = taskRowByPresetAndHeight(page, presetTitle, height);
  await expect(row).toBeVisible({ timeout: UI_TIMEOUT });
  const downloadLink = row.getByRole('link', { name: 'Download' });
  await expect(downloadLink).toBeVisible({ timeout: UI_TIMEOUT });

  const href = await downloadLink.getAttribute('href');
  if (!href) throw new Error('Download link href is empty');

  const downloadPromise = page.waitForEvent('download', { timeout: 20000 });
  await downloadLink.click({ timeout: UI_TIMEOUT });
  const download = await downloadPromise;

  const suggested = download.suggestedFilename();
  expect(suggested).toContain(String(height) + 'p');
  expect(suggested).toMatch(/\.mp4$/i);

  const downloadUrl = new URL(href, page.url()).toString();
  const response = await page.request.get(downloadUrl, {
    failOnStatusCode: false,
    maxRedirects: 0,
    timeout: NAV_TIMEOUT,
  });
  expect(response.status()).toBeLessThan(400);
}

module.exports = { clickDownloadAndVerifyMp4, expectDownloadFilename, expectRowDownloadFilename, clickDownloadAndVerifyByHeight };

