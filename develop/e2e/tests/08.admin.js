/**
 * 08 · admin: dashboard sections check
 *
 * - Login as admin
 * - Navigate to admin panel
 * - Loop over all 7 sections: verify menu link, h1 heading, content type
 *   Always-empty sections (No results found.): Payments
 *   Always-have-rows sections (table.table.datagrid with 1+ rows): Users, Tariffs, Presets
 *   Optional sections (may be empty or have rows): Videos, Tasks, Logs
 */

const { test, expect } = require('@playwright/test');
const {
  UI_TIMEOUT,
  NAV_TIMEOUT,
  loginAsAdmin,
  logoutToPublic,
  openAdminDashboardFromHome,
  adminMenuLink,
  shot,
} = require('../helpers');

test('08 · admin: dashboard sections', async ({ page }, testInfo) => {
  await loginAsAdmin(page);
  await shot(page, testInfo, '08-00-login.png');

  await openAdminDashboardFromHome(page);
  await shot(page, testInfo, '08-01-admin-dashboard.png');

  // content: 'table' = must have 1+ rows, 'empty' = must be empty, 'any' = either is fine
  const sections = [
    { name: 'Users',    path: '/admin/user',    content: 'table' },
    { name: 'Tariffs',  path: '/admin/tariff',  content: 'table' },
    { name: 'Payments', path: '/admin/payment', content: 'empty' },
    { name: 'Videos',   path: '/admin/video',   content: 'any'   },
    { name: 'Presets',  path: '/admin/preset',  content: 'table' },
    { name: 'Tasks',    path: '/admin/task',    content: 'any'   },
    { name: 'Logs',     path: '/admin/log',     content: 'any'   },
  ];

  for (const section of sections) {
    // Menu link must be visible
    await expect(adminMenuLink(page, section.path)).toBeVisible({ timeout: UI_TIMEOUT });

    // Click the menu link to navigate
    await adminMenuLink(page, section.path).click({ timeout: UI_TIMEOUT });
    await expect(page).toHaveURL(new RegExp(section.path), { timeout: NAV_TIMEOUT });

    // h1 heading matches section name
    await expect(
      page.locator('h1', { hasText: section.name }).first()
    ).toBeVisible({ timeout: UI_TIMEOUT });

    if (section.content === 'table') {
      // Must have a datagrid table with at least one data row
      await expect(page.locator('table.table.datagrid').first()).toBeVisible({ timeout: UI_TIMEOUT });
      await expect(
        page.locator('table.table.datagrid tbody tr').first()
      ).toBeVisible({ timeout: UI_TIMEOUT });
    } else if (section.content === 'empty') {
      // Must show empty-state message
      await expect(page.locator('body')).toContainText('No results found.', { timeout: UI_TIMEOUT });
    }
    // 'any': section is accessible — no content assertion required

    await shot(page, testInfo, `08-section-${section.name.toLowerCase()}.png`);
  }

  await logoutToPublic(page);
  await shot(page, testInfo, '08-99-logout.png');
});
