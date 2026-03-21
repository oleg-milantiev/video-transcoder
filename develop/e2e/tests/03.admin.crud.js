const { test, expect } = require('@playwright/test');

async function shot(page, testInfo, name) {
  await page.screenshot({ path: testInfo.outputPath(name), fullPage: true });
}

function adminMenuLink(page, pathSuffix) {
  return page.locator(`a[href$="${pathSuffix}"]`).first();
}

async function openAdminSection(page, sectionName, pathSuffix) {
  await adminMenuLink(page, pathSuffix).click();
  await expect(page).toHaveURL(/\/admin/);
  await expect(page.getByRole('heading', { name: sectionName }).first()).toBeVisible();
  await expect(mainTableBodyForHeading(page, sectionName)).toBeVisible();
}

// Return tbody of the main table that is associated with a page heading (e.g. 'Users').
// This avoids matching unrelated <tbody> elements (Symfony toolbar, etc.).
function mainTableBodyForHeading(page, heading) {
  return page.locator('article', { has: page.getByRole('heading', { name: heading }) }).locator('table tbody').first();
}

async function submitCrudForm(page) {
  const createButton = page.getByRole('button', { name: 'Create', exact: true });
  if ((await createButton.count()) > 0) {
    await createButton.click();
    return;
  }

  const saveChangesButton = page.getByRole('button', { name: 'Save changes', exact: true });
  if ((await saveChangesButton.count()) > 0) {
    await saveChangesButton.click();
    return;
  }

  await page.getByRole('button', { name: 'Update', exact: true }).click();
}

async function createOrUpdateTariff(page, testInfo) {
  await openAdminSection(page, 'Tariffs', '/admin/tariff');

  const tariffsTbody = mainTableBodyForHeading(page, 'Tariffs');
  const tariffRows = tariffsTbody.locator('tr', { hasText: 'Free' });

  if ((await tariffRows.count()) === 0) {
    await expect(page.locator('a.action-new')).toBeVisible();
    await page.locator('a.action-new').click();

    await page.getByLabel('Title').fill('Free');
    await page.getByLabel('Delay').fill('60');
    await page.getByLabel('Instance').fill('1');
    await submitCrudForm(page);
  }

  await expect(tariffsTbody.locator('tr', { hasText: 'Free' }).first()).toBeVisible();
  await shot(page, testInfo, '07-tariff-free-present.png');

  const freeRow = tariffsTbody.locator('tr', { hasText: 'Free' }).first();
  await freeRow.locator('a.action-edit').click();
  await page.getByLabel('Delay').fill('60');
  await page.getByLabel('Instance').fill('1');
  await submitCrudForm(page);
  await expect(tariffsTbody.locator('tr', { hasText: 'Free' }).first()).toContainText('60');
  await expect(tariffsTbody.locator('tr', { hasText: 'Free' }).first()).toContainText('1');
}

async function createOrUpdatePreset(page, testInfo) {
  await openAdminSection(page, 'Presets', '/admin/preset');
  const presetsTbody = mainTableBodyForHeading(page, 'Presets');
  const presetRows = presetsTbody.locator('tr', { hasText: '180p' });

  if ((await presetRows.count()) === 0) {
    await expect(page.locator('a.action-new')).toBeVisible();
    await page.locator('a.action-new').click();

    await page.getByLabel('Title').fill('180p');
    await page.getByLabel('Width').fill('320');
    await page.getByLabel('Height').fill('180');
    await page.getByLabel('Codec').fill('h264');
    await page.getByLabel('Bitrate (Mbps)').fill('1.1');
    await submitCrudForm(page);
  }

  const presetRow = presetsTbody.locator('tr', { hasText: '180p' }).first();
  await expect(presetRow).toBeVisible();
  await shot(page, testInfo, '06-preset-180p-present.png');

  await presetRow.locator('a.action-edit').click();
  await page.getByLabel('Width').fill('320');
  await page.getByLabel('Height').fill('180');
  await page.getByLabel('Codec').fill('h264');
  await page.getByLabel('Bitrate (Mbps)').fill('1.1');
  await submitCrudForm(page);
  await expect(presetsTbody.locator('tr', { hasText: '180p' }).first()).toContainText('320');
  await expect(presetsTbody.locator('tr', { hasText: '180p' }).first()).toContainText('180');
}

test('admin area full smoke with CRUD checks', async ({ page }, testInfo) => {
  const adminEmail = process.env.ADMIN_EMAIL || 'oleg@milantiev.com';
  const adminPassword = process.env.ADMIN_PASSWORD || 'admin';
  const uploadedVideoName = '2022_10_04_Two_Maxes.mp4';

  await page.goto('/');
  await page.getByRole('link', { name: 'Sign in' }).last().click();
  await page.locator('#inputEmail').fill(adminEmail);
  await page.locator('#inputPassword').fill(adminPassword);
  await page.getByRole('button', { name: 'Sign in' }).click();

  await expect(page.getByRole('link', { name: 'Admin', exact: true })).toBeVisible();
  await shot(page, testInfo, '01-home-admin-link.png');

  await page.getByRole('link', { name: 'Admin', exact: true }).click();
  await expect(page).toHaveURL(/\/admin/);
  await expect(page.getByRole('heading', { name: 'Users' }).first()).toBeVisible();

  // Users: check sections and user, verify CRUD controls are visible.
  await expect(adminMenuLink(page, '/admin/user')).toBeVisible();
  await expect(adminMenuLink(page, '/admin/tariff')).toBeVisible();
  await expect(adminMenuLink(page, '/admin/video')).toBeVisible();
  await expect(adminMenuLink(page, '/admin/preset')).toBeVisible();
  await expect(adminMenuLink(page, '/admin/task')).toBeVisible();

  await expect(page.locator('a.action-new')).toBeVisible();
  await expect(page.locator('a.action-edit').first()).toBeVisible();
  await expect(page.locator('a.action-detail').first()).toBeVisible();
  const usersTbody = mainTableBodyForHeading(page, 'Users');
  await expect(usersTbody).toContainText('oleg@milantiev.com');
  await shot(page, testInfo, '02-admin-users-and-menu.png');

  await createOrUpdatePreset(page, testInfo);
  await createOrUpdateTariff(page, testInfo);

  await openAdminSection(page, 'Videos', '/admin/video');
  await expect(page.locator('a.action-new')).toHaveCount(0);
  const videosTbody = mainTableBodyForHeading(page, 'Videos');
  await expect(videosTbody).toContainText(uploadedVideoName);
  await expect(videosTbody.locator('a.action-detail').first()).toBeVisible();
  await shot(page, testInfo, '03-admin-videos-uploaded-file.png');

  await openAdminSection(page, 'Tasks', '/admin/task');
  await expect(page.locator('a.action-new')).toHaveCount(0);
  await expect(page.locator('a.action-edit')).toHaveCount(0);
  await expect(page.locator('a.action-delete')).toHaveCount(0);
  await shot(page, testInfo, '04-admin-tasks-crud-constraints.png');

  await page.goto('/');
  await expect(page.getByRole('button', { name: 'Upload' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Sign out' })).toBeVisible();
  await page.getByRole('link', { name: 'Sign out' }).click();
  await expect(page.getByRole('link', { name: 'Sign in' })).toHaveCount(2);
  await shot(page, testInfo, '08-sign-out-after-admin-flow.png');
});

