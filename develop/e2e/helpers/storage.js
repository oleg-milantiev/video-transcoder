const { expect } = require('@playwright/test');
const { UI_TIMEOUT } = require('./constants');

// ── Storage badge locator ─────────────────────────────────────────────────────
// The badge is rendered below the tabs on the home page AND at the bottom of
// video-details pages (both use renderStorageBadge from shared/StorageBadge.js).

function storageBadge(page) {
  return page.locator('div.bg-light.bg-opacity-10.rounded-4.p-3.mt-3').first();
}

// ── Text assertions ───────────────────────────────────────────────────────────

/** Assert the badge contains `text` (partial match, retries until UI_TIMEOUT). */
async function expectStorageBadgeContains(page, text) {
  await expect(storageBadge(page)).toContainText(text, { timeout: UI_TIMEOUT });
}

// ── Parse current "now" value ─────────────────────────────────────────────────

/**
 * Read the "used" storage value from the badge and return it as **MB** (integer, rounded).
 * The badge text contains a pattern like "120 MB / 1 GB" or "0 MB / 102 MB".
 * Returns 0 if the badge is not yet rendered or the value cannot be parsed.
 */
async function readStorageBadgeNowMB(page) {
  const text = (await storageBadge(page).textContent().catch(() => '')) || '';
  // Match: "{now_val} {now_unit} / {max_val} {max_unit}"
  const match = text.match(/(\d+(?:\.\d+)?)\s*(MB|GB)\s*\/\s*(\d+(?:\.\d+)?)\s*(MB|GB)/);
  if (!match) return 0;
  const val = parseFloat(match[1]);
  return match[2] === 'GB' ? Math.round(val * 1024) : Math.round(val);
}

// ── Polling helpers ───────────────────────────────────────────────────────────

/**
 * Poll until the badge "now" value exceeds `thresholdMB`.
 * Returns the new "now" value in MB once the condition is met.
 */
async function waitForStorageNowToExceed(page, thresholdMB, timeoutMs = 15000) {
  let current = 0;
  await expect.poll(async () => {
    current = await readStorageBadgeNowMB(page);
    return current;
  }, { timeout: timeoutMs }).toBeGreaterThan(thresholdMB);
  return current;
}

/**
 * Poll until the badge "now" value drops below `thresholdMB`.
 * Returns the new "now" value in MB once the condition is met.
 */
async function waitForStorageNowBelow(page, thresholdMB, timeoutMs = 15000) {
  let current = 0;
  await expect.poll(async () => {
    current = await readStorageBadgeNowMB(page);
    return current;
  }, { timeout: timeoutMs }).toBeLessThan(thresholdMB);
  return current;
}

// ── Exact-value helper ────────────────────────────────────────────────────────

/**
 * Wait until the badge shows exactly "0 MB / {maxLabel}" — i.e. storage fully freed.
 * `maxLabel` defaults to `'102 MB'` (the Free-02 tariff limit).
 */
async function expectStorageBadgeEmpty(page, maxLabel = '102 MB') {
  await expectStorageBadgeContains(page, `0 MB / ${maxLabel}`);
}

module.exports = {
  storageBadge,
  expectStorageBadgeContains,
  readStorageBadgeNowMB,
  waitForStorageNowToExceed,
  waitForStorageNowBelow,
  expectStorageBadgeEmpty,
};
