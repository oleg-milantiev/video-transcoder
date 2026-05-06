/**
 * Profile page helpers.
 *
 * The /profile SPA view renders four section cards:
 *   Account · Tariff (+ Premium) · Videos & Transcoding · Storage
 *
 * Each "infoRow" is rendered as:
 *   <div class="row mb-2">
 *     <div class="col-sm-7 text-muted">{label}</div>
 *     <div class="col-sm-5 fw-semibold">{value}</div>
 *   </div>
 */

const { expect } = require('@playwright/test');
const { UI_TIMEOUT } = require('./constants');

/**
 * Return the `.card` element whose header contains an `h2` with the given heading text.
 * Works for Account, Tariff, Premium, Videos & Transcoding, Storage cards.
 */
function profileCard(page, heading) {
  return page.locator('.card', {
    has: page.locator('h2', { hasText: heading }),
  }).first();
}

/**
 * Locate the value cell (`div.col-sm-5.fw-semibold`) that sits next to the
 * label cell (`div.col-sm-7.text-muted`) matching `labelText`.
 * Searches the entire page — labels are unique within a profile view.
 */
function profileInfoRowValue(page, labelText) {
  return page
    .locator('div.col-sm-7.text-muted', { hasText: labelText })
    .first()
    .locator('xpath=./following-sibling::div[1]');
}

/**
 * Assert the value cell for `labelText` contains `expectedValue`.
 */
async function expectProfileInfoRowValue(page, labelText, expectedValue) {
  await expect(profileInfoRowValue(page, labelText)).toContainText(
    expectedValue,
    { timeout: UI_TIMEOUT }
  );
}

/**
 * Wait for the profile page to finish loading (the "Loading…" paragraph disappears
 * and at least one infoRow value is visible).
 */
async function waitForProfileLoaded(page) {
  // "Loading..." is a <p class="text-muted"> rendered while vm.loading is true.
  await expect(page.locator('p.text-muted', { hasText: 'Loading...' })).toHaveCount(0, { timeout: 15000 });
  // Also wait for actual content (Account heading)
  await expect(page.locator('h2', { hasText: 'Account' }).first()).toBeVisible({ timeout: UI_TIMEOUT });
}

module.exports = {
  profileCard,
  profileInfoRowValue,
  expectProfileInfoRowValue,
  waitForProfileLoaded,
};
