/**
 * 11 · rename.download
 *
 * TBD: скачивание, переименование, скачивание с новым именем
 */

const { test, expect } = require('@playwright/test');
const {
  loginAs,
  logoutToPublic,
  shot,
} = require('../helpers');

const EMAIL    = 'test-11@test.com';
const PASSWORD = 'test-11';

test('11 · rename.download: TBD', async ({ page }, testInfo) => {
  await loginAs(page, EMAIL, PASSWORD);
  await shot(page, testInfo, '11-01-login.png');

  // TODO: upload video, download, rename, download with new name

  await logoutToPublic(page);
  await shot(page, testInfo, '11-99-logout.png');
});

