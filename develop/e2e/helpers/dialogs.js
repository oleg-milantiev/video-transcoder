const { expect } = require('@playwright/test');
const { UI_TIMEOUT } = require('./constants');

async function clickAndAcceptConfirm(page, clickableLocator, expectedNativeMessagePart) {
  const nativeDialogPromise = page
    .waitForEvent('dialog', { timeout: 500 })
    .then(async (dialog) => {
      const message = dialog.message();
      await dialog.accept();
      return message;
    })
    .catch(() => null);

  await clickableLocator.click({ timeout: UI_TIMEOUT });

  const nativeMessage = await nativeDialogPromise;
  if (nativeMessage !== null) {
    if (expectedNativeMessagePart) {
      expect(nativeMessage).toContain(expectedNativeMessagePart);
    }
    return;
  }

  const confirmButton = page.getByRole('button', { name: 'Confirm' }).first();
  await confirmButton.waitFor({ state: 'visible', timeout: UI_TIMEOUT });
  await confirmButton.click({ timeout: UI_TIMEOUT });
  await expect(confirmButton).not.toBeVisible({ timeout: UI_TIMEOUT });
}

module.exports = { clickAndAcceptConfirm };

