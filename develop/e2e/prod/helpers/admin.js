const { expect } = require('@playwright/test');
const { UI_TIMEOUT } = require('../../helpers/constants');
const { openAdminSection, mainTableBodyForHeading, submitCrudForm } = require('../../helpers/admin');
const { shot } = require('../../helpers/screenshot');
const { clickAndAcceptConfirm } = require('../../helpers/dialogs');

function userRowByText(page, userText) {
  return mainTableBodyForHeading(page, 'Users').locator('tr', { hasText: userText }).first();
}

function usersFilterValueInput(page) {
  return page
    .locator('input[name="filters[email][value]"], input[name*="filters[email]"][name*="[value]"]')
    .first();
}

function usersFilterComparisonSelect(page) {
  return page
    .locator('select[name="filters[email][comparison]"], select[name*="filters[email]"][name*="[comparison]"]')
    .first();
}

async function openUsersFilterPanel(page) {
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
  }

  await expect(input).toBeVisible({ timeout: UI_TIMEOUT });
  return input;
}

async function filterUsersByEmail(page, emailNeedle, testInfo, screenshotName = 'admin-users-filtered.png', { expectMatch = true } = {}) {
  await openAdminSection(page, 'Users', '/admin/user');

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

  const filterForm = input.locator('xpath=ancestor::form[1]').first();
  const submitButton = filterForm.locator('button[type="submit"], input[type="submit"]').first();
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
  await filterUsersByEmail(page, userText, testInfo, screenshotName);
  await expect(userRowByText(page, userText)).toContainText(tariffTitle, { timeout: UI_TIMEOUT });
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
  userRowByText,
  filterUsersByEmail,
  setTariffForFilteredUser,
  deleteFilteredUser,
  deleteUserByEmail,
};

