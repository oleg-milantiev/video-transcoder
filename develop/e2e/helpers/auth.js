const { expect } = require('@playwright/test');
const { UI_TIMEOUT, NAV_TIMEOUT } = require('./constants');

function getAdminCredentials() {
  return {
    adminEmail: process.env.ADMIN_EMAIL || 'oleg@milantiev.com',
    adminPassword: process.env.ADMIN_PASSWORD || 'admin',
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
  const { adminEmail, adminPassword } = getAdminCredentials();
  await openHome(page);
  await openSignIn(page);
  await fillSignInCredentials(page, adminEmail, adminPassword);
  await submitSignIn(page);
  await expect(page.getByRole('button', { name: 'Videos' })).toBeVisible({ timeout: UI_TIMEOUT });
}

async function logoutToPublic(page) {
  await expect(page.getByRole('link', { name: 'Sign out' })).toBeVisible({ timeout: UI_TIMEOUT });
  await page.getByRole('link', { name: 'Sign out' }).click({ timeout: UI_TIMEOUT });
  await expect(page.getByRole('link', { name: 'Sign in' })).toHaveCount(2, { timeout: UI_TIMEOUT });
}

module.exports = {
  getAdminCredentials,
  openHome,
  openSignIn,
  fillSignInCredentials,
  submitSignIn,
  loginAsAdmin,
  logoutToPublic,
};


