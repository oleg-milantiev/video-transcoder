const { test, expect } = require('@playwright/test');

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

async function readPresetTaskState(page, presetTitle) {
    const row = presetRow(page, presetTitle);
    await expect(row).toBeVisible();

    const status = (await row.locator('td').nth(1).innerText()).trim();
    const progressText = (await row.locator('td').nth(2).innerText()).trim();
    const progressMatch = progressText.match(/(\d+)\s*%/);
    const progress = progressMatch ? Number(progressMatch[1]) : -1;

    return { status, progress };
}

async function waitForVideoDetailsVisible(page) {
    await expect(page.getByRole('heading', { name: 'Video Details' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Presets' })).toBeVisible();
    await expect(presetsTable(page)).toBeVisible();
}

async function clickDownloadAndVerifyMp4(page, row) {
    const downloadLink = row.getByRole('link', { name: 'Download' });
    await expect(downloadLink).toBeVisible();

    const href = await downloadLink.getAttribute('href');
    if (!href) {
        throw new Error('Download link href is empty');
    }

    const downloadPromise = page.waitForEvent('download', { timeout: 15000 }).catch(() => null);
    await downloadLink.click();

    const download = await downloadPromise;

    const downloadUrl = new URL(href, page.url()).toString();
    const response = await page.request.get(downloadUrl, {
        failOnStatusCode: false,
        maxRedirects: 0,
    });

    expect(response.status()).toBeLessThan(400);
    const location = (response.headers().location || '').toLowerCase();
    if (location) {
        expect(location).toContain('.mp4');
    }

    if (download) {
        const failure = await download.failure();
        // Some browsers report "canceled" for redirect-to-storage download while response is successful.
        expect(failure === null || failure === 'canceled').toBeTruthy();
        expect(download.suggestedFilename().toLowerCase()).toMatch(/\.mp4$/);
        return;
    }
}

test('transcode flow from video details to downloadable mp4', async ({ page }, testInfo) => {
    test.setTimeout(720000);

    const adminEmail = process.env.ADMIN_EMAIL || 'oleg@milantiev.com';
    const adminPassword = process.env.ADMIN_PASSWORD || 'admin';
    const uploadedVideoName = '2022_10_04_Two_Maxes.mp4';
    const presetTitle = '180p';

    // 1) Home + login
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await page.getByRole('link', { name: 'Sign in' }).last().click();
    await page.locator('#inputEmail').fill(adminEmail);
    await page.locator('#inputPassword').fill(adminPassword);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page.getByRole('button', { name: 'Videos' })).toBeVisible();
    await shot(page, testInfo, '01-login-success.png');


    // 2) Open Videos tab
    await page.getByRole('button', { name: 'Videos' }).click();
    await expect(page.locator('#videosTable')).toBeVisible();
    await shot(page, testInfo, '02-videos-tab-open.png');

    // 3) Open previously uploaded video card
    const row = videoRowByTitle(page, uploadedVideoName);
    await expect(row).toBeVisible({ timeout: 20000 });
    await row.click();

    // 4) Verify presets table + preset exists
    await waitForVideoDetailsVisible(page);
    const row180p = presetRow(page, presetTitle);
    await expect(row180p).toBeVisible();
    await shot(page, testInfo, '03-video-details-with-presets.png');

    // 5) Verify and click Transcode button
    const transcodeButton = row180p.getByRole('button', { name: 'Transcode' });
    await expect(transcodeButton).toBeVisible();
    await transcodeButton.click();
    await shot(page, testInfo, '04-transcode-clicked.png');

    // 6) Verify task appears in running state
    await expect
        .poll(
            async () => {
                const state = await readPresetTaskState(page, presetTitle);
                return state.status;
            },
            { timeout: 30000, intervals: [1000, 2000, 5000] }
        )
        .toMatch(/PENDING|PROCESSING|COMPLETED/);

    // 7) Poll every 5s, validate progress increase, wait until COMPLETED
    let prevProgress = -1;
    let sawProgressIncrease = false;
    let completed = false;

    for (let attempt = 1; attempt <= 90; attempt += 1) {
        const state = await readPresetTaskState(page, presetTitle);

        if (prevProgress >= 0 && state.progress > prevProgress) {
            sawProgressIncrease = true;
        }
        if (state.progress > prevProgress) {
            prevProgress = state.progress;
        }

        if (state.status === 'COMPLETED') {
            completed = true;
            break;
        }

        await page.waitForTimeout(5000);
        await page.reload();
        await waitForVideoDetailsVisible(page);
    }

    expect(completed).toBe(true);
    expect(sawProgressIncrease).toBe(true);
    await shot(page, testInfo, '05-task-completed.png');

    // 8) Verify Download button and download without errors
    const completedRow = presetRow(page, presetTitle);
    await expect(completedRow.getByRole('link', { name: 'Download' })).toBeVisible();
    await clickDownloadAndVerifyMp4(page, completedRow);
    await shot(page, testInfo, '06-download-verified.png');

    // 9) Sign out
    await expect(page.getByRole('link', { name: 'Sign out' })).toBeVisible();
    await page.getByRole('link', { name: 'Sign out' }).click();
    await expect(page.getByRole('link', { name: 'Sign in' })).toHaveCount(2);
    await shot(page, testInfo, '07-sign-out.png');
});
