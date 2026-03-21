const { test, expect } = require('@playwright/test');

const UI_TIMEOUT = 8000;
const NAV_TIMEOUT = 15000;

async function shot(page, testInfo, name) {
  await page.screenshot({ path: testInfo.outputPath(name), fullPage: true });
}

test('admin login and empty tabs smoke test', async ({ page }, testInfo) => {
  const adminEmail = process.env.ADMIN_EMAIL || 'oleg@milantiev.com';
  const adminPassword = process.env.ADMIN_PASSWORD || 'admin';

  await page.goto('/', { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
  await expect(page.getByRole('link', { name: 'Sign in' }).last()).toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, '01-home-sign-in.png');

  await page.getByRole('link', { name: 'Sign in' }).last().click({ timeout: UI_TIMEOUT });
  await expect(page.locator('#inputEmail')).toBeVisible({ timeout: UI_TIMEOUT });
  await page.locator('#inputEmail').fill(adminEmail);
  await page.locator('#inputPassword').fill(adminPassword);
  await shot(page, testInfo, '02-login-form-filled.png');

  await page.getByRole('button', { name: 'Sign in' }).click({ timeout: UI_TIMEOUT });
  await expect(page.getByRole('button', { name: 'Upload' })).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(page.getByRole('button', { name: 'Videos' })).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(page.getByRole('button', { name: 'Tasks' })).toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, '03-tabs-visible.png');

  await expect(page.locator('#drag-drop-area .uppy-Dashboard')).toBeVisible({ timeout: 30000 });
  await shot(page, testInfo, '04-upload-tab-uppy.png');

  await page.getByRole('button', { name: 'Videos' }).click({ timeout: UI_TIMEOUT });
  await expect(page.locator('#videosTable')).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(page.locator('#videosTable')).toContainText('No videos', { timeout: UI_TIMEOUT });
  await shot(page, testInfo, '05-videos-empty.png');

  await page.getByRole('button', { name: 'Tasks' }).click({ timeout: UI_TIMEOUT });
  await expect(page.locator('#tasksTable')).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(page.locator('#tasksTable')).toContainText('No tasks', { timeout: UI_TIMEOUT });
  await shot(page, testInfo, '06-tasks-empty.png');

  await expect(page.getByRole('link', { name: 'Sign out' })).toBeVisible({ timeout: UI_TIMEOUT });
  await page.getByRole('link', { name: 'Sign out' }).click({ timeout: UI_TIMEOUT });
  await expect(page.getByRole('link', { name: 'Sign in' })).toHaveCount(2, { timeout: UI_TIMEOUT });
  await shot(page, testInfo, '07-sign-out-and-sign-in-links.png');
});

