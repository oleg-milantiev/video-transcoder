/**
 * 04 · Static texts: home page, Terms of Service, Privacy Policy
 *
 * Scenario:
 *  - open home as guest
 *    - find "Why Sign Up?" heading and all 4 feature cards
 *    - click "Sign In to Get Started" → see login form (don't sign in)
 *  - for each state (guest, logged in as test-04):
 *    - in footer: verify Terms and Privacy links exist
 *    - click Terms → h1 "Terms of Service" + h5 sections 1–9
 *    - click Privacy (from footer on Terms page) → h1 "Privacy Policy" + h5 sections 1–10
 */

const { test, expect } = require('@playwright/test');
const {
  UI_TIMEOUT,
  NAV_TIMEOUT,
  openHome,
  loginAs,
  logoutToPublic,
  shot,
} = require('../helpers');

const TEST_04_EMAIL    = 'test-04@test.com';
const TEST_04_PASSWORD = 'test-04';

const FEATURES = [
  'Fast Processing',
  'Flexible Presets',
  'Secure Storage',
  'Track Progress',
];

const TERMS_SECTIONS   = ['1.', '2.', '3.', '4.', '5.', '6.', '7.', '8.', '9.'];
const PRIVACY_SECTIONS = ['1.', '2.', '3.', '4.', '5.', '6.', '7.', '8.', '9.', '10.'];

// ── helpers ───────────────────────────────────────────────────────────────────

async function checkTerms(page, testInfo, prefix) {
  await page.getByRole('link', { name: 'Terms' }).first().click({ timeout: UI_TIMEOUT });
  await expect(page).toHaveURL(/\/terms/, { timeout: NAV_TIMEOUT });
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Terms of Service', { timeout: UI_TIMEOUT });
  for (const n of TERMS_SECTIONS) {
    await expect(page.locator('h5').filter({ hasText: n }).first()).toBeVisible({ timeout: UI_TIMEOUT });
  }
  await shot(page, testInfo, `${prefix}-terms.png`);
}

async function checkPrivacy(page, testInfo, prefix) {
  await page.getByRole('link', { name: 'Privacy' }).first().click({ timeout: UI_TIMEOUT });
  await expect(page).toHaveURL(/\/privacy/, { timeout: NAV_TIMEOUT });
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Privacy Policy', { timeout: UI_TIMEOUT });
  for (const n of PRIVACY_SECTIONS) {
    await expect(page.locator('h5').filter({ hasText: n }).first()).toBeVisible({ timeout: UI_TIMEOUT });
  }
  await shot(page, testInfo, `${prefix}-privacy.png`);
}

async function checkFooterTermsAndPrivacy(page, testInfo, prefix) {
  // Verify footer links are present before clicking
  await expect(page.getByRole('link', { name: 'Terms' }).first()).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(page.getByRole('link', { name: 'Privacy' }).first()).toBeVisible({ timeout: UI_TIMEOUT });

  await checkTerms(page, testInfo, prefix);
  // Privacy link is in the footer of the Terms page too — no need to navigate back
  await checkPrivacy(page, testInfo, prefix);
}

// ── test ──────────────────────────────────────────────────────────────────────

test('04 · texts: home features, sign-in gate, terms & privacy (guest + auth)', async ({ page }, testInfo) => {
  test.setTimeout(90_000);

  // ── Part 1: Guest — home page content ────────────────────────────────────────
  await openHome(page);
  await shot(page, testInfo, 'test04-00-home-guest.png');

  await expect(page.getByRole('heading', { name: 'Why Sign Up?' })).toBeVisible({ timeout: UI_TIMEOUT });
  for (const feature of FEATURES) {
    await expect(page.locator('h5').filter({ hasText: feature }).first()).toBeVisible({ timeout: UI_TIMEOUT });
  }
  await shot(page, testInfo, 'test04-01-home-features.png');

  // Click "Sign In to Get Started" → login form appears
  await page.getByRole('link', { name: 'Sign In to Get Started' }).click({ timeout: UI_TIMEOUT });
  await expect(page.locator('#inputEmail')).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(page.locator('#inputPassword')).toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, 'test04-02-login-form.png');

  // ── Part 2: Guest — Terms & Privacy ──────────────────────────────────────────
  // Navigate back to home so footer links are reachable
  await openHome(page);
  await checkFooterTermsAndPrivacy(page, testInfo, 'test04-guest');

  // ── Part 3: Authenticated — Terms & Privacy ───────────────────────────────────
  await loginAs(page, TEST_04_EMAIL, TEST_04_PASSWORD);
  await shot(page, testInfo, 'test04-10-logged-in.png');

  await checkFooterTermsAndPrivacy(page, testInfo, 'test04-auth');

  // ── Logout ────────────────────────────────────────────────────────────────────
  await logoutToPublic(page);
  await shot(page, testInfo, 'test04-99-logout.png');
});
