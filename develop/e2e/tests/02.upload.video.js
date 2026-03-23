const path = require('path');
const { test, expect } = require('@playwright/test');

const UI_TIMEOUT = 8000;
const NAV_TIMEOUT = 15000;

async function shot(page, testInfo, name) {
  await page.screenshot({ path: testInfo.outputPath(name), fullPage: true });
}

async function expectDetailsValue(page, label) {
  const dt = page.locator('dt', { hasText: label }).first();
  await expect(dt).toBeVisible({ timeout: UI_TIMEOUT });

  const dd = dt.locator('xpath=following-sibling::dd[1]');
  await expect(dd).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(dd).not.toHaveText('-', { timeout: UI_TIMEOUT });
  await expect(dd).toHaveText(/\S+/, { timeout: UI_TIMEOUT });
}

async function isPosterLoaded(page) {
  // Support both legacy card image markup and current accessible image name markup.
  const posterByName = page.getByRole('img', { name: /\.mp4$/i }).first();
  const posterLegacy = page.locator('.card-body img.img-fluid').first();
  const poster = (await posterByName.count()) > 0 ? posterByName : posterLegacy;

  if ((await poster.count()) === 0) return false;

  return poster.evaluate((img) => {
    if (!(img instanceof HTMLImageElement)) {
      return false;
    }

    return Boolean(img.currentSrc) && img.complete && img.naturalWidth > 0 && img.naturalHeight > 0;
  });
}

async function hasDurationMeta(page) {
  const metaHeading = page.getByRole('heading', { name: 'Meta' }).first();
  if ((await metaHeading.count()) === 0) return false;

  const metaList = metaHeading.locator('xpath=following-sibling::ul[1]');
  if ((await metaList.count()) === 0) return false;

  const durationItem = metaList.locator('li', {
    has: page.locator('strong', { hasText: /^\s*duration\s*:\s*$/i }),
  }).first();
  if ((await durationItem.count()) === 0) return false;

  const value = (await durationItem.textContent())
    ?.replace(/^\s*duration\s*:\s*/i, '')
    .trim() || '';

  return value.length > 0 && value !== '-' && !/no\s+meta\s+data/i.test(value);
}

async function waitForPosterAndMeta(page, testInfo) {
  const maxAttempts = 5;

  for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
    const posterLoaded = await isPosterLoaded(page);
    const durationReady = await hasDurationMeta(page);

    if (posterLoaded && durationReady) {
      await shot(page, testInfo, `07-details-poster-meta-ready-attempt-${attempt}.png`);
      return;
    }

    if (attempt < maxAttempts) {
      await page.waitForTimeout(5000);
      await page.reload({ waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
      await expect(page.getByRole('heading', { name: 'Video Details' })).toBeVisible({ timeout: UI_TIMEOUT });
    }
  }

  throw new Error('Poster is not fully loaded or Meta duration is missing after 5 checks with 5-second delays');
}

test('upload video and verify details flow', async ({ page }, testInfo) => {
  const adminEmail = process.env.ADMIN_EMAIL || 'oleg@milantiev.com';
  const adminPassword = process.env.ADMIN_PASSWORD || 'admin';
  const fileName = '2022_10_04_Two_Maxes.mp4';
  const uploadFilePath = path.join('/work/e2e', fileName);

  await page.goto('/', { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
  await page.getByRole('link', { name: 'Sign in' }).last().click({ timeout: UI_TIMEOUT });
  await page.locator('#inputEmail').fill(adminEmail);
  await page.locator('#inputPassword').fill(adminPassword);
  await page.getByRole('button', { name: 'Sign in' }).click({ timeout: UI_TIMEOUT });

  await expect(page.getByRole('button', { name: 'Upload' })).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(page.locator('#drag-drop-area .uppy-Dashboard')).toBeVisible({ timeout: 30000 });
  await shot(page, testInfo, '01-upload-tab-ready.png');

  const fileChooserPromise = page.waitForEvent('filechooser');
  await page.locator('#drag-drop-area .uppy-Dashboard-browse').click({ timeout: UI_TIMEOUT });
  const fileChooser = await fileChooserPromise;
  await fileChooser.setFiles(uploadFilePath);
  await expect(page.locator('#drag-drop-area .uppy-StatusBar-content[role="status"][title="Complete"]')).toBeVisible({ timeout: 30000 });
  await shot(page, testInfo, '02-uppy-upload-complete.png');

  await page.getByRole('button', { name: 'Videos' }).click({ timeout: UI_TIMEOUT });
  await expect(page.locator('#videosTable')).toBeVisible({ timeout: UI_TIMEOUT });

  const videoRow = page.locator('#videosTable tbody tr', {
    has: page.locator('td', { hasText: fileName }),
  });

  await expect(videoRow).toBeVisible({ timeout: 15000 });
  await expect(videoRow.locator('td').nth(1)).toContainText(fileName, { timeout: UI_TIMEOUT });
  await expect(videoRow.locator('td').nth(2)).not.toHaveText('-', { timeout: UI_TIMEOUT });
  await shot(page, testInfo, '03-video-row-in-table.png');

  await videoRow.click({ timeout: UI_TIMEOUT });
  await expect(page.getByRole('heading', { name: 'Video Details' })).toBeVisible({ timeout: UI_TIMEOUT });

  await expectDetailsValue(page, 'Title');
  await expectDetailsValue(page, 'Extension');
  await expectDetailsValue(page, 'Created At');
  await expectDetailsValue(page, 'User ID');
  await shot(page, testInfo, '04-video-details-filled.png');

  await waitForPosterAndMeta(page, testInfo);

  // Sign out at the end of the flow (same as in 01.admin.login.js)
  await expect(page.getByRole('link', { name: 'Sign out' })).toBeVisible({ timeout: UI_TIMEOUT });
  await page.getByRole('link', { name: 'Sign out' }).click({ timeout: UI_TIMEOUT });
  await expect(page.getByRole('link', { name: 'Sign in' })).toHaveCount(2, { timeout: UI_TIMEOUT });
  await shot(page, testInfo, '08-sign-out-and-sign-in-links.png');
});

