const { expect } = require('@playwright/test');
const { UI_TIMEOUT, NAV_TIMEOUT } = require('./constants');
const { shot } = require('./screenshot');

function escapeRegExp(value) {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

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

  const rawStatus = (await row.locator('td').nth(1).innerText({ timeout: UI_TIMEOUT })).trim();
  const status = rawStatus.replace(/\s+\?\s*$/, '').trim();
  const progressText = (await row.locator('td').nth(2).innerText({ timeout: UI_TIMEOUT })).trim();
  const progressMatch = progressText.match(/(\d+)\s*%/);
  const progress = progressMatch ? Number(progressMatch[1]) : -1;

  return { status, progress };
}

async function waitForVideoDetailsVisible(page, { requirePresets = true } = {}) {
  await expect(page.getByRole('heading', { name: 'Video Details' })).toBeVisible({ timeout: UI_TIMEOUT });
  if (!requirePresets) {
    return;
  }
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

async function getAllPresetTitles(page) {
  const rows = await presetsTable(page).locator('tbody tr').all();
  const titles = [];
  for (const row of rows) {
    const title = (await row.locator('td').nth(0).innerText()).trim();
    if (title) titles.push(title);
  }
  return titles;
}

async function expectAllPresetsToShowTranscodeWithExpectedSize(page) {
  const titles = await getAllPresetTitles(page);
  expect(titles.length).toBeGreaterThan(0);

  for (const title of titles) {
    const row = presetRow(page, title);
    await expect(row.getByRole('button', { name: 'Transcode' })).toBeVisible({ timeout: UI_TIMEOUT });
    await expect(row.locator('td').nth(4)).toContainText('Expected size:', { timeout: UI_TIMEOUT });
  }

  return titles;
}

async function expectPresetTranscodeDisabledWithHint(page, presetTitle, { expectedSizeText, tooltipText } = {}) {
  const row = presetRow(page, presetTitle);
  const transcodeButton = row.getByRole('button', { name: 'Transcode' });
  await expect(transcodeButton).toBeDisabled({ timeout: UI_TIMEOUT });

  const actionCell = row.locator('td').nth(4);
  if (expectedSizeText) {
    await expect(actionCell).toContainText(expectedSizeText, { timeout: UI_TIMEOUT });
  } else {
    await expect(actionCell).toContainText('Expected size:', { timeout: UI_TIMEOUT });
  }

  const helpIcon = actionCell.getByRole('img').first();
  await expect(helpIcon).toBeVisible({ timeout: UI_TIMEOUT });
  await helpIcon.hover({ timeout: UI_TIMEOUT });

  if (tooltipText) {
    const tooltipPattern = new RegExp(escapeRegExp(tooltipText), 'i');
    await expect(helpIcon).toHaveAttribute('title', tooltipPattern, { timeout: UI_TIMEOUT });
    await expect(helpIcon).toHaveAttribute('aria-label', tooltipPattern, { timeout: UI_TIMEOUT });
  }

  return helpIcon;
}

async function waitForDeletedVideoDetailsWithoutPoster(page, expectedTitle, maxAttempts = 8, delayMs = 5000) {
  for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
    await waitForVideoDetailsVisible(page, { requirePresets: false });

    const deletedTitle = page.locator('dd.video-title-deleted').first();
    const hasDeletedTitle = (await deletedTitle.count()) > 0;
    const hasPoster = (await page.locator('.card-body img.img-fluid').count()) > 0;

    if (hasDeletedTitle && !hasPoster) {
      await expect(deletedTitle).toContainText(expectedTitle, { timeout: UI_TIMEOUT });
      return;
    }

    if (attempt < maxAttempts) {
      await page.waitForTimeout(delayMs);
      await page.reload({ waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
    }
  }

  throw new Error(`Video ${expectedTitle} did not become deleted without poster after ${maxAttempts} checks`);
}

async function clickTranscodeForPreset(page, presetTitle) {
  const row = presetRow(page, presetTitle);
  const btn = row.getByRole('button', { name: 'Transcode' });
  await expect(btn).toBeVisible({ timeout: UI_TIMEOUT });
  await btn.click({ timeout: UI_TIMEOUT });
}

async function expectPresetStatus(page, presetTitle, expectedStatus) {
  const { status } = await readPresetTaskState(page, presetTitle);
  expect(status).toContain(expectedStatus);
}

async function waitForAllPresetsToComplete(page, presetTitles, maxAttempts = 24, delayMs = 5000) {
  for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
    let allDone = true;
    for (const title of presetTitles) {
      const { status } = await readPresetTaskState(page, title);
      if (status !== 'COMPLETED') {
        allDone = false;
        break;
      }
    }
    if (allDone) return;
    if (attempt < maxAttempts) await page.waitForTimeout(delayMs);
  }
  throw new Error(`Not all presets reached COMPLETED after ${maxAttempts} attempts (${(maxAttempts * delayMs) / 1000}s)`);
}

/**
 * Polls every pollMs until ALL specified presets are simultaneously PROCESSING with progress > 0.
 * Verifies parallel execution: every preset must be in PROCESSING state (not just one at a time).
 */
async function waitForAllPresetsProcessingWithProgress(page, presetTitles, maxAttempts = 90, pollMs = 1000) {
  for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
    let allProcessing = true;
    for (const title of presetTitles) {
      const { status, progress } = await readPresetTaskState(page, title);
      if (status !== 'PROCESSING' || progress <= 0) {
        allProcessing = false;
        break;
      }
    }
    if (allProcessing) return;
    if (attempt < maxAttempts) await page.waitForTimeout(pollMs);
  }
  throw new Error(
    `Not all presets [${presetTitles.join(', ')}] reached PROCESSING with progress > 0 after ${maxAttempts}s`,
  );
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
  getAllPresetTitles,
  expectAllPresetsToShowTranscodeWithExpectedSize,
  expectPresetTranscodeDisabledWithHint,
  waitForDeletedVideoDetailsWithoutPoster,
  clickTranscodeForPreset,
  expectPresetStatus,
  waitForAllPresetsToComplete,
  waitForAllPresetsProcessingWithProgress,
};

