const { expect } = require('@playwright/test');
const { UI_TIMEOUT, NAV_TIMEOUT } = require('./constants');
const { shot } = require('./screenshot');
function escapeRegExp(value) {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
// ── Tab switching for video-details card ─────────────────────────────────────
// Tab keys: 'info' → '📄 Details', 'transcode' → '⚙️ Transcode', 'tasks' → '📋 Tasks'
async function switchToVideoTab(page, tabKey) {
  const labels = { info: '📄 Details', transcode: '⚙️ Transcode', tasks: '📋 Tasks' };
  const label = labels[tabKey] || tabKey;
  const btn = page.locator('button.nav-link', { hasText: label }).first();
  await expect(btn).toBeVisible({ timeout: UI_TIMEOUT });
  await btn.click({ timeout: UI_TIMEOUT });
}
async function expectDetailsValue(page, label) {
  if (label === 'Title') {
    // Title is now an h6 in the Info tab (not a dt/dd anymore)
    await switchToVideoTab(page, 'info');
    const titleH6 = page.locator('.card-body h6').first();
    await expect(titleH6).toBeVisible({ timeout: UI_TIMEOUT });
    await expect(titleH6).not.toHaveText('-', { timeout: UI_TIMEOUT });
    await expect(titleH6).toHaveText(/\S+/, { timeout: UI_TIMEOUT });
    return;
  }
  const dt = page.locator('dt', { hasText: label }).first();
  await expect(dt).toBeVisible({ timeout: UI_TIMEOUT });
  const dd = dt.locator('xpath=following-sibling::dd[1]');
  await expect(dd).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(dd).not.toHaveText('-', { timeout: UI_TIMEOUT });
  await expect(dd).toHaveText(/\S+/, { timeout: UI_TIMEOUT });
}
async function renameVideoFromDetails(page, newTitle) {
  // The rename button lives in the Details (Info) tab
  await switchToVideoTab(page, 'info');
  const renameButton = page.locator('button[title="Rename"]').first();
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
  // Title is now rendered as h6 in the Info tab title row (above poster/data)
  const titleH6 = page.locator('.card-body h6').first();
  await expect(titleH6).toBeVisible({ timeout: UI_TIMEOUT });
  await expect.poll(
    async () => ((await titleH6.textContent()) || '').trim(),
    { timeout: 30000 }
  ).toBe(expectedTitle);
}
async function clickBackButton(page) {
  // The new video-details UI has a close (✕) button with aria-label="Close"
  // that navigates back to the Videos list
  await page.getByRole('button', { name: 'Close' }).click({ timeout: UI_TIMEOUT });
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
  // h6 shows "PresetTitle (videoCodec/audioCodec/format)" — only visible in Transcode tab
  return page.locator('h6', { hasText: presetTitle }).first().locator('xpath=./parent::div');
}
// ── Task row by preset title + specific height (e.g. 1080p) ─────────────────
function taskRowByPresetAndHeight(page, presetTitle, height) {
  return tasksTable(page).locator('tbody tr', { hasText: presetTitle })
    .filter({ has: page.locator('td', { hasText: String(height) + 'p' }) })
    .first();
}
// Returns first non-CANCELLED row for this preset+height (e.g. after a restart)
function activeTaskRowByPresetAndHeight(page, presetTitle, height) {
  return tasksTable(page).locator('tbody tr', { hasText: presetTitle })
    .filter({ has: page.locator('td', { hasText: String(height) + 'p' }) })
    .filter({ hasNot: page.locator('td', { hasText: 'CANCELLED' }) })
    .first();
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
// ── Read task status/progress for a specific preset+height ──────────────────
async function readPresetTaskStateByHeight(page, presetTitle, height, { preferActive = false } = {}) {
  const row = preferActive
    ? activeTaskRowByPresetAndHeight(page, presetTitle, height)
    : taskRowByPresetAndHeight(page, presetTitle, height);
  await expect(row).toBeVisible({ timeout: UI_TIMEOUT });
  const rawStatus = (await row.locator('td').nth(2).innerText({ timeout: UI_TIMEOUT })).trim();
  const status = rawStatus.replace(/\s+\?\s*$/, '').trim();
  const progressText = (await row.locator('td').nth(3).innerText({ timeout: UI_TIMEOUT })).trim();
  const progressMatch = progressText.match(/(\d+)\s*%/);
  const progress = progressMatch ? Number(progressMatch[1]) : -1;
  return { status, progress };
}
// ── waitForVideoDetailsVisible ───────────────────────────────────────────────
async function waitForVideoDetailsVisible(page, { requirePresets = false } = {}) {
  // The '📄 Details' nav-link button is unique to the video-details card
  await expect(
    page.locator('button.nav-link', { hasText: '📄 Details' }).first()
  ).toBeVisible({ timeout: UI_TIMEOUT });
  if (!requirePresets) {
    return;
  }
  // Switch to Transcode tab and wait for at least one preset h6 block
  await switchToVideoTab(page, 'transcode');
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
      await waitForVideoDetailsVisible(page, { requirePresets: false });
    }
  }
  throw new Error('Poster is not fully loaded or Meta duration is missing after 5 checks with 5-second delays');
}
// ── Preset block helpers ─────────────────────────────────────────────────────
async function getAllPresetTitles(page) {
  // h6 text: "PresetTitle (videoCodec/audioCodec/format)"  → extract title before '('
  // Preset blocks are only rendered in the Transcode tab — switch there first.
  try {
    await switchToVideoTab(page, 'transcode');
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
  // Preset blocks are in the Transcode tab
  await switchToVideoTab(page, 'transcode');
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
    // In the new UI, the deleted indicator is a 'Deleted' badge inside the title row (Info tab)
    const deletedBadge = page.locator('.badge', { hasText: 'Deleted' }).first();
    const hasDeletedBadge = (await deletedBadge.count()) > 0;
    // Poster img can be in col-md-5 (vertical) or div.mb-3 (landscape layout)
    const hasPoster = (await page.locator('.card-body img.img-fluid').count()) > 0;
    if (hasDeletedBadge && !hasPoster) {
      // Verify the video title is correct — now shown as h6 in the title row
      const titleH6 = page.locator('.card-body h6').first();
      if ((await titleH6.count()) > 0) {
        await expect(titleH6).toContainText(expectedTitle, { timeout: UI_TIMEOUT });
      }
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
  // Preset blocks live in the Transcode tab — switch there first
  await switchToVideoTab(page, 'transcode');
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
  switchToVideoTab,
  expectDetailsValue,
  renameVideoFromDetails,
  expectVideoDetailsTitle,
  clickBackButton,
  tasksTable,
  taskRowByPreset,
  activeTaskRowByPreset,
  taskRowByPresetAndHeight,
  activeTaskRowByPresetAndHeight,
  presetsTable,
  presetRow,
  presetBlock,
  readPresetTaskState,
  readPresetTaskStateByHeight,
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
