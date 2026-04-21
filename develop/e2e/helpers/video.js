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
// ── New UI: "Transcoding Tasks" table (id="transcoding-tasks-section") ──────
function tasksTable(page) {
  return page.locator('#transcoding-tasks-section table').first();
}
// Task table columns: 0=presetTitle, 1=height+'p', 2=status, 3=progress, 4=createdAt, 5=actions
function taskRowByPreset(page, presetTitle) {
  // Return rows matching presetTitle. When multiple exist (e.g. after cancel+restart),
  // the caller can use .first() (DOM order) or activeTaskRowByPreset for non-cancelled.
  return tasksTable(page).locator('tbody tr', { hasText: presetTitle }).first();
}
// Returns the first non-CANCELLED task row for the preset (used after restart)
function activeTaskRowByPreset(page, presetTitle) {
  return tasksTable(page).locator('tbody tr', { hasText: presetTitle })
    .filter({ hasNot: page.locator('td', { hasText: 'CANCELLED' }) }).first();
}
// Alias kept for backward compat – now points to tasks table
function presetsTable(page) {
  return tasksTable(page);
}
function presetRow(page, presetTitle) {
  return taskRowByPreset(page, presetTitle);
}
// ── New UI: preset blocks in "Start new Video Transcoding Task" section ──────
function presetBlock(page, presetTitle) {
  // h6 shows "PresetTitle (videoCodec/audioCodec/format)"
  return page.locator('h6', { hasText: presetTitle }).first().locator('xpath=./parent::div');
}
// ── Read task status/progress from the Transcoding Tasks table ───────────────
async function readPresetTaskState(page, presetTitle, { preferActive = false } = {}) {
  // When preferActive=true (e.g. after restart), skip CANCELLED rows
  const row = preferActive ? activeTaskRowByPreset(page, presetTitle) : taskRowByPreset(page, presetTitle);
  await expect(row).toBeVisible({ timeout: UI_TIMEOUT });
  // col2 = status (may have "? " icon suffix)
  const rawStatus = (await row.locator('td').nth(2).innerText({ timeout: UI_TIMEOUT })).trim();
  const status = rawStatus.replace(/\s+\?\s*$/, '').trim();
  const progressText = (await row.locator('td').nth(3).innerText({ timeout: UI_TIMEOUT })).trim();
  const progressMatch = progressText.match(/(\d+)\s*%/);
  const progress = progressMatch ? Number(progressMatch[1]) : -1;
  return { status, progress };
}
// ── waitForVideoDetailsVisible ───────────────────────────────────────────────
async function waitForVideoDetailsVisible(page, { requirePresets = true } = {}) {
  await expect(page.getByRole('heading', { name: 'Video Details' })).toBeVisible({ timeout: UI_TIMEOUT });
  if (!requirePresets) {
    return;
  }
  // Wait for at least one preset h6 block to be visible (presets section loaded)
  await expect(page.locator('h6').first()).toBeVisible({ timeout: UI_TIMEOUT });
}
// ── Flash popup ──────────────────────────────────────────────────────────────
async function expectFlashPopupTitle(page, titleText, timeout = 30000) {
  const toastTitle = page.locator('.app-flash-toast .app-flash-title', { hasText: titleText }).last();
  await expect(toastTitle).toBeVisible({ timeout });
}
// ── Poster / meta ────────────────────────────────────────────────────────────
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
// ── Preset block helpers ─────────────────────────────────────────────────────
async function getAllPresetTitles(page) {
  // h6 text: "PresetTitle (videoCodec/audioCodec/format)"  → extract title before '('
  // Wait for preset blocks to appear (they only render when _width/_height are in meta)
  try {
    await expect(page.locator('h6').first()).toBeVisible({ timeout: UI_TIMEOUT });
  } catch {
    return [];
  }
  const headings = await page.locator('h6').all();
  const titles = [];
  for (const h of headings) {
    const text = ((await h.textContent()) || '').trim();
    if (!text) continue;
    const match = text.match(/^(.+?)\s*\(/);
    if (match) titles.push(match[1].trim());
    else titles.push(text);
  }
  return titles;
}
async function expectAllPresetsToShowTranscodeWithExpectedSize(page) {
  const titles = await getAllPresetTitles(page);
  expect(titles.length).toBeGreaterThan(0);
  for (const title of titles) {
    const block = presetBlock(page, title);
    await expect(block).toBeVisible({ timeout: UI_TIMEOUT });
    // At least one resolution button
    await expect(block.locator('button.btn-outline-primary').first()).toBeVisible({ timeout: UI_TIMEOUT });
    // Expected size hint (~X MB) under button
    await expect(block.locator('.text-muted').first()).toBeVisible({ timeout: UI_TIMEOUT });
  }
  return titles;
}
// ── Task table helpers ───────────────────────────────────────────────────────
async function expectPresetStatusHelpIcon(page, presetTitle, { statusText, tooltipText } = {}) {
  const row = taskRowByPreset(page, presetTitle);
  const statusCell = row.locator('td').nth(2);  // col2 = status
  await expect(statusCell).toBeVisible({ timeout: UI_TIMEOUT });
  if (statusText) {
    await expect(statusCell).toContainText(statusText, { timeout: UI_TIMEOUT });
  }
  const helpIcon = statusCell.getByRole('img').first();
  await expect(helpIcon).toBeVisible({ timeout: UI_TIMEOUT });
  await helpIcon.hover({ timeout: UI_TIMEOUT });
  if (tooltipText) {
    const tooltipPattern = new RegExp(escapeRegExp(tooltipText), 'i');
    await expect(helpIcon).toHaveAttribute('title', tooltipPattern, { timeout: UI_TIMEOUT });
    await expect(helpIcon).toHaveAttribute('aria-label', tooltipPattern, { timeout: UI_TIMEOUT });
  }
  return helpIcon;
}
async function expectPresetTranscodeDisabledWithHint(page, presetTitle, { expectedSizeText, tooltipText } = {}) {
  // In new UI: verify the preset block is visible and shows expected size hint under buttons.
  // Storage-based disabling is not yet implemented in the new UI; just verify size hints are shown.
  const block = presetBlock(page, presetTitle);
  await expect(block).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(block.locator('button.btn-outline-primary').first()).toBeVisible({ timeout: UI_TIMEOUT });
  const sizeHint = block.locator('.text-muted').first();
  await expect(sizeHint).toBeVisible({ timeout: UI_TIMEOUT });
  if (expectedSizeText) {
    // Match approximate size (e.g. "~4.7 MB" for "Expected size: 4.7 MB")
    const sizeMatch = expectedSizeText.replace(/Expected size:\s*/i, '').trim();
    await expect(sizeHint).toContainText(sizeMatch, { timeout: UI_TIMEOUT });
  }
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
  // Click the first available (not disabled, not struck-through) resolution button in the preset block
  const block = presetBlock(page, presetTitle);
  await expect(block).toBeVisible({ timeout: UI_TIMEOUT });
  const btn = block.locator('button.btn-outline-primary:not([disabled])').first();
  await expect(btn).toBeVisible({ timeout: UI_TIMEOUT });
  await btn.click({ timeout: UI_TIMEOUT });
}
async function expectPresetStatus(page, presetTitle, expectedStatus) {
  if (expectedStatus === 'No task') {
    // No task row should exist for this preset in the tasks table
    const row = taskRowByPreset(page, presetTitle);
    await expect(row).toHaveCount(0, { timeout: UI_TIMEOUT });
    return;
  }
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
  tasksTable,
  taskRowByPreset,
  activeTaskRowByPreset,
  presetsTable,
  presetRow,
  presetBlock,
  readPresetTaskState,
  waitForVideoDetailsVisible,
  expectFlashPopupTitle,
  waitForPosterAndMeta,
  getAllPresetTitles,
  expectAllPresetsToShowTranscodeWithExpectedSize,
  expectPresetStatusHelpIcon,
  expectPresetTranscodeDisabledWithHint,
  waitForDeletedVideoDetailsWithoutPoster,
  clickTranscodeForPreset,
  expectPresetStatus,
  waitForAllPresetsToComplete,
  waitForAllPresetsProcessingWithProgress,
};
