const path = require('path');
const { test, expect } = require('@playwright/test');

async function shot(page, testInfo, name) {
  await page.screenshot({ path: testInfo.outputPath(name), fullPage: true });
}

async function expectDetailsValue(page, label) {
  const dt = page.locator('dt', { hasText: label }).first();
  await expect(dt).toBeVisible();

  const dd = dt.locator('xpath=following-sibling::dd[1]');
  await expect(dd).toBeVisible();
  await expect(dd).not.toHaveText('-');
  await expect(dd).toHaveText(/\S+/);
}

async function waitForPosterAndMeta(page, testInfo) {
  const maxAttempts = 5;

  for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
    const hasPoster = (await page.locator('.card-body img.img-fluid').count()) > 0;
    const noMetaVisible = await page.getByText('No meta data').isVisible().catch(() => false);

    if (hasPoster && !noMetaVisible) {
      await shot(page, testInfo, `07-details-poster-meta-ready-attempt-${attempt}.png`);
      return;
    }

    if (attempt < maxAttempts) {
      await page.waitForTimeout(5000);
      await page.reload();
      await expect(page.getByRole('heading', { name: 'Video Details' })).toBeVisible();
    }
  }

  throw new Error('Poster or meta data is still missing after 3 checks with 5-second delays');
}

test('upload video and verify details flow', async ({ page }, testInfo) => {
  const adminEmail = process.env.ADMIN_EMAIL || 'oleg@milantiev.com';
  const adminPassword = process.env.ADMIN_PASSWORD || 'admin';
  const fileName = '2022_10_04_Two_Maxes.mp4';
  const uploadFilePath = path.join('/work/e2e', fileName);

  await page.goto('/');
  await page.getByRole('link', { name: 'Sign in' }).last().click();
  await page.locator('#inputEmail').fill(adminEmail);
  await page.locator('#inputPassword').fill(adminPassword);
  await page.getByRole('button', { name: 'Sign in' }).click();

  await expect(page.getByRole('button', { name: 'Upload' })).toBeVisible();
  await expect(page.locator('#drag-drop-area .uppy-Dashboard')).toBeVisible({ timeout: 30000 });
  await shot(page, testInfo, '01-upload-tab-ready.png');

  const fileChooserPromise = page.waitForEvent('filechooser');
  await page.locator('#drag-drop-area .uppy-Dashboard-browse').click();
  const fileChooser = await fileChooserPromise;
  await fileChooser.setFiles(uploadFilePath);
  await expect(page.locator('#drag-drop-area .uppy-StatusBar-content[role="status"][title="Complete"]')).toBeVisible({ timeout: 30000 });
  await shot(page, testInfo, '02-uppy-upload-complete.png');

  await page.getByRole('button', { name: 'Videos' }).click();
  await expect(page.locator('#videosTable')).toBeVisible();

  const videoRow = page.locator('#videosTable tbody tr', {
    has: page.locator('td', { hasText: fileName }),
  });

  await expect(videoRow).toBeVisible({ timeout: 15000 });
  await expect(videoRow.locator('td').nth(1)).toContainText(fileName);
  await expect(videoRow.locator('td').nth(2)).not.toHaveText('-');
  await expect(videoRow.locator('td').nth(3)).not.toHaveText('-');
  await shot(page, testInfo, '03-video-row-in-table.png');

  await videoRow.click();
  await expect(page.getByRole('heading', { name: 'Video Details' })).toBeVisible();

  await expectDetailsValue(page, 'Title');
  await expectDetailsValue(page, 'Extension');
  await expectDetailsValue(page, 'Status');
  await expectDetailsValue(page, 'Created At');
  await expectDetailsValue(page, 'User ID');
  await shot(page, testInfo, '04-video-details-filled.png');

  await waitForPosterAndMeta(page, testInfo);

  // Sign out at the end of the flow (same as in 01.admin.login.js)
  await expect(page.getByRole('link', { name: 'Sign out' })).toBeVisible();
  await page.getByRole('link', { name: 'Sign out' }).click();
  await expect(page.getByRole('link', { name: 'Sign in' })).toHaveCount(2);
  await shot(page, testInfo, '08-sign-out-and-sign-in-links.png');
});

