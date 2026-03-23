const { test, expect } = require('@playwright/test');

let uiTimeout = 8000;
let navTimeout = 15000;

async function shot(page, testInfo, name) {
  await page.screenshot({ path: testInfo.outputPath(name), fullPage: true });
}

function videoRowByTitle(page, fileName) {
  return page.locator('#videosTable tbody tr', { hasText: fileName }).first();
}

function presetsTable(page) {
  const heading = page.getByRole('heading', { name: 'Presets' }).first();
  return heading.locator('xpath=following-sibling::table[1]');
}

function presetRow(page, presetTitle) {
  return presetsTable(page).locator('tbody tr', { hasText: presetTitle }).first();
}

function adminMenuLink(page, pathSuffix) {
  return page.locator(`a[href$="${pathSuffix}"]`).first();
}

function mainTableBodyForHeading(page, heading) {
  return page.locator('article', { has: page.getByRole('heading', { name: heading }) }).locator('table tbody').first();
}

async function openAdminSection(page, sectionName, pathSuffix) {
  await adminMenuLink(page, pathSuffix).click({ timeout: uiTimeout });
  await expect(page).toHaveURL(/\/admin/, { timeout: navTimeout });
  await expect(page.getByRole('heading', { name: sectionName }).first()).toBeVisible({ timeout: uiTimeout });
  await expect(mainTableBodyForHeading(page, sectionName)).toBeVisible({ timeout: uiTimeout });
}

async function submitCrudForm(page) {
  const createButton = page.getByRole('button', { name: 'Create', exact: true });
  if ((await createButton.count()) > 0) {
    await createButton.click({ timeout: uiTimeout });
    return;
  }

  const saveChangesButton = page.getByRole('button', { name: 'Save changes', exact: true });
  if ((await saveChangesButton.count()) > 0) {
    await saveChangesButton.click({ timeout: uiTimeout });
    return;
  }

  await page.getByRole('button', { name: 'Update', exact: true }).click({ timeout: uiTimeout });
}

async function assignTariffToUser(page, userEmail, tariffTitle) {
  await openAdminSection(page, 'Users', '/admin/user');

  const usersTbody = mainTableBodyForHeading(page, 'Users');
  const userRow = usersTbody.locator('tr', { hasText: userEmail }).first();
  await expect(userRow).toBeVisible({ timeout: uiTimeout });

  await userRow.locator('a.action-edit').click({ timeout: uiTimeout });

  const tariffSelect = page.locator('select[name$="[tariff]"]').first();
  if ((await tariffSelect.count()) > 0) {
    await tariffSelect.selectOption({ label: tariffTitle });
  } else {
    const tariffInput = page.getByLabel('Tariff').first();
    await tariffInput.click();
    await tariffInput.fill(tariffTitle);
    await page.keyboard.press('Enter');
  }

  await submitCrudForm(page);
  await expect(mainTableBodyForHeading(page, 'Users').locator('tr', { hasText: userEmail }).first()).toContainText(tariffTitle, {
    timeout: uiTimeout,
  });
}

async function readPresetTaskState(page, presetTitle) {
  const row = presetRow(page, presetTitle);
  await expect(row).toBeVisible({ timeout: uiTimeout });

  const status = (await row.locator('td').nth(1).innerText()).trim();
  const progressText = (await row.locator('td').nth(2).innerText()).trim();
  const progressMatch = progressText.match(/(\d+)\s*%/);
  const progress = progressMatch ? Number(progressMatch[1]) : -1;

  return { status, progress };
}

async function waitForVideoDetailsVisible(page) {
  await expect(page.getByRole('heading', { name: 'Video Details' })).toBeVisible({ timeout: uiTimeout });
  await expect(page.getByRole('heading', { name: 'Presets' })).toBeVisible({ timeout: uiTimeout });
  await expect(presetsTable(page)).toBeVisible({ timeout: uiTimeout });
}

async function reloadDetails(page) {
  await page.reload({ waitUntil: 'domcontentloaded', timeout: navTimeout });
  await waitForVideoDetailsVisible(page);
}

test('task state flow with 4k preset: progress, cancel, restart, complete', async ({ page }, testInfo) => {
  // Local timeouts for this long-flow test only.
  page.setDefaultTimeout(uiTimeout);
  page.setDefaultNavigationTimeout(navTimeout);

  const adminEmail = process.env.ADMIN_EMAIL || 'oleg@milantiev.com';
  const adminPassword = process.env.ADMIN_PASSWORD || 'admin';
  const uploadedVideoName = '2022_10_04_Two_Maxes.mp4';
  const presetTitle = '4k UHD';

  await page.goto('/', { waitUntil: 'domcontentloaded', timeout: navTimeout });
  await page.getByRole('link', { name: 'Sign in' }).last().click({ timeout: uiTimeout });
  await page.locator('#inputEmail').fill(adminEmail);
  await page.locator('#inputPassword').fill(adminPassword);
  await page.getByRole('button', { name: 'Sign in' }).click({ timeout: uiTimeout });
  await expect(page.getByRole('button', { name: 'Videos' })).toBeVisible({ timeout: uiTimeout });
  await shot(page, testInfo, '01-login-success.png');

  // Ensure two quick transcodes are allowed in this scenario.
  await expect(page.getByRole('link', { name: 'Admin', exact: true })).toBeVisible({ timeout: uiTimeout });
  await page.getByRole('link', { name: 'Admin', exact: true }).click({ timeout: uiTimeout });
  await assignTariffToUser(page, adminEmail, 'Premium');
  await shot(page, testInfo, '01b-admin-premium-tariff-assigned.png');

  // Re-login to refresh security token context after tariff update.
  await page.goto('/logout', { waitUntil: 'domcontentloaded', timeout: navTimeout });
  await expect(page.getByRole('link', { name: 'Sign in' }).first()).toBeVisible({ timeout: uiTimeout });
  await page.getByRole('link', { name: 'Sign in' }).last().click({ timeout: uiTimeout });
  await page.locator('#inputEmail').fill(adminEmail);
  await page.locator('#inputPassword').fill(adminPassword);
  await page.getByRole('button', { name: 'Sign in' }).click({ timeout: uiTimeout });
  await expect(page.getByRole('button', { name: 'Videos' })).toBeVisible({ timeout: uiTimeout });
  await page.goto('/?tab=videos', { waitUntil: 'domcontentloaded', timeout: navTimeout });

  await page.getByRole('button', { name: 'Videos' }).click({ timeout: uiTimeout });
  await expect(page.locator('#videosTable')).toBeVisible({ timeout: uiTimeout });

  const videoRow = videoRowByTitle(page, uploadedVideoName);
  await expect(videoRow).toBeVisible({ timeout: uiTimeout });
  await videoRow.click({ timeout: uiTimeout });

  await waitForVideoDetailsVisible(page);
  await expect(presetRow(page, presetTitle)).toBeVisible({ timeout: uiTimeout });
  await shot(page, testInfo, '02-video-details-with-4k-preset.png');

  const startButton = presetRow(page, presetTitle).getByRole('button', { name: 'Transcode' });
  await expect(startButton).toBeVisible({ timeout: uiTimeout });
  await startButton.click({ timeout: uiTimeout });

  await expect
    .poll(async () => (await readPresetTaskState(page, presetTitle)).status, {
      timeout: uiTimeout,
      intervals: [1000, 2000, 5000],
    })
    .toMatch(/PENDING|PROCESSING|COMPLETED/);
  await shot(page, testInfo, '03-transcode-started.png');

  let prevProgress = -1;
  let sawProgressIncrease = false;
  let cancellationSent = false;

  for (let attempt = 1; attempt <= 10; attempt += 1) {
    const state = await readPresetTaskState(page, presetTitle);

    if (prevProgress >= 0 && state.progress > prevProgress) {
      sawProgressIncrease = true;
    }
    if (state.progress > prevProgress) {
      prevProgress = state.progress;
    }

    if (state.status === 'COMPLETED') {
      throw new Error('4k task completed before cancellation was sent; flow cannot validate cancel-in-processing.');
    }

    if (state.status === 'PROCESSING') {
      const cancelButton = presetRow(page, presetTitle).getByRole('button', { name: 'Cancel' });
      await expect(cancelButton).toBeVisible({ timeout: uiTimeout });
      await cancelButton.click({ timeout: uiTimeout });
      cancellationSent = true;
      break;
    }

    // wait for realtime update (worker emits every ~5s)
    await page.waitForTimeout(6000);
  }

  expect(cancellationSent).toBe(true);
  await shot(page, testInfo, '04-cancel-request-sent-after-progress-growth.png');

  let cancelled = false;
  for (let attempt = 1; attempt <= 60; attempt += 1) {
    const state = await readPresetTaskState(page, presetTitle);
    if (state.status === 'CANCELLED') {
      cancelled = true;
      break;
    }

    // wait for realtime update (worker emits every ~5s)
    await page.waitForTimeout(6000);
  }

  expect(cancelled).toBe(true);
  const cancelledRow = presetRow(page, presetTitle);
  await expect(cancelledRow.getByRole('link', { name: 'Download' })).toHaveCount(0);
  await expect(cancelledRow.getByRole('button', { name: 'Transcode' })).toBeVisible({ timeout: uiTimeout });
  await shot(page, testInfo, '05-task-cancelled.png');

  await cancelledRow.getByRole('button', { name: 'Transcode' }).click({ timeout: uiTimeout });

  await expect
    .poll(async () => (await readPresetTaskState(page, presetTitle)).status, {
      timeout: 60000,
      intervals: [1000, 2000, 5000],
    })
    .toMatch(/PENDING|PROCESSING|COMPLETED/);

  let prevRestartProgress = -1;
  let sawRestartProgressIncrease = false;
  let completed = false;

  for (let attempt = 1; attempt <= 10; attempt += 1) {
    const state = await readPresetTaskState(page, presetTitle);

    if (prevRestartProgress >= 0 && state.progress > prevRestartProgress) {
      sawRestartProgressIncrease = true;
    }
    if (state.progress > prevRestartProgress) {
      prevRestartProgress = state.progress;
    }

    if (state.status === 'COMPLETED') {
      completed = true;
      break;
    }

    // wait for realtime update (worker emits every ~5s)
    await page.waitForTimeout(6000);
  }

  expect(completed).toBe(true);
  expect(sawRestartProgressIncrease).toBe(true);

  const completedRow = presetRow(page, presetTitle);
  await expect(completedRow.getByRole('link', { name: 'Download' })).toBeVisible({ timeout: uiTimeout });
  await shot(page, testInfo, '06-restart-completed-with-download.png');

  await expect(page.getByRole('link', { name: 'Sign out' })).toBeVisible({ timeout: uiTimeout });
  await page.getByRole('link', { name: 'Sign out' }).click({ timeout: uiTimeout });
  await expect(page.getByRole('link', { name: 'Sign in' })).toHaveCount(2, { timeout: uiTimeout });
  await shot(page, testInfo, '07-sign-out.png');
});

