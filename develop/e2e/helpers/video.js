const { expect } = require('@playwright/test');
const { UI_TIMEOUT, NAV_TIMEOUT } = require('./constants');
const { shot } = require('./screenshot');

async function expectDetailsValue(page, label) {
  const dt = page.locator('dt', { hasText: label }).first();
  await expect(dt).toBeVisible({ timeout: UI_TIMEOUT });

  const dd = dt.locator('xpath=following-sibling::dd[1]');
  await expect(dd).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(dd).not.toHaveText('-', { timeout: UI_TIMEOUT });
  await expect(dd).toHaveText(/\S+/, { timeout: UI_TIMEOUT });
}

async function renameVideoFromDetails(page, newTitle) {
  const renameButton = page.locator('button[title="Rename video"]').first();
  await expect(renameButton).toBeVisible({ timeout: UI_TIMEOUT });
  await renameButton.click({ timeout: UI_TIMEOUT });

  const renameModalInput = page.locator('.swal2-input').first();
  await expect(renameModalInput).toBeVisible({ timeout: UI_TIMEOUT });
  await renameModalInput.fill(newTitle);

  const confirmButton = page.locator('.swal2-confirm').first();
  await expect(confirmButton).toBeVisible({ timeout: UI_TIMEOUT });
  await confirmButton.click({ timeout: UI_TIMEOUT });
}

async function expectVideoDetailsTitle(page, expectedTitle) {
  const titleLabel = page.locator('dt', { hasText: 'Title' }).first();
  await expect(titleLabel).toBeVisible({ timeout: UI_TIMEOUT });

  const titleValue = titleLabel.locator('xpath=following-sibling::dd[1]//span[1]').first();
  await expect(titleValue).toBeVisible({ timeout: UI_TIMEOUT });
  await expect.poll(
    async () => ((await titleValue.textContent()) || '').trim(),
    { timeout: 30000 }
  ).toBe(expectedTitle);
}

async function clickBackButton(page) {
  await page.getByRole('button', { name: 'Back' }).click({ timeout: UI_TIMEOUT });
}


function presetsTable(page) {
  const heading = page.getByRole('heading', { name: 'Presets' }).first();
  return heading.locator('xpath=following-sibling::table[1]');
}

function presetRow(page, presetTitle) {
  return presetsTable(page).locator('tbody tr', { hasText: presetTitle }).first();
}

async function readPresetTaskState(page, presetTitle) {
  const row = presetRow(page, presetTitle);
  await expect(row).toBeVisible({ timeout: UI_TIMEOUT });

  const status = (await row.locator('td').nth(1).innerText({ timeout: UI_TIMEOUT })).trim();
  const progressText = (await row.locator('td').nth(2).innerText({ timeout: UI_TIMEOUT })).trim();
  const progressMatch = progressText.match(/(\d+)\s*%/);
  const progress = progressMatch ? Number(progressMatch[1]) : -1;

  return { status, progress };
}

async function waitForVideoDetailsVisible(page) {
  await expect(page.getByRole('heading', { name: 'Video Details' })).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(page.getByRole('heading', { name: 'Presets' })).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(presetsTable(page)).toBeVisible({ timeout: UI_TIMEOUT });
}

async function expectFlashPopupTitle(page, titleText, timeout = 30000) {
  const toastTitle = page.locator('.app-flash-toast .app-flash-title', { hasText: titleText }).last();
  await expect(toastTitle).toBeVisible({ timeout });
}

async function isPosterLoaded(page) {
  const posterByName = page.getByRole('img', { name: /\.mp4$/i }).first();
  const posterLegacy = page.locator('.card-body img.img-fluid').first();
  const poster = (await posterByName.count()) > 0 ? posterByName : posterLegacy;

  if ((await poster.count()) === 0) {
    return false;
  }

  return poster.evaluate((img) => {
    if (!(img instanceof HTMLImageElement)) {
      return false;
    }

    return Boolean(img.currentSrc) && img.complete && img.naturalWidth > 0 && img.naturalHeight > 0;
  });
}

async function hasDurationMeta(page) {
  const metaHeading = page.getByRole('heading', { name: 'Meta' }).first();
  if ((await metaHeading.count()) === 0) {
    return false;
  }

  const metaList = metaHeading.locator('xpath=following-sibling::ul[1]');
  if ((await metaList.count()) === 0) {
    return false;
  }

  const durationItem = metaList.locator('li', {
    has: page.locator('strong', { hasText: /^\s*duration\s*:\s*$/i }),
  }).first();
  if ((await durationItem.count()) === 0) {
    return false;
  }

  const value = (await durationItem.textContent())
    ?.replace(/^\s*duration\s*:\s*/i, '')
    .trim() || '';

  return value.length > 0 && value !== '-' && !/no\s+meta\s+data/i.test(value);
}

async function waitForPosterAndMeta(page, testInfo, prefix = '07-details-poster-meta-ready-attempt') {
  const maxAttempts = 5;

  for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
    const posterLoaded = await isPosterLoaded(page);
    const durationReady = await hasDurationMeta(page);

    if (posterLoaded && durationReady) {
      await shot(page, testInfo, `${prefix}-${attempt}.png`);
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

module.exports = {
  expectDetailsValue,
  renameVideoFromDetails,
  expectVideoDetailsTitle,
  clickBackButton,
  presetsTable,
  presetRow,
  readPresetTaskState,
  waitForVideoDetailsVisible,
  expectFlashPopupTitle,
  waitForPosterAndMeta,
};

