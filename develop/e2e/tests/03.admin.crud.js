const { test, expect } = require('@playwright/test');

const UI_TIMEOUT = 8000;
const NAV_TIMEOUT = 15000;

async function shot(page, testInfo, name) {
  await page.screenshot({ path: testInfo.outputPath(name), fullPage: true });
}

function adminMenuLink(page, pathSuffix) {
  return page.locator(`a[href$="${pathSuffix}"]`).first();
}

async function openAdminSection(page, sectionName, pathSuffix) {
  await adminMenuLink(page, pathSuffix).click({ timeout: UI_TIMEOUT });
  await expect(page).toHaveURL(/\/admin/, { timeout: NAV_TIMEOUT });
  await expect(page.getByRole('heading', { name: sectionName }).first()).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(mainTableBodyForHeading(page, sectionName)).toBeVisible({ timeout: UI_TIMEOUT });
}

// Return tbody of the main table that is associated with a page heading (e.g. 'Users').
// This avoids matching unrelated <tbody> elements (Symfony toolbar, etc.).
function mainTableBodyForHeading(page, heading) {
  return page.locator('article', { has: page.getByRole('heading', { name: heading }) }).locator('table tbody').first();
}

async function submitCrudForm(page) {
  const createButton = page.getByRole('button', { name: 'Create', exact: true });
  if ((await createButton.count()) > 0) {
    await createButton.click({ timeout: UI_TIMEOUT });
    return;
  }

  const saveChangesButton = page.getByRole('button', { name: 'Save changes', exact: true });
  if ((await saveChangesButton.count()) > 0) {
    await saveChangesButton.click({ timeout: UI_TIMEOUT });
    return;
  }

  await page.getByRole('button', { name: 'Update', exact: true }).click({ timeout: UI_TIMEOUT });
}

async function createOrUpdateTariffByTitle(page, title, delay, instance, testInfo, screenshotName) {
  await openAdminSection(page, 'Tariffs', '/admin/tariff');

  const tariffsTbody = mainTableBodyForHeading(page, 'Tariffs');
  const tariffRows = tariffsTbody.locator('tr', { hasText: title });

  if ((await tariffRows.count()) === 0) {
    await expect(page.locator('a.action-new')).toBeVisible({ timeout: UI_TIMEOUT });
    await page.locator('a.action-new').click({ timeout: UI_TIMEOUT });

    await page.getByLabel('Title').fill(title);
    await page.getByLabel('Delay').fill(String(delay));
    await page.getByLabel('Instance').fill(String(instance));
    await submitCrudForm(page);
  }

  const tariffRow = tariffsTbody.locator('tr', { hasText: title }).first();
  await expect(tariffRow).toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, screenshotName);

  await tariffRow.locator('a.action-edit').click({ timeout: UI_TIMEOUT });
  await page.getByLabel('Delay').fill(String(delay));
  await page.getByLabel('Instance').fill(String(instance));
  await submitCrudForm(page);
  await expect(tariffsTbody.locator('tr', { hasText: title }).first()).toContainText(String(delay), { timeout: UI_TIMEOUT });
  await expect(tariffsTbody.locator('tr', { hasText: title }).first()).toContainText(String(instance), { timeout: UI_TIMEOUT });
}

async function assignTariffToUser(page, userEmail, tariffTitle, testInfo) {
  await openAdminSection(page, 'Users', '/admin/user');

  const usersTbody = mainTableBodyForHeading(page, 'Users');
  const userRow = usersTbody.locator('tr', { hasText: userEmail }).first();
  await expect(userRow).toBeVisible({ timeout: UI_TIMEOUT });

  await userRow.locator('a.action-edit').click({ timeout: UI_TIMEOUT });

  const tariffSelect = page.locator('select[name$="[tariff]"]').first();
  if ((await tariffSelect.count()) > 0) {
    await tariffSelect.selectOption({ label: tariffTitle });
  } else {
    const tariffInput = page.getByLabel('Tariff').first();
    await tariffInput.click();
    await tariffInput.fill(tariffTitle);
    await page.keyboard.press('Enter');
  }

  await submitCrudForm(page);
  await expect(mainTableBodyForHeading(page, 'Users').locator('tr', { hasText: userEmail }).first()).toContainText(tariffTitle, { timeout: UI_TIMEOUT });
  await shot(page, testInfo, '08-user-free-tariff-assigned.png');
}

async function createOrUpdatePreset(page, preset, testInfo) {
  await openAdminSection(page, 'Presets', '/admin/preset');
  const presetsTbody = mainTableBodyForHeading(page, 'Presets');
  const presetRows = presetsTbody.locator('tr', { hasText: preset.title });

  if ((await presetRows.count()) === 0) {
    await expect(page.locator('a.action-new')).toBeVisible({ timeout: UI_TIMEOUT });
    await page.locator('a.action-new').click({ timeout: UI_TIMEOUT });

    await page.getByLabel('Title').fill(preset.title);
    await page.getByLabel('Width').fill(String(preset.width));
    await page.getByLabel('Height').fill(String(preset.height));
    await page.getByLabel('Codec').fill(preset.codec);
    await page.getByLabel('Bitrate (Mbps)').fill(String(preset.bitrate));
    await submitCrudForm(page);
  }

  const presetRow = presetsTbody.locator('tr', { hasText: preset.title }).first();
  await expect(presetRow).toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, `06-preset-${preset.title}-present.png`);

  await presetRow.locator('a.action-edit').click({ timeout: UI_TIMEOUT });
  await page.getByLabel('Width').fill(String(preset.width));
  await page.getByLabel('Height').fill(String(preset.height));
  await page.getByLabel('Codec').fill(preset.codec);
  await page.getByLabel('Bitrate (Mbps)').fill(String(preset.bitrate));
  await submitCrudForm(page);
  await expect(presetsTbody.locator('tr', { hasText: preset.title }).first()).toContainText(String(preset.width), { timeout: UI_TIMEOUT });
  await expect(presetsTbody.locator('tr', { hasText: preset.title }).first()).toContainText(String(preset.height), { timeout: UI_TIMEOUT });
}

test('admin area full smoke with CRUD checks', async ({ page }, testInfo) => {
  const adminEmail = process.env.ADMIN_EMAIL || 'oleg@milantiev.com';
  const adminPassword = process.env.ADMIN_PASSWORD || 'admin';
  const uploadedVideoName = '2022_10_04_Two_Maxes.mp4';

  await page.goto('/', { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
  await page.getByRole('link', { name: 'Sign in' }).last().click({ timeout: UI_TIMEOUT });
  await page.locator('#inputEmail').fill(adminEmail);
  await page.locator('#inputPassword').fill(adminPassword);
  await page.getByRole('button', { name: 'Sign in' }).click({ timeout: UI_TIMEOUT });

  await expect(page.getByRole('link', { name: 'Admin', exact: true })).toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, '01-home-admin-link.png');

  await page.getByRole('link', { name: 'Admin', exact: true }).click({ timeout: UI_TIMEOUT });
  await expect(page).toHaveURL(/\/admin/, { timeout: NAV_TIMEOUT });
  await expect(page.getByRole('heading', { name: 'Users' }).first()).toBeVisible({ timeout: UI_TIMEOUT });

  // Users: check sections and user, verify CRUD controls are visible.
  await expect(adminMenuLink(page, '/admin/user')).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(adminMenuLink(page, '/admin/tariff')).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(adminMenuLink(page, '/admin/video')).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(adminMenuLink(page, '/admin/preset')).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(adminMenuLink(page, '/admin/task')).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(adminMenuLink(page, '/admin/log')).toBeVisible({ timeout: UI_TIMEOUT });

  await expect(page.locator('a.action-new')).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(page.locator('a.action-edit').first()).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(page.locator('a.action-detail').first()).toBeVisible({ timeout: UI_TIMEOUT });
  const usersTbody = mainTableBodyForHeading(page, 'Users');
  await expect(usersTbody).toContainText('oleg@milantiev.com', { timeout: UI_TIMEOUT });
  await shot(page, testInfo, '02-admin-users-and-menu.png');

  await createOrUpdatePreset(page, {
    title: '180p',
    width: 320,
    height: 180,
    codec: 'h264',
    bitrate: 1.1,
  }, testInfo);
  await createOrUpdatePreset(page, {
    title: '4k',
    width: 3840,
    height: 2160,
    codec: 'h264',
    bitrate: 8.0,
  }, testInfo);
  await createOrUpdateTariffByTitle(page, 'Free', 60, 1, testInfo, '07-tariff-free-initial.png');
  await createOrUpdateTariffByTitle(page, 'Free', 3600, 1, testInfo, '07b-tariff-free-updated-to-hour.png');
  await createOrUpdateTariffByTitle(page, 'Premium', 0, 2, testInfo, '07c-tariff-premium-present.png');
  await assignTariffToUser(page, adminEmail, 'Free', testInfo);

  await openAdminSection(page, 'Videos', '/admin/video');
  await expect(page.locator('a.action-new')).toHaveCount(0, { timeout: UI_TIMEOUT });
  const videosTbody = mainTableBodyForHeading(page, 'Videos');
  await expect(videosTbody).toContainText(uploadedVideoName, { timeout: UI_TIMEOUT });
  await expect(videosTbody.locator('a.action-detail').first()).toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, '03-admin-videos-uploaded-file.png');

  await openAdminSection(page, 'Tasks', '/admin/task');
  await expect(page.locator('a.action-new')).toHaveCount(0, { timeout: UI_TIMEOUT });
  await expect(page.locator('a.action-edit')).toHaveCount(0, { timeout: UI_TIMEOUT });
  await expect(page.locator('a.action-delete')).toHaveCount(0, { timeout: UI_TIMEOUT });
  await shot(page, testInfo, '04-admin-tasks-crud-constraints.png');

  await openAdminSection(page, 'Logs', '/admin/log');
  await expect(page.locator('a.action-new')).toHaveCount(0, { timeout: UI_TIMEOUT });
  await expect(page.locator('a.action-edit')).toHaveCount(0, { timeout: UI_TIMEOUT });
  const filtersAction = page
    .locator('a.action-filters, button.action-filters, a.action-filters-button, button.action-filters-button')
    .or(page.getByRole('button', { name: /filter/i }))
    .or(page.getByRole('link', { name: /filter/i }))
    .first();
  await expect(filtersAction).toBeVisible({ timeout: UI_TIMEOUT });
  const logsTbody = mainTableBodyForHeading(page, 'Logs');
  await expect.poll(async () => logsTbody.locator('tr').count(), { timeout: UI_TIMEOUT }).toBeGreaterThan(0);
  await shot(page, testInfo, '05-admin-logs-readonly-with-filters.png');

  await page.goto('/', { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
  await expect(page.getByRole('button', { name: 'Upload' })).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(page.getByRole('link', { name: 'Sign out' })).toBeVisible({ timeout: UI_TIMEOUT });
  await page.getByRole('link', { name: 'Sign out' }).click({ timeout: UI_TIMEOUT });
  await expect(page.getByRole('link', { name: 'Sign in' })).toHaveCount(2, { timeout: UI_TIMEOUT });
  await shot(page, testInfo, '09-sign-out-after-admin-flow.png');
});

