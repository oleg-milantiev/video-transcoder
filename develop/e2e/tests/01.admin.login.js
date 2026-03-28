const { test, expect } = require('@playwright/test');
const {
  UI_TIMEOUT,
  openHome,
  openSignIn,
  getAdminCredentials,
  fillSignInCredentials,
  submitSignIn,
  expectTabsVisible,
  expectUploadDashboardVisible,
  openVideosTab,
  openTasksTab,
  expectEmptyVideos,
  expectEmptyTasks,
  logoutToPublic,
  shot,
} = require('../helpers');

test('admin login and empty tabs smoke test', async ({ page }, testInfo) => {
  const { email, password } = getAdminCredentials();

  await openHome(page);
  await expect(page.getByRole('link', { name: 'Sign in' }).last()).toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, '01-home-sign-in.png');

  await openSignIn(page);
  await expect(page.locator('#inputEmail')).toBeVisible({ timeout: UI_TIMEOUT });
  await fillSignInCredentials(page, email, password);
  await shot(page, testInfo, '02-login-form-filled.png');

  await submitSignIn(page);
  await expectTabsVisible(page);
  await shot(page, testInfo, '03-tabs-visible.png');

  await expectUploadDashboardVisible(page);
  await shot(page, testInfo, '04-upload-tab-uppy.png');

  await openVideosTab(page);
  await expectEmptyVideos(page);
  await shot(page, testInfo, '05-videos-empty.png');

  await openTasksTab(page);
  await expectEmptyTasks(page);
  await shot(page, testInfo, '06-tasks-empty.png');

  await logoutToPublic(page);
  await shot(page, testInfo, '07-sign-out-and-sign-in-links.png');
});

