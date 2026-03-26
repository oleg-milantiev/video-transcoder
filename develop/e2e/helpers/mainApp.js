const { expect } = require('@playwright/test');
const { UI_TIMEOUT } = require('./constants');

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
};


