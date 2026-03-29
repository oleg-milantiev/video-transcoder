const { expect } = require('@playwright/test');
const { UI_TIMEOUT, NAV_TIMEOUT } = require('./constants');
const { shot } = require('./screenshot');

function adminMenuLink(page, pathSuffix) {
  return page.locator(`a[href$="${pathSuffix}"]`).first();
}

function mainTableBodyForHeading(page, heading) {
  return page.locator('article', { has: page.getByRole('heading', { name: heading }) }).locator('table tbody').first();
}

async function openAdminSection(page, sectionName, pathSuffix) {
  await adminMenuLink(page, pathSuffix).click({ timeout: UI_TIMEOUT });
  await expect(page).toHaveURL(/\/admin/, { timeout: NAV_TIMEOUT });
  await expect(page.getByRole('heading', { name: sectionName }).first()).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(mainTableBodyForHeading(page, sectionName)).toBeVisible({ timeout: UI_TIMEOUT });
}

async function openAdminDashboardFromHome(page) {
  await expect(page.getByRole('link', { name: 'Admin', exact: true })).toBeVisible({ timeout: UI_TIMEOUT });
  await page.getByRole('link', { name: 'Admin', exact: true }).click({ timeout: UI_TIMEOUT });
  await expect(page).toHaveURL(/\/admin/, { timeout: NAV_TIMEOUT });
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

async function ensureAdminMenuSectionsVisible(page) {
  await expect(adminMenuLink(page, '/admin/user')).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(adminMenuLink(page, '/admin/tariff')).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(adminMenuLink(page, '/admin/video')).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(adminMenuLink(page, '/admin/preset')).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(adminMenuLink(page, '/admin/task')).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(adminMenuLink(page, '/admin/log')).toBeVisible({ timeout: UI_TIMEOUT });
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
  await shot(page, testInfo, `03-preset-${preset.title}-present.png`);

  await presetRow.locator('a.action-edit').click({ timeout: UI_TIMEOUT });
  await page.getByLabel('Width').fill(String(preset.width));
  await page.getByLabel('Height').fill(String(preset.height));
  await page.getByLabel('Codec').fill(preset.codec);
  await page.getByLabel('Bitrate (Mbps)').fill(String(preset.bitrate));
  await submitCrudForm(page);

  const persistedRow = presetsTbody.locator('tr', { hasText: preset.title }).first();
  await expect(persistedRow).toContainText(String(preset.width), { timeout: UI_TIMEOUT });
  await expect(persistedRow).toContainText(String(preset.height), { timeout: UI_TIMEOUT });
}

async function fillTariffFields(page, tariff) {
  await page.getByLabel('Delay').fill(String(tariff.delay));
  await page.getByLabel('Parallel tasks').fill(String(tariff.instance));
  await page.getByLabel('Max video duration').fill(String(tariff.videoDuration));
  await page.getByLabel('Max video size').fill(String(tariff.videoSize));
  await page.getByLabel('Max width').fill(String(tariff.maxWidth));
  await page.getByLabel('Max height').fill(String(tariff.maxHeight));
  await page.getByLabel('Storage (GB)').fill(String(tariff.storageGb));
  await page.getByLabel('Storage retention').fill(String(tariff.storageHour));
}

async function createOrUpdateTariffByTitle(page, title, tariff, testInfo, screenshotName) {
  await openAdminSection(page, 'Tariffs', '/admin/tariff');

  const tariffsTbody = mainTableBodyForHeading(page, 'Tariffs');
  const tariffRows = tariffsTbody.locator('tr', { hasText: title });

  if ((await tariffRows.count()) === 0) {
    await expect(page.locator('a.action-new')).toBeVisible({ timeout: UI_TIMEOUT });
    await page.locator('a.action-new').click({ timeout: UI_TIMEOUT });

    await page.getByLabel('Title').fill(title);
    await fillTariffFields(page, tariff);
    await submitCrudForm(page);
  }

  const tariffRow = tariffsTbody.locator('tr', { hasText: title }).first();
  await expect(tariffRow).toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, screenshotName);

  await tariffRow.locator('a.action-edit').click({ timeout: UI_TIMEOUT });
  await fillTariffFields(page, tariff);
  await submitCrudForm(page);

  const persistedRow = tariffsTbody.locator('tr', { hasText: title }).first();
  await expect(persistedRow).toContainText(String(tariff.delay), { timeout: UI_TIMEOUT });
  await expect(persistedRow).toContainText(String(tariff.instance), { timeout: UI_TIMEOUT });
}

async function assignTariffToUser(page, userEmail, tariffTitle, testInfo, screenshotName = '08-user-tariff-assigned.png') {
  await openAdminSection(page, 'Users', '/admin/user');

  const usersTbody = mainTableBodyForHeading(page, 'Users');
  const userRow = usersTbody.locator('tr', { hasText: userEmail }).first();
  await expect(userRow).toBeVisible({ timeout: UI_TIMEOUT });

  await userRow.locator('a.action-edit').click({ timeout: UI_TIMEOUT });

  const tariffSelect = page.locator('select[name$="[tariff]"]').first();
  if ((await tariffSelect.count()) > 0) {
    await tariffSelect.selectOption({ label: tariffTitle }, { timeout: UI_TIMEOUT });
  } else {
    const tariffInput = page.getByLabel('Tariff').first();
    await tariffInput.click({ timeout: UI_TIMEOUT });
    await tariffInput.fill(tariffTitle, { timeout: UI_TIMEOUT });
    await page.keyboard.press('Enter');
  }

  await submitCrudForm(page);
  await expect(mainTableBodyForHeading(page, 'Users').locator('tr', { hasText: userEmail }).first()).toContainText(tariffTitle, {
    timeout: UI_TIMEOUT,
  });

  if (testInfo) {
    await shot(page, testInfo, screenshotName);
  }
}

async function createUserWithTariff(page, email, password, tariffTitle) {
  await openAdminSection(page, 'Users', '/admin/user');
  await page.locator('a.action-new').click({ timeout: UI_TIMEOUT });

  await page.getByLabel('Email').first().fill(email, { timeout: UI_TIMEOUT });
  await page.getByLabel('Password').first().fill(password, { timeout: UI_TIMEOUT });

  await page.locator('button.field-collection-add-button').first().click({ timeout: UI_TIMEOUT });
  await page.locator('input#UserEntity_roles_0').first().fill('ROLE_USER', { timeout: UI_TIMEOUT });

  const tariffSelect = page.locator('select[name$="[tariff]"]').first();
  if ((await tariffSelect.count()) > 0) {
    await tariffSelect.selectOption({ label: tariffTitle }, { timeout: UI_TIMEOUT });
  } else {
    const tariffInput = page.getByLabel('Tariff').first();
    await tariffInput.click({ timeout: UI_TIMEOUT });
    await tariffInput.fill(tariffTitle, { timeout: UI_TIMEOUT });
    await page.keyboard.press('Enter');
  }

  await submitCrudForm(page);
  await openAdminSection(page, 'Users', '/admin/user');
  await expect(mainTableBodyForHeading(page, 'Users')).toContainText(email, { timeout: UI_TIMEOUT });
}

module.exports = {
  adminMenuLink,
  mainTableBodyForHeading,
  openAdminSection,
  openAdminDashboardFromHome,
  submitCrudForm,
  ensureAdminMenuSectionsVisible,
  createOrUpdatePreset,
  createOrUpdateTariffByTitle,
  assignTariffToUser,
  createUserWithTariff,
};

