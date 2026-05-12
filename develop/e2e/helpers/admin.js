const { expect } = require('@playwright/test');
const { UI_TIMEOUT, NAV_TIMEOUT } = require('./constants');
const { shot } = require('./screenshot');
const { clickAndAcceptConfirm } = require('./dialogs');

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
        await page.locator('div.item:text-is("'+ presetTitle +'"), div.option:text-is("'+ presetTitle +'")').click({ timeout: UI_TIMEOUT });
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

// ── Users filter & management ─────────────────────────────────────────────────

function userRowByText(page, userText) {
  return mainTableBodyForHeading(page, 'Users').locator('tr', { hasText: userText }).first();
}

function usersFilterValueInput(page) {
  return page
    .locator('input[name="filters[email][value]"]:not([type="hidden"]), input[name*="filters[email]"][name*="[value]"]:not([type="hidden"])')
    .first();
}

function usersFilterComparisonSelect(page) {
  return page
    .locator('select[name="filters[email][comparison]"], select[name*="filters[email]"][name*="[comparison]"]')
    .first();
}

function usersFilterModal(page) {
  return page.locator('#modal-filters.show, .modal.show[aria-modal="true"]').first();
}

async function closeUsersFilterModal(page) {
  const modal = usersFilterModal(page);
  if ((await modal.count()) === 0 || !(await modal.isVisible().catch(() => false))) {
    await dismissAllVisibleModals(page);
    return;
  }

  const closeButton = modal.locator('button.btn-close, button[aria-label="Close"], [data-bs-dismiss="modal"]').first();
  if ((await closeButton.count()) > 0) {
    await closeButton.click({ timeout: UI_TIMEOUT }).catch(() => {});
  } else {
    await page.keyboard.press('Escape').catch(() => {});
  }

  await expect(modal).not.toBeVisible({ timeout: UI_TIMEOUT }).catch(() => {});
}

async function openUsersFilterPanel(page) {
  await dismissAllVisibleModals(page);

  const input = usersFilterValueInput(page);
  if ((await input.count()) > 0 && await input.isVisible().catch(() => false)) {
    return input;
  }

  const filterToggle = page
    .locator('a.action-filters, button.action-filters, a.action-filters-button, button.action-filters-button')
    .or(page.getByRole('button', { name: /filters?/i }))
    .or(page.getByRole('link', { name: /filters?/i }))
    .first();

  if ((await filterToggle.count()) > 0) {
    await filterToggle.click({ timeout: UI_TIMEOUT });
    await page.waitForTimeout(300);
  }

  const modal = usersFilterModal(page);
  if ((await modal.count()) > 0) {
    await expect(modal).toBeVisible({ timeout: UI_TIMEOUT });
    const emailTab = modal.getByRole('link', { name: 'Email', exact: true }).first();
    if ((await emailTab.count()) > 0) {
      await emailTab.click({ timeout: UI_TIMEOUT }).catch(() => {});
      await page.waitForTimeout(500);
    }
  }

  const inputRetry = usersFilterValueInput(page);
  if ((await inputRetry.count()) === 0) {
    throw new Error('Filter email input not found. Filter panel may not be properly initialized.');
  }

  await inputRetry.scrollIntoViewIfNeeded({ timeout: UI_TIMEOUT }).catch(() => {});
  await page.waitForTimeout(200);

  return inputRetry;
}

async function filterUsersByEmail(page, emailNeedle, testInfo, screenshotName = 'admin-users-filtered.png', { expectMatch = true } = {}) {
  await openAdminSection(page, 'Users', '/admin/user');

  const filterCheckbox = page.locator('input[type="checkbox"].filter-checkbox').first();
  const isFilterAlreadyApplied = (await filterCheckbox.count()) > 0 && await filterCheckbox.isChecked();

  if (isFilterAlreadyApplied) {
    await expect(mainTableBodyForHeading(page, 'Users')).toBeVisible({ timeout: UI_TIMEOUT });
    if (expectMatch) {
      await expect(userRowByText(page, emailNeedle)).toBeVisible({ timeout: UI_TIMEOUT });
    }
    if (testInfo) {
      await shot(page, testInfo, screenshotName);
    }
    return;
  }

  const input = await openUsersFilterPanel(page);
  await input.fill(emailNeedle, { timeout: UI_TIMEOUT });

  const comparison = usersFilterComparisonSelect(page);
  if ((await comparison.count()) > 0) {
    const options = await comparison.locator('option').evaluateAll((nodes) =>
      nodes.map((node) => ({ value: node.value, label: (node.textContent || '').trim() }))
    );
    const preferred = options.find((option) => /contain/i.test(option.label) || /contain/i.test(option.value)) || options[0];
    if (preferred) {
      await comparison.selectOption(preferred.value, { timeout: UI_TIMEOUT });
    }
  }

  let submitButton = input.locator('xpath=ancestor::form[1]').first().locator('button[type="submit"], input[type="submit"]').first();

  if ((await submitButton.count()) === 0) {
    const modal = usersFilterModal(page);
    if ((await modal.count()) > 0) {
      submitButton = modal.getByRole('button', { name: /apply/i }).first();
    }
  }

  await Promise.all([
    page.waitForLoadState('domcontentloaded').catch(() => {}),
    submitButton.click({ timeout: UI_TIMEOUT }),
  ]);

  await expect(mainTableBodyForHeading(page, 'Users')).toBeVisible({ timeout: UI_TIMEOUT });

  if (expectMatch) {
    await expect(userRowByText(page, emailNeedle)).toBeVisible({ timeout: UI_TIMEOUT });
  }

  if (testInfo) {
    await shot(page, testInfo, screenshotName);
  }

  await closeUsersFilterModal(page);
}

async function setTariffForFilteredUser(page, userText, tariffTitle, testInfo, screenshotName = 'admin-user-tariff-updated.png') {
  const row = userRowByText(page, userText);
  await expect(row).toBeVisible({ timeout: UI_TIMEOUT });
  await row.locator('a.action-edit').first().click({ timeout: UI_TIMEOUT });

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
  await expect(userRowByText(page, userText)).toContainText(tariffTitle, { timeout: UI_TIMEOUT });

  if (testInfo) {
    await shot(page, testInfo, screenshotName);
  }
}

async function deleteFilteredUser(page, userText, testInfo, screenshotName = 'admin-user-deleted.png') {
  const row = userRowByText(page, userText);
  if ((await row.count()) === 0) {
    return false;
  }

  await expect(row).toBeVisible({ timeout: UI_TIMEOUT });
  const deleteAction = row.locator('.action-delete').first();
  if ((await deleteAction.count()) === 0) {
    return false;
  }

  await clickAndAcceptConfirm(page, deleteAction);
  await expect(userRowByText(page, userText)).toHaveCount(0, { timeout: UI_TIMEOUT });

  if (testInfo) {
    await shot(page, testInfo, screenshotName);
  }

  return true;
}

async function deleteUserByEmail(page, emailNeedle, testInfo, screenshotName = 'admin-user-deleted.png') {
  await filterUsersByEmail(page, emailNeedle, testInfo, 'admin-users-filtered-for-delete.png', { expectMatch: false });

  const row = userRowByText(page, emailNeedle);
  if ((await row.count()) === 0) {
    return false;
  }

  return deleteFilteredUser(page, emailNeedle, testInfo, screenshotName);
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
  userRowByText,
  filterUsersByEmail,
  setTariffForFilteredUser,
  deleteFilteredUser,
  deleteUserByEmail,
};
