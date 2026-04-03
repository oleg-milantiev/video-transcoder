const { expect } = require('@playwright/test');
const { UI_TIMEOUT } = require('../../helpers/constants');
const { openAdminSection, mainTableBodyForHeading, submitCrudForm, dismissVisibleAdminModal, dismissAllVisibleModals } = require('../../helpers/admin');
const { shot } = require('../../helpers/screenshot');
const { clickAndAcceptConfirm } = require('../../helpers/dialogs');

async function ensureAdminModalDismissed(page) {
  if (typeof dismissAllVisibleModals === 'function') {
    await dismissAllVisibleModals(page);
    return;
  }

  if (typeof dismissVisibleAdminModal === 'function') {
    await dismissVisibleAdminModal(page).catch(() => {});
    return;
  }

  for (let i = 0; i < 3; i++) {
    const modal = page.locator('#modal-filters.show, .modal.show[aria-modal="true"]').first();
    if ((await modal.count()) === 0 || !(await modal.isVisible().catch(() => false))) {
      break;
    }
    await page.keyboard.press('Escape');
    await page.waitForTimeout(100);
  }
}

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
    await ensureAdminModalDismissed(page);
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
   await ensureAdminModalDismissed(page);

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
     // Wait for modal to be visible before interacting
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

    // Input may be hidden by CSS (Bootstrap collapse), just ensure it exists and can be filled
    await inputRetry.scrollIntoViewIfNeeded({ timeout: UI_TIMEOUT }).catch(() => {});
    await page.waitForTimeout(200);

    return inputRetry;
  }

async function filterUsersByEmail(page, emailNeedle, testInfo, screenshotName = 'admin-users-filtered.png', { expectMatch = true } = {}) {
  await openAdminSection(page, 'Users', '/admin/user');

  // Check if filter checkbox is already checked (filter already applied)
  const filterCheckbox = page.locator('input[type="checkbox"].filter-checkbox').first();
  const isFilterAlreadyApplied = (await filterCheckbox.count()) > 0 && await filterCheckbox.isChecked();

  if (isFilterAlreadyApplied) {
    // Filter already applied, just verify the user is visible
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

    // Find submit button - first try within the input's form, then fallback to modal buttons
    let submitButton = input.locator('xpath=ancestor::form[1]').first().locator('button[type="submit"], input[type="submit"]').first();

    // If no submit button found, look for Apply button in the filter modal
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

