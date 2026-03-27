const { test, expect } = require('@playwright/test');
const {
  UI_TIMEOUT,
  NAV_TIMEOUT,
  getAdminCredentials,
  openHome,
  openSignIn,
  fillSignInCredentials,
  submitSignIn,
  openAdminDashboardFromHome,
  openAdminSection,
  ensureAdminMenuSectionsVisible,
  mainTableBodyForHeading,
  createOrUpdatePreset,
  createOrUpdateTariffByTitle,
  createUserWithTariff,
  assignTariffToUser,
  clickAndAcceptConfirm,
  shot,
} = require('../helpers');

test('admin area full smoke with CRUD checks', async ({ page }, testInfo) => {
  // Configure admin credentials used in this test and target uploaded video name
  const { adminEmail, adminPassword } = getAdminCredentials();
  const uploadedVideoName = '2022_10_04_Two_Maxes-02';

  // Step 1 — Navigate to home and sign in as admin
  await openHome(page);
  await openSignIn(page);
  await fillSignInCredentials(page, adminEmail, adminPassword);
  await submitSignIn(page);

  await expect(page.getByRole('link', { name: 'Admin', exact: true })).toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, '01-home-admin-link.png');

  // Step 2 — Open the Admin area and ensure the Users section is visible
  await openAdminDashboardFromHome(page);
  await expect(page.getByRole('heading', { name: 'Users' }).first()).toBeVisible({ timeout: UI_TIMEOUT });
  await ensureAdminMenuSectionsVisible(page);

  await expect(page.locator('a.action-new')).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(page.locator('a.action-edit').first()).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(page.locator('a.action-detail').first()).toBeVisible({ timeout: UI_TIMEOUT });
  const usersTbody = mainTableBodyForHeading(page, 'Users');
  await expect(usersTbody).toContainText('oleg@milantiev.com', { timeout: UI_TIMEOUT });
  await shot(page, testInfo, '02-admin-users-and-menu.png');

  // Step 3 — Ensure required Presets exist (create or update if missing)
  await createOrUpdatePreset(page, {
    title: '180p',
    width: 320,
    height: 180,
    codec: 'h264',
    bitrate: 1.1,
  }, testInfo);
  await createOrUpdatePreset(page, {
    title: 'FHD',
    width: 1920,
    height: 1280,
    codec: 'h264',
    bitrate: 5.0,
  }, testInfo);
  await shot(page, testInfo, '03-presets-created.png');

  // Step 4 — Ensure Tariffs exist and configure them; then assign a tariff to the admin user
  await createOrUpdateTariffByTitle(page, 'Free', 60, 1, testInfo, '07-tariff-free-initial.png');
  await createOrUpdateTariffByTitle(page, 'Free', 3600, 1, testInfo, '07b-tariff-free-updated-to-hour.png');
  await createOrUpdateTariffByTitle(page, 'Premium', 0, 2, testInfo, '07c-tariff-premium-present.png');
  await shot(page, testInfo, '04-tarifs-created.png');

  // Step 5 — Create a test user with email test@test.com, password 'test', ROLE_USER and Free tariff
  await createUserWithTariff(page, 'test@test.com', 'test', 'Free');
  await shot(page, testInfo, '05-test-user-created.png');
  await assignTariffToUser(page, adminEmail, 'Free', testInfo);
  await shot(page, testInfo, '05b-admin-user-free-tariff.png');

  // Step 6 - go to Tasks and mark the first task deleted, then verify UI updates.
  /*
  TODO тут ещё нет тасков !!!

  await openAdminSection(page, 'Tasks', '/admin/task');
  const tasksTbodyPre = mainTableBodyForHeading(page, 'Tasks');
  const tasksRows = tasksTbodyPre.locator('tr');
  const rowsCount = await tasksRows.count();
  if (rowsCount === 0) {
    // No tasks present — capture screenshot and continue
    await shot(page, testInfo, '02-no-tasks-present.png');
  }
  // Click 'Mark deleted' on the first task that exposes this action and accept confirmation when found
  let markDeletedPerformed = false;
  for (let i = 0; i < rowsCount; i += 1) {
    const row = tasksRows.nth(i);
    const link = row.getByRole('link', { name: 'Mark deleted' }).first();
    if ((await link.count()) > 0) {
      // found a row with the action
      await expect(link).toBeVisible({ timeout: UI_TIMEOUT });
      await clickAndAcceptConfirmDialog(page, link);
      markDeletedPerformed = true;
      break;
    }
  }

  if (markDeletedPerformed) {
    // Re-open Tasks index to refresh list and verify one of the rows shows deleted styling where applicable
    await openAdminSection(page, 'Tasks', '/admin/task');
    const tasksTbodyAfterTaskDelete = mainTableBodyForHeading(page, 'Tasks');
    const firstTaskRowAfter = tasksTbodyAfterTaskDelete.locator('tr').first();
    if ((await firstTaskRowAfter.locator('td.video-title-deleted').count()) > 0) {
      await expect(firstTaskRowAfter.locator('td.video-title-deleted')).toHaveCount(1, { timeout: UI_TIMEOUT });
    }
  } else {
    // no row had the 'Mark deleted' action — capture screenshot and continue
    await shot(page, testInfo, '02-tasks-no-mark-deleted-action.png');
  }
   */

  // Step 6 — Verify uploaded Videos are listed and details page is available (no create action)
  await openAdminSection(page, 'Videos', '/admin/video');
  await expect(page.locator('a.action-new')).toHaveCount(0, { timeout: UI_TIMEOUT });
  const videosTbody = mainTableBodyForHeading(page, 'Videos');
  await expect(videosTbody).toContainText(uploadedVideoName, { timeout: UI_TIMEOUT });
  await expect(videosTbody.locator('a.action-detail').first()).toBeVisible({ timeout: UI_TIMEOUT });
  await shot(page, testInfo, '06-admin-videos-uploaded-file.png');

  // Step 7 - Mark video as deleted and verify UI updates in Videos
  const videoRow = videosTbody.locator('tr', { hasText: uploadedVideoName }).first();
  await expect(videoRow).toBeVisible({ timeout: UI_TIMEOUT });
  const markDeletedButton = videoRow.getByRole('button', { name: /Mark deleted$/ }).first();
  await expect(markDeletedButton).toBeVisible({ timeout: UI_TIMEOUT });
  await clickAndAcceptConfirm(page, markDeletedButton);
  // Re-open Videos index to refresh and verify video row is strikethrough and action removed
  await openAdminSection(page, 'Videos', '/admin/video');
  const videosTbodyAfter = mainTableBodyForHeading(page, 'Videos');
  const videoRowAfter = videosTbodyAfter.locator('tr[data-deleted-row="1"]', { hasText: uploadedVideoName }).first();
  await expect(videoRowAfter).toHaveCount(1, { timeout: UI_TIMEOUT });
  await expect(videoRowAfter.getByRole('button', { name: /Mark deleted$/ })).toHaveCount(0, { timeout: UI_TIMEOUT });
  await shot(page, testInfo, '07-admin-video-deleted.png');

  // Step 8 — Verify Tasks section is read-only (no new/edit/delete actions allowed)
  await openAdminSection(page, 'Tasks', '/admin/task');
  await expect(page.locator('a.action-new')).toHaveCount(0, { timeout: UI_TIMEOUT });

  // TODO ещё нет edit
  await expect(page.locator('a.action-edit')).toHaveCount(0, { timeout: UI_TIMEOUT });
  await expect(page.locator('a.action-delete')).toHaveCount(0, { timeout: UI_TIMEOUT });
  await shot(page, testInfo, '08-admin-tasks-crud-constraints.png');

  // Step 9 - ensure all tasks are marked deleted (strikethrough) by Video deletion. No 'Mark deleted' actions remain
  /*
  // TODO ещё нет тасков
  await openAdminSection(page, 'Tasks', '/admin/task');
  const tasksTbodyFinal = mainTableBodyForHeading(page, 'Tasks');
  await expect.poll(async () => tasksTbodyFinal.locator('tr').count(), { timeout: UI_TIMEOUT }).toBeGreaterThan(0);
  const taskRows = tasksTbodyFinal.locator('tr');
  const taskCount = await taskRows.count();
  // Ensure there are no remaining 'Mark deleted' actions on any task rows.
  for (let i = 0; i < taskCount; i += 1) {
    const r = taskRows.nth(i);
    await expect(r.getByRole('link', { name: 'Mark deleted' })).toHaveCount(0, { timeout: UI_TIMEOUT });
  }
  await shot(page, testInfo, '04-admin-tasks-all-deleted.png');
   */

  // Step 10 — Verify Logs view is read-only and filtering controls are present
  await openAdminSection(page, 'Logs', '/admin/log');
  await expect(page.locator('a.action-new')).toHaveCount(0, { timeout: UI_TIMEOUT });
  await expect(page.locator('a.action-edit')).toHaveCount(0, { timeout: UI_TIMEOUT });
  const filtersAction = page
    .locator('a.action-filters, button.action-filters, a.action-filters-button, button.action-filters-button')
    .or(page.getByRole('button', { name: /filter/i }))
    .or(page.getByRole('link', { name: /filter/i }))
    .first();
  await expect(filtersAction).toBeVisible({ timeout: UI_TIMEOUT });
  const logsTbody = mainTableBodyForHeading(page, 'Logs');
  await expect.poll(async () => logsTbody.locator('tr').count(), { timeout: UI_TIMEOUT }).toBeGreaterThan(0);
  await shot(page, testInfo, '10-admin-logs-readonly-with-filters.png');

  // Step 11 — Return to the site home, verify UI and sign out
  await page.goto('/', { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
  await expect(page.getByRole('button', { name: 'Upload' })).toBeVisible({ timeout: UI_TIMEOUT });
  await expect(page.getByRole('link', { name: 'Sign out' })).toBeVisible({ timeout: UI_TIMEOUT });
  await page.getByRole('link', { name: 'Sign out' }).click({ timeout: UI_TIMEOUT });
  await expect(page.getByRole('link', { name: 'Sign in' })).toHaveCount(2, { timeout: UI_TIMEOUT });
  await shot(page, testInfo, '11-sign-out-after-admin-flow.png');
});
