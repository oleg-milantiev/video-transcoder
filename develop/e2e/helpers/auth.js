const { expect } = require('@playwright/test');
const { UI_TIMEOUT, NAV_TIMEOUT } = require('./constants');

function getAdminCredentials() {
  return {
    email: process.env.ADMIN_EMAIL || 'oleg@milantiev.com',
    password: process.env.ADMIN_PASSWORD || 'admin',
  };
}

function getTestCredentials() {
  return {
    email: process.env.TEST_EMAIL || 'test@test.com',
    password: process.env.TEST_PASSWORD || 'test',
  };
}

async function openHome(page) {
  await page.goto('/', { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
}

async function openSignIn(page) {
  await page.getByRole('link', { name: 'Sign in' }).last().click({ timeout: UI_TIMEOUT });
}

async function fillSignInCredentials(page, email, password) {
  await page.locator('#inputEmail').fill(email, { timeout: UI_TIMEOUT });
  await page.locator('#inputPassword').fill(password, { timeout: UI_TIMEOUT });
}

async function submitSignIn(page) {
  await page.getByRole('button', { name: 'Sign in' }).click({ timeout: UI_TIMEOUT });
}

async function loginAsAdmin(page) {
  const {email, password} = getAdminCredentials();
  await loginAs(page, email, password);
}

async function loginAsTest(page) {
  const {email, password} = getTestCredentials();
  await loginAs(page, email, password);
}

async function loginAs(page, email, password) {
  await openHome(page);
  await openSignIn(page);
  await fillSignInCredentials(page, email, password);
  await submitSignIn(page);
  await expect(page.getByRole('button', { name: 'Videos' })).toBeVisible({ timeout: UI_TIMEOUT });
}

async function logoutToPublic(page) {
  await openHome(page);
  await expect(page.getByRole('link', { name: 'Sign out' })).toBeVisible({ timeout: UI_TIMEOUT });
  await page.getByRole('link', { name: 'Sign out' }).click({ timeout: UI_TIMEOUT });
  await expect(page.getByRole('link', { name: 'Sign in' })).toHaveCount(2, { timeout: UI_TIMEOUT });
}

module.exports = {
  getAdminCredentials,
  getTestCredentials,
  openHome,
  openSignIn,
  fillSignInCredentials,
  submitSignIn,
  loginAsAdmin,
  loginAsTest,
  logoutToPublic,
};
