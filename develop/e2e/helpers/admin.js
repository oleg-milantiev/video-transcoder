const { expect } = require('@playwright/test');
const { UI_TIMEOUT, NAV_TIMEOUT } = require('./constants');
const { shot } = require('./screenshot');

function adminMenuLink(page, pathSuffix) {
  return page.locator(`a[href$="${pathSuffix}"]`).first();
}

function mainTableBodyForHeading(page, heading) {
  return page.locator('article', { has: page.getByRole('heading', { name: heading }) }).locator('table tbody').first();
}

async function dismissVisibleAdminModal(page) {
  const modal = page.locator('.modal.show[aria-modal="true"], #modal-filters.show').first();
  if ((await modal.count()) === 0) {
    return;
  }

  if (!(await modal.isVisible().catch(() => false))) {
    return;
  }

  const closeButton = modal
    .locator('button.btn-close, button[aria-label="Close"], [data-bs-dismiss="modal"]')
    .first();

  if ((await closeButton.count()) > 0) {
    await closeButton.click({ timeout: UI_TIMEOUT }).catch(() => {});
  } else {
    await page.keyboard.press('Escape').catch(() => {});
  }

  await expect(modal).not.toBeVisible({ timeout: UI_TIMEOUT });
}

async function dismissAllVisibleModals(page) {
  for (let i = 0; i < 3; i++) {
    const modal = page.locator('.modal.show[aria-modal="true"], #modal-filters.show').first();
    if ((await modal.count()) === 0 || !(await modal.isVisible().catch(() => false))) {
      break;
    }
    await page.keyboard.press('Escape');
    await page.waitForTimeout(100);
  }
}

async function openAdminSection(page, sectionName, pathSuffix) {
  await dismissAllVisibleModals(page);

  if (page.url().includes(pathSuffix)) {
    const heading = page.getByRole('heading', { name: sectionName }).first();
    if ((await heading.count()) > 0 && await heading.isVisible().catch(() => false)) {
      await expect(mainTableBodyForHeading(page, sectionName)).toBeVisible({ timeout: UI_TIMEOUT });
      return;
    }
  }

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

async function fillPresetCodeEditorBitrate(page, bitrateJson) {
  // CodeEditorField renders a hidden textarea; set it via JS and also try CM editor
  const bitrateStr = typeof bitrateJson === 'string' ? bitrateJson : JSON.stringify(bitrateJson, null, 2);
  await page.evaluate((val) => {
    const textarea = Array.from(document.querySelectorAll('textarea'))
      .find(t => t.name && t.name.toLowerCase().includes('bitrate'));
    if (textarea) {
      const setter = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value')?.set;
      if (setter) setter.call(textarea, val);
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
      textarea.dispatchEvent(new Event('change', { bubbles: true }));
    }
    // Also try CodeMirror 5 API
    const cmEl = document.querySelector('.CodeMirror');
    if (cmEl && cmEl.CodeMirror) {
      cmEl.CodeMirror.setValue(val);
    }
  }, bitrateStr);
}

async function createOrUpdatePreset(page, preset, testInfo) {
  // preset: { title, format, videoCodec, audioCodec, bitrateJson, tariffs? }
  // bitrateJson: object like {"180": 1.1} or JSON string
  await openAdminSection(page, 'Presets', '/admin/preset');
  const presetsTbody = mainTableBodyForHeading(page, 'Presets');
  const presetRows = presetsTbody.locator('tr', { hasText: preset.title });

  const fillPresetForm = async () => {
    await page.getByLabel('Title').fill(preset.title);
    await page.getByLabel('Format').fill(preset.format || 'mp4');
    await page.getByLabel('Video Codec').fill(preset.videoCodec || preset.codec || 'h264');
    await page.getByLabel('Audio Codec').fill(preset.audioCodec || 'aac');
    if (preset.bitrateJson) {
      await fillPresetCodeEditorBitrate(page, preset.bitrateJson);
    }
    if (preset.tariffs && preset.tariffs.length > 0) {
      // EasyAdmin renders AssociationField tariffs as <select multiple>
      const tariffsSelect = page.locator('select').filter({ has: page.locator('option') }).last();
      const tariffsSelectByLabel = page.getByLabel('Tariffs');
      const sel = (await tariffsSelectByLabel.count()) > 0 ? tariffsSelectByLabel.first() : tariffsSelect;
      await sel.selectOption(preset.tariffs).catch(() => {});
    }
  };

  if ((await presetRows.count()) === 0) {
    await expect(page.locator('a.action-new')).toBeVisible({ timeout: UI_TIMEOUT });
    await page.locator('a.action-new').click({ timeout: UI_TIMEOUT });
    await fillPresetForm();
    await submitCrudForm(page);
  }

  const presetRow = presetsTbody.locator('tr', { hasText: preset.title }).first();
  await expect(presetRow).toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, `03-preset-${preset.title}-present.png`);

  await presetRow.locator('a.action-edit').click({ timeout: UI_TIMEOUT });
  await page.getByLabel('Format').fill(preset.format || 'mp4');
  await page.getByLabel('Video Codec').fill(preset.videoCodec || preset.codec || 'h264');
  await page.getByLabel('Audio Codec').fill(preset.audioCodec || 'aac');
  if (preset.bitrateJson) {
    await fillPresetCodeEditorBitrate(page, preset.bitrateJson);
  }
  if (preset.tariffs && preset.tariffs.length > 0) {
    const tariffsSelectByLabel = page.getByLabel('Tariffs');
    const sel = (await tariffsSelectByLabel.count()) > 0 ? tariffsSelectByLabel.first() : null;
    if (sel) await sel.selectOption(preset.tariffs).catch(() => {});
  }
  await submitCrudForm(page);

  const persistedRow = presetsTbody.locator('tr', { hasText: preset.title }).first();
  await expect(persistedRow).toBeVisible({ timeout: UI_TIMEOUT });
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

  if (tariff.presets && tariff.presets.length > 0) {
    for (const presetTitle of tariff.presets) {
      const presetsSelect = page.locator('select[name$="[presets]"]').first();
      if ((await presetsSelect.count()) > 0) {
        await presetsSelect.selectOption({ label: presetTitle }, { timeout: UI_TIMEOUT });
      } else {
        const presetsInput = page.getByLabel('Presets').first();
        await presetsInput.click({ timeout: UI_TIMEOUT });
        await page.locator('div.item:has-text("'+ presetTitle +'"), div.option:has-text("'+ presetTitle +'")').click({ timeout: UI_TIMEOUT });
      }
    }
  }
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
  dismissVisibleAdminModal,
  dismissAllVisibleModals,
  createOrUpdatePreset,
  createOrUpdateTariffByTitle,
  assignTariffToUser,
  createUserWithTariff,
};
