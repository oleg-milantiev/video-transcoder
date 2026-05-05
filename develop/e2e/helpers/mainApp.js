const { expect } = require('@playwright/test');
const { UI_TIMEOUT } = require('./constants');
const { clickAndAcceptConfirm } = require('./dialogs');

async function expectTabsVisible(page) {
  await expect(page.getByRole('button', { name: 'Upload' })).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(page.getByRole('button', { name: 'Videos' })).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(page.getByRole('button', { name: 'Tasks' })).toBeVisible({ timeout: UI_TIMEOUT });
}

async function openUploadTab(page) {
  await page.getByRole('button', { name: 'Upload' }).click({ timeout: UI_TIMEOUT });
}

async function openVideosTab(page) {
  await page.getByRole('button', { name: 'Videos' }).click({ timeout: UI_TIMEOUT });
}

async function openTasksTab(page) {
  await page.getByRole('button', { name: 'Tasks' }).click({ timeout: UI_TIMEOUT });
}

async function expectUploadDashboardVisible(page, timeout = 30000) {
  await expect(page.locator('#drag-drop-area .uppy-Dashboard')).toBeVisible({ timeout });
}

async function expectVideosTableVisible(page) {
  await expect(page.locator('#videosTable')).toBeVisible({ timeout: UI_TIMEOUT });
}

async function expectTasksTableVisible(page) {
  await expect(page.locator('#tasksTable')).toBeVisible({ timeout: UI_TIMEOUT });
}

async function expectEmptyVideos(page) {
  await expectVideosTableVisible(page);
  await expect(page.locator('#videosTable')).toContainText('No videos', { timeout: UI_TIMEOUT });
}

async function expectEmptyTasks(page) {
  await expectTasksTableVisible(page);
  await expect(page.locator('#tasksTable')).toContainText('No tasks', { timeout: UI_TIMEOUT });
}

async function expectVideoRowHasCoreValues(videoRow, fileName) {
  await expect(videoRow).toBeVisible({ timeout: 15000 });
  await expect(videoRow.locator('td').nth(1)).toContainText(fileName, { timeout: UI_TIMEOUT });
  await expect(videoRow.locator('td').nth(2)).not.toHaveText('-', { timeout: UI_TIMEOUT });
}

function videoRowByTitle(page, fileName) {
  return page.locator('#videosTable tbody tr', { hasText: fileName }).first();
}

function activeVideoRowByTitle(page, fileName) {
  return page
    .locator('#videosTable tbody tr', { hasText: fileName })
    .filter({ hasNot: page.locator('td.video-title-deleted') })
    .first();
}

// ── Pagination ────────────────────────────────────────────────────────────────

/** Click the "Next" pagination button in the Videos tab. */
async function clickVideosNextPage(page) {
  const nextBtn = page.getByRole('button', { name: 'Next', exact: true });
  await expect(nextBtn).not.toBeDisabled({ timeout: UI_TIMEOUT });
  await nextBtn.click({ timeout: UI_TIMEOUT });
  // Brief settle while the new page loads from the API
  await page.waitForTimeout(800);
}

/** Assert the pagination info span shows "total {expectedCount}". */
async function expectVideosTotalCount(page, expectedCount) {
  await expect(
    page.locator('span.text-muted.small').filter({ hasText: `total ${expectedCount}` })
  ).toBeVisible({ timeout: UI_TIMEOUT });
}

// ── Bulk delete ───────────────────────────────────────────────────────────────

/**
 * Delete every non-deleted (Delete-button-visible) video row on the current
 * Videos list page, one by one, confirming each dialog.
 * Waits for each row to transition to `td.video-title-deleted` before continuing.
 */
async function deleteAllVideosOnPage(page) {
  for (;;) {
    const rows = page
      .locator('#videosTable tbody tr')
      .filter({ has: page.getByRole('button', { name: 'Delete' }) });
    if (await rows.count() === 0) break;
    const row = rows.first();
    await clickAndAcceptConfirm(
      page,
      row.getByRole('button', { name: 'Delete' }),
      'Delete this video?'
    );
    await expect(row.locator('td.video-title-deleted')).toBeVisible({ timeout: 15000 });
  }
}

/**
 * Assert that no active (deletable) video rows remain on the current page —
 * i.e., every visible row has already been soft-deleted.
 */
async function expectAllVideosOnPageAreDeleted(page) {
  await expect(
    page
      .locator('#videosTable tbody tr')
      .filter({ has: page.getByRole('button', { name: 'Delete' }) })
  ).toHaveCount(0, { timeout: UI_TIMEOUT });
}

module.exports = {
  expectTabsVisible,
  openUploadTab,
  openVideosTab,
  openTasksTab,
  expectUploadDashboardVisible,
  expectVideosTableVisible,
  expectTasksTableVisible,
  expectEmptyVideos,
  expectEmptyTasks,
  expectVideoRowHasCoreValues,
  videoRowByTitle,
  activeVideoRowByTitle,
  clickVideosNextPage,
  expectVideosTotalCount,
  deleteAllVideosOnPage,
  expectAllVideosOnPageAreDeleted,
};


