const { expect } = require('@playwright/test');
const { UI_TIMEOUT } = require('./constants');
const { shot } = require('./screenshot');

/**
 * Run the full Contact Us modal flow for a single trigger.
 *
 * Sequence:
 *   A) trigger → fill text → Cancel  →  modal closed
 *   B) trigger → click Send empty    →  validation "Message must not be empty"
 *      → fill text → Send            →  success "Request received!"
 *   C) click OK                      →  modal closed
 *
 * @param {import('@playwright/test').Page} page
 * @param {() => Promise<void>} triggerFn  - async function that opens the modal
 * @param {import('@playwright/test').TestInfo} testInfo
 * @param {string} prefix  - screenshot name prefix (e.g. 'test03-home-footer')
 */
async function runContactUsFlow(page, triggerFn, testInfo, prefix) {
  const popup      = page.locator('.swal2-popup');
  const title      = page.locator('.swal2-title');
  const textarea   = page.locator('.swal2-textarea');
  const cancelBtn  = page.locator('button.swal2-cancel');
  const confirmBtn = page.locator('button.swal2-confirm');
  const validation = page.locator('.swal2-validation-message');

  // ── A: open → fill → Cancel ──────────────────────────────────────────────────
  await triggerFn();
  await expect(popup).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(title).toContainText('Contact Us', { timeout: UI_TIMEOUT });
  await textarea.fill('Test message');
  await cancelBtn.click();
  await expect(popup).not.toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, `${prefix}-a-cancelled.png`);

  // ── B: open → Send empty → validation error ───────────────────────────────────
  await triggerFn();
  await expect(popup).toBeVisible({ timeout: UI_TIMEOUT });
  await confirmBtn.click();
  await expect(validation).toContainText('Message must not be empty', { timeout: UI_TIMEOUT });
  await shot(page, testInfo, `${prefix}-b-validation.png`);

  // ── C: fill text in same modal → Send → success ──────────────────────────────
  await textarea.fill('Test message from automated test');
  await confirmBtn.click();
  await expect(title).toContainText('Request received!', { timeout: UI_TIMEOUT });
  await expect(page.locator('.swal2-html-container')).toContainText(
    'We received your request and will contact you via email',
    { timeout: UI_TIMEOUT }
  );
  await shot(page, testInfo, `${prefix}-c-success.png`);

  // ── D: click OK → modal closes ───────────────────────────────────────────────
  await confirmBtn.click();
  await expect(popup).not.toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, `${prefix}-d-closed.png`);
}

module.exports = {
  runContactUsFlow,
};
