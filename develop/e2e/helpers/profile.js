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

// ── Plan feature lists (mirrors tariff/planCard.js PLANS) ────────────────────

const FREE_INCLUDED = [
  'Up to 500 MB per video',
  'Up to 10 minutes duration',
  'Up to 1080p resolution',
  '5 GB, 24 Hours storage',
  'Standard transcoding speed',
  'MP4 output format',
];

const FREE_EXCLUDED = [
  'Instant transcoding',
  'Multiple simultaneous tasks',
  'Priority queue',
  'Custom presets',
  'HLS streaming output',
];

const PREMIUM_INCLUDED = [
  'Up to 4 GB per video',
  'Up to 3 hours duration',
  'Up to 4K resolution',
  '50 GB, 7 Days storage',
  'Fast transcoding speed',
  'MP4, WebM output formats',
  'Instant transcoding',
  'Two simultaneous tasks',
  'Priority queue',
  'Custom presets',
];

const PREMIUM_EXCLUDED = ['HLS streaming output'];

// ── Section card helpers ──────────────────────────────────────────────────────

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
  await expect(
    page.locator('p.text-muted', { hasText: 'Loading...' })
  ).toHaveCount(0, { timeout: 15000 });
  await expect(
    page.locator('h2', { hasText: 'Account' }).first()
  ).toBeVisible({ timeout: UI_TIMEOUT });
}

/**
 * Verify the feature list inside a plan card.
 * Counts li.list-group-item elements with span.text-success (included) and
 * span.text-danger (excluded), then checks each label is present.
 */
async function checkFeatureItems(page, card, included, excluded) {
  const successItems = card.locator('li.list-group-item', {
    has: page.locator('span.text-success'),
  });
  const dangerItems = card.locator('li.list-group-item', {
    has: page.locator('span.text-danger'),
  });

  await expect(successItems).toHaveCount(included.length, { timeout: UI_TIMEOUT });
  await expect(dangerItems).toHaveCount(excluded.length,  { timeout: UI_TIMEOUT });

  for (const f of included) {
    await expect(successItems.filter({ hasText: f })).toHaveCount(1);
  }
  for (const f of excluded) {
    await expect(dangerItems.filter({ hasText: f })).toHaveCount(1);
  }
}

module.exports = {
  FREE_INCLUDED,
  FREE_EXCLUDED,
  PREMIUM_INCLUDED,
  PREMIUM_EXCLUDED,
  profileCard,
  profileInfoRowValue,
  expectProfileInfoRowValue,
  waitForProfileLoaded,
  checkFeatureItems,
};
