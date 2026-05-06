/**
 * 07 · Tariffs page: plan cards for Free user, then Premium user
 *
 * PHASE 1 — FREE USER (test-07)
 *   - Login as test-07 (Free tariff).
 *   - Navigate to /tariffs and wait for the page to fully render.
 *
 *   Free card:
 *     - h2.h4 heading "Free"
 *     - 6 included features (✔ span.text-success): FREE_INCLUDED
 *     - 5 excluded features (⊘ span.text-danger):  FREE_EXCLUDED
 *     - "Current plan" corner ribbon visible
 *     - Card footer button: "Current plan" (disabled)
 *
 *   Premium card:
 *     - h2.h4 heading "Premium"
 *     - 10 included features (✔ span.text-success): PREMIUM_INCLUDED
 *     - 1 excluded feature  (⊘ span.text-danger):  PREMIUM_EXCLUDED
 *     - Card footer button: "Upgrade to Premium"
 *
 *   Enterprise card:
 *     - h2.h4 heading "Enterprise"
 *     - 12 included features (✔ span.text-success): ENTERPRISE_INCLUDED
 *     - 0 excluded features  (⊘ span.text-danger):  ENTERPRISE_EXCLUDED
 *     - Card footer button: "Contact us"
 *
 *   Profile → /tariffs navigation:
 *     - Navigate to /profile, click "Upgrade" link → lands on /tariffs
 *     - Tariffs page renders correctly (Free card visible)
 *
 * PHASE 2 — PREMIUM USER (test-06)
 *   - Login as test-06 (Premium tariff).
 *   - Navigate to /tariffs.
 *
 *   Premium card:
 *     - "Current plan" corner ribbon visible
 *     - Card footer button: "Current plan" (disabled)
 *
 *   Free card:
 *     - No "Current plan" button (Free is not the current plan of Premium user)
 *
 *   Enterprise card:
 *     - Card footer button: "Contact us"
 */

const { test, expect } = require('@playwright/test');
const {
  UI_TIMEOUT,
  NAV_TIMEOUT,
  loginAs,
  logoutToPublic,
  FREE_INCLUDED,
  FREE_EXCLUDED,
  PREMIUM_INCLUDED,
  PREMIUM_EXCLUDED,
  ENTERPRISE_INCLUDED,
  ENTERPRISE_EXCLUDED,
  tariffCard,
  waitForTariffsLoaded,
  waitForProfileLoaded,
  profileInfoRowValue,
  checkFeatureItems,
  shot,
} = require('../helpers');

const FREE_EMAIL    = 'test-07@test.com';
const FREE_PASSWORD = 'test-07';
const PREMIUM_EMAIL    = 'test-06@test.com';
const PREMIUM_PASSWORD = 'test-06';

test('07 · tariffs page: plan cards for free and premium users', async ({ page }, testInfo) => {
  test.setTimeout(3 * 60_000);

  // ══ PHASE 1: Free user — all three plan cards ════════════════════════════

  await loginAs(page, FREE_EMAIL, FREE_PASSWORD);
  await shot(page, testInfo, 'test07-00-free-logged-in.png');

  await page.goto('/tariffs', { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
  await waitForTariffsLoaded(page);
  await shot(page, testInfo, 'test07-01-tariffs-loaded.png');

  // ── Free card (current plan) ──────────────────────────────────────────────
  const freePlanCard = tariffCard(page, 'Free');
  await expect(freePlanCard).toBeVisible({ timeout: UI_TIMEOUT });
  await checkFeatureItems(page, freePlanCard, FREE_INCLUDED, FREE_EXCLUDED);
  await expect(freePlanCard.locator('.card-footer button', { hasText: 'Current plan' })).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(freePlanCard.locator('.card-footer button', { hasText: 'Current plan' })).toBeDisabled({ timeout: UI_TIMEOUT });
  // Current plan ribbon (aria-label="Your current plan")
  await expect(freePlanCard.locator('[aria-label="Your current plan"]')).toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, 'test07-02-free-card.png');

  // ── Premium card ──────────────────────────────────────────────────────────
  const premiumPlanCard = tariffCard(page, 'Premium');
  await expect(premiumPlanCard).toBeVisible({ timeout: UI_TIMEOUT });
  await checkFeatureItems(page, premiumPlanCard, PREMIUM_INCLUDED, PREMIUM_EXCLUDED);
  await expect(premiumPlanCard.locator('.card-footer button', { hasText: 'Upgrade to Premium' })).toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, 'test07-03-premium-card.png');

  // ── Enterprise card ───────────────────────────────────────────────────────
  const enterprisePlanCard = tariffCard(page, 'Enterprise');
  await expect(enterprisePlanCard).toBeVisible({ timeout: UI_TIMEOUT });
  await checkFeatureItems(page, enterprisePlanCard, ENTERPRISE_INCLUDED, ENTERPRISE_EXCLUDED);
  await expect(enterprisePlanCard.locator('.card-footer button', { hasText: 'Contact us' })).toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, 'test07-04-enterprise-card.png');

  // ── Profile → tariffs navigation via "Upgrade" link ──────────────────────
  await page.goto('/profile', { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
  await waitForProfileLoaded(page);
  const upgradeLink = profileInfoRowValue(page, 'Current plan').getByRole('link', { name: /Upgrade/i });
  await expect(upgradeLink).toBeVisible({ timeout: UI_TIMEOUT });
  await upgradeLink.click({ timeout: UI_TIMEOUT });
  await expect(page).toHaveURL(/\/tariffs/, { timeout: NAV_TIMEOUT });
  await waitForTariffsLoaded(page);
  await shot(page, testInfo, 'test07-05-profile-upgrade-link.png');

  await logoutToPublic(page);
  await shot(page, testInfo, 'test07-10-free-logout.png');

  // ══ PHASE 2: Premium user — current plan ribbon on Premium card ══════════

  await loginAs(page, PREMIUM_EMAIL, PREMIUM_PASSWORD);
  await shot(page, testInfo, 'test07-11-premium-logged-in.png');

  await page.goto('/tariffs', { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
  await waitForTariffsLoaded(page);
  await shot(page, testInfo, 'test07-12-tariffs-premium-user.png');

  // Premium is current plan
  const premiumCurrentCard = tariffCard(page, 'Premium');
  await expect(premiumCurrentCard).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(premiumCurrentCard.locator('.card-footer button', { hasText: 'Current plan' })).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(premiumCurrentCard.locator('.card-footer button', { hasText: 'Current plan' })).toBeDisabled({ timeout: UI_TIMEOUT });
  await expect(premiumCurrentCard.locator('[aria-label="Your current plan"]')).toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, 'test07-13-premium-current-card.png');

  // Free card — no "Current plan" button for Premium user
  const freeCardForPremium = tariffCard(page, 'Free');
  await expect(freeCardForPremium).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(freeCardForPremium.locator('.card-footer button', { hasText: 'Current plan' })).toHaveCount(0);
  await shot(page, testInfo, 'test07-14-free-card-not-current.png');

  // Enterprise card — "Contact us" still visible
  const enterpriseCardForPremium = tariffCard(page, 'Enterprise');
  await expect(enterpriseCardForPremium).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(enterpriseCardForPremium.locator('.card-footer button', { hasText: 'Contact us' })).toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, 'test07-15-enterprise-card-premium-user.png');

  await logoutToPublic(page);
  await shot(page, testInfo, 'test07-99-logout.png');
});
