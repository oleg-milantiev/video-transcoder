/**
 * 03 · Contact Us form: verified on all pages + Enterprise tariff card
 *
 * Scenario:
 *  - login as test-03
 *  - for each page (home, terms, privacy, profile, tariffs):
 *      - click "Contact Us" in footer
 *      - fill text → Cancel → modal closed
 *      - click "Contact Us" in footer
 *      - click Send without text → validation "Message must not be empty"
 *      - fill text → Send → "Request received!" success
 *      - click OK → modal closed
 *  - on tariffs page: same full flow via Enterprise card "Contact us" button
 *  - logout
 */

const { test } = require('@playwright/test');
const {
  NAV_TIMEOUT,
  loginAs,
  logoutToPublic,
  runContactUsFlow,
  shot,
} = require('../helpers');

const TEST_03_EMAIL    = 'test-03@test.com';
const TEST_03_PASSWORD = 'test-03';

/** Pages where we test the footer Contact Us link (in order). */
const PAGES = [
  { name: 'home',    url: '/' },
  { name: 'terms',   url: '/terms' },
  { name: 'privacy', url: '/privacy' },
  { name: 'profile', url: '/profile' },
  { name: 'tariffs', url: '/tariffs' },
];

test('03 · Contact Us form: footer on all pages + Enterprise card on tariffs', async ({ page }, testInfo) => {
  // 5 pages × 4 shots each + tariffs Enterprise flow = generous budget
  test.setTimeout(2 * 60_000);

  // ── Login ─────────────────────────────────────────────────────────────────
  await loginAs(page, TEST_03_EMAIL, TEST_03_PASSWORD);
  await shot(page, testInfo, 'test03-00-login.png');

  // ── Footer Contact Us on every page ──────────────────────────────────────
  for (const { name, url } of PAGES) {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
    const footerTrigger = () => page.locator('#footer-contact-us').click();
    await runContactUsFlow(page, footerTrigger, testInfo, `test03-${name}-footer`);
  }

  // ── Tariffs — Enterprise card "Contact us" button ─────────────────────────
  // (we are already on /tariffs from the loop above; no extra navigation needed)
  const enterpriseTrigger = () =>
    page.getByRole('button', { name: 'Contact us' }).click();
  await runContactUsFlow(page, enterpriseTrigger, testInfo, 'test03-tariffs-enterprise');

  // ── Logout ────────────────────────────────────────────────────────────────
  await logoutToPublic(page);
  await shot(page, testInfo, 'test03-99-logout.png');
});
