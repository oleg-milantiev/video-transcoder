const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');
const { attachConsoleCapture } = require('../consoleCapture');

const UI_TIMEOUT = 8000;
const NAV_TIMEOUT = 15000;

async function shot(page, testInfo, name) {
    await page.screenshot({ path: testInfo.outputPath(name), fullPage: true, timeout: UI_TIMEOUT });
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
    await expect(row).toBeVisible({ timeout: UI_TIMEOUT });

    const status = (await row.locator('td').nth(1).innerText({ timeout: UI_TIMEOUT })).trim();
    const progressText = (await row.locator('td').nth(2).innerText({ timeout: UI_TIMEOUT })).trim();
    const progressMatch = progressText.match(/(\d+)\s*%/);
    const progress = progressMatch ? Number(progressMatch[1]) : -1;

    return { status, progress };
}

async function waitForVideoDetailsVisible(page) {
    await expect(page.getByRole('heading', { name: 'Video Details' })).toBeVisible({ timeout: UI_TIMEOUT });
    await expect(page.getByRole('heading', { name: 'Presets' })).toBeVisible({ timeout: UI_TIMEOUT });
    await expect(presetsTable(page)).toBeVisible({ timeout: UI_TIMEOUT });
}

async function expectFlashPopupTitle(page, titleText, timeout = 30000) {
    const toastTitle = page.locator('.app-flash-toast .app-flash-title', { hasText: titleText }).last();
    await expect(toastTitle).toBeVisible({ timeout });
}

async function clickAndAcceptConfirmDialog(page, clickableLocator, expectedMessagePart) {
    const dialogPromise = page.waitForEvent('dialog', { timeout: UI_TIMEOUT }).then(async (dialog) => {
        const message = dialog.message();
        await dialog.accept();
        return message;
    });

    await clickableLocator.click({ timeout: UI_TIMEOUT });
    const dialogMessage = await dialogPromise;
    expect(dialogMessage).toContain(expectedMessagePart);
}

async function clickDownloadAndVerifyMp4(page, row) {
    const downloadLink = row.getByRole('link', { name: 'Download' });
    await expect(downloadLink).toBeVisible({ timeout: UI_TIMEOUT });

    const href = await downloadLink.getAttribute('href', { timeout: UI_TIMEOUT });
    if (!href) {
        throw new Error('Download link href is empty');
    }

    const downloadPromise = page.waitForEvent('download', { timeout: 15000 }).catch(() => null);
    await downloadLink.click({ timeout: UI_TIMEOUT });

    const download = await downloadPromise;

    const downloadUrl = new URL(href, page.url()).toString();
    const response = await page.request.get(downloadUrl, {
        failOnStatusCode: false,
        maxRedirects: 0,
        timeout: NAV_TIMEOUT,
    });

    expect(response.status()).toBeLessThan(400);
    const location = (response.headers().location || '').toLowerCase();
    const locationRaw = response.headers().location || '';
    let resolvedMp4Url = '';
    if (location) {
        expect(location).toContain('.mp4');
        resolvedMp4Url = new URL(locationRaw, downloadUrl).toString();
    } else if (downloadUrl.toLowerCase().includes('.mp4')) {
        // Fallback for direct-file endpoints without redirect.
        resolvedMp4Url = downloadUrl;
    }

    if (download) {
        const failure = await download.failure();
        // Some browsers report "canceled" for redirect-to-storage download while response is successful.
        expect(failure === null || failure === 'canceled').toBeTruthy();
        expect(download.suggestedFilename().toLowerCase()).toMatch(/\.mp4$/);
    }

    if (!resolvedMp4Url) {
        throw new Error('Could not resolve final mp4 URL from download response');
    }

    return resolvedMp4Url;
}

async function uploadVideoWithCustomName(page, testInfo, sourceFileName, uploadAsName) {
    const uploadFilePath = path.join('/work/e2e', sourceFileName);
    const fileBuffer = fs.readFileSync(uploadFilePath);

    await page.getByRole('button', { name: 'Upload' }).click({ timeout: UI_TIMEOUT });
    await expect(page.locator('#drag-drop-area .uppy-Dashboard')).toBeVisible({ timeout: 30000 });

    const fileChooserPromise = page.waitForEvent('filechooser');
    await page.locator('#drag-drop-area .uppy-Dashboard-browse').click({ timeout: UI_TIMEOUT });
    const fileChooser = await fileChooserPromise;
    await fileChooser.setFiles({
        name: uploadAsName,
        mimeType: 'video/mp4',
        buffer: fileBuffer,
    });

    await expect(page.locator('#drag-drop-area .uppy-StatusBar-content[role="status"][title="Complete"]')).toBeVisible({ timeout: 30000 });
    await shot(page, testInfo, '01c-upload-with-04-name.png');

    await page.getByRole('button', { name: 'Videos' }).click({ timeout: UI_TIMEOUT });
    await expect(page.locator('#videosTable')).toBeVisible({ timeout: NAV_TIMEOUT });
}

test('transcode flow from video details to downloadable mp4', async ({ page }, testInfo) => {
    // start console capture for this test
    const capture = attachConsoleCapture(page, testInfo, { maxBodyChars: 4000 });
    await capture.start();
    const adminEmail = process.env.ADMIN_EMAIL || 'oleg@milantiev.com';
    const adminPassword = process.env.ADMIN_PASSWORD || 'admin';
    const sourceVideoFileName = '2022_10_04_Two_Maxes.mp4';
    const uploadedVideoName = '2022_10_04_Two_Maxes-04.mp4';
    const presetTitle = '180p';
    let downloadedMp4Url = '';

    try {
        // 1) Home + login
        await page.goto('/', { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
        await page.getByRole('link', { name: 'Sign in' }).last().click({ timeout: UI_TIMEOUT });
        await page.locator('#inputEmail').fill(adminEmail, { timeout: UI_TIMEOUT });
        await page.locator('#inputPassword').fill(adminPassword, { timeout: UI_TIMEOUT });
        await page.getByRole('button', { name: 'Sign in' }).click({ timeout: UI_TIMEOUT });
        await expect(page.getByRole('button', { name: 'Videos' })).toBeVisible({ timeout: UI_TIMEOUT });
        await shot(page, testInfo, '01-login-success.png');

    // 2) Upload the same source video under a -04 suffix and open Videos tab
        await uploadVideoWithCustomName(page, testInfo, sourceVideoFileName, uploadedVideoName);
        await page.getByRole('button', { name: 'Videos' }).click({ timeout: UI_TIMEOUT });
        await expect(page.locator('#videosTable')).toBeVisible({ timeout: UI_TIMEOUT });
        await shot(page, testInfo, '02-videos-tab-open.png');

    // 3) Open previously uploaded video card
        const row = videoRowByTitle(page, uploadedVideoName);
        await expect(row).toBeVisible({ timeout: NAV_TIMEOUT });
        await row.click({ timeout: UI_TIMEOUT });

    // 4) Verify presets table + preset exists
        await waitForVideoDetailsVisible(page);
        const row180p = presetRow(page, presetTitle);
        await expect(row180p).toBeVisible({ timeout: UI_TIMEOUT });
        await shot(page, testInfo, '03-video-details-with-presets.png');

    // 5) Verify and click Transcode button
        const transcodeButton = row180p.getByRole('button', { name: 'Transcode' });
        await expect(transcodeButton).toBeVisible({ timeout: UI_TIMEOUT });
        await transcodeButton.click({ timeout: UI_TIMEOUT });
        await expectFlashPopupTitle(page, 'Transcoding started');
        await shot(page, testInfo, '04-transcode-clicked.png');

    // 6) Verify task appears in running state
        await expect
            .poll(
                async () => {
                    const state = await readPresetTaskState(page, presetTitle);
                    return state.status;
                },
                { timeout: NAV_TIMEOUT, intervals: [1000, 2000, 5000] }
            )
            .toMatch(/PENDING|PROCESSING|COMPLETED/);

    // 7) Poll every 5s, validate progress increase, wait until COMPLETED
        let prevProgress = -1;
        let sawProgressIncrease = false;
        let completed = false;

        for (let attempt = 1; attempt <= 10; attempt += 1) {
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

            // wait for realtime progress update (ffmpeg worker publishes every ~5s)
            await page.waitForTimeout(6000);
        }

        expect(completed).toBe(true);
        expect(sawProgressIncrease).toBe(true);
        await expectFlashPopupTitle(page, 'Transcoding completed');
        await shot(page, testInfo, '05-task-completed.png');

    // 8) Verify Download button and download without errors
        const completedRow = presetRow(page, presetTitle);
        await expect(completedRow.getByRole('link', { name: 'Download' })).toBeVisible({ timeout: UI_TIMEOUT });
        downloadedMp4Url = await clickDownloadAndVerifyMp4(page, completedRow);
        await shot(page, testInfo, '06-download-verified.png');

    // 9) Go back to videos list, delete video, verify deleted state in list
        await page.goto('/?tab=videos', { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
        await page.getByRole('button', { name: 'Videos' }).click({ timeout: UI_TIMEOUT });
        await expect(page.locator('#videosTable')).toBeVisible({ timeout: UI_TIMEOUT });

        const listRow = videoRowByTitle(page, uploadedVideoName);
        await expect(listRow).toBeVisible({ timeout: NAV_TIMEOUT });

        await clickAndAcceptConfirmDialog(
            page,
            listRow.getByRole('button', { name: 'Delete' }),
            'Delete this video?'
        );

        await expect.poll(async () => listRow.locator('td.video-title-deleted').count(), {
            timeout: NAV_TIMEOUT,
            intervals: [1000, 2000, 5000],
        }).toBeGreaterThan(0);

        await expect(listRow.locator('td.video-title-deleted')).toContainText(uploadedVideoName, { timeout: UI_TIMEOUT });
        // Deleted row must not allow a real delete action anymore.
        await expect(listRow.locator('button:not([disabled])', { hasText: 'Delete' })).toHaveCount(0);
        await shot(page, testInfo, '07-video-marked-deleted-in-list.png');

    // 10) Open deleted video details and verify deleted UI state
        await listRow.click({ timeout: UI_TIMEOUT });
        await waitForVideoDetailsVisible(page);
        await expect(page.getByText('This video has been deleted')).toBeVisible({ timeout: UI_TIMEOUT });
        await expect(page.locator('dd.video-title-deleted')).toContainText(uploadedVideoName, { timeout: UI_TIMEOUT });

        const presetsBody = presetsTable(page).locator('tbody').first();
        await expect(presetsBody).toContainText('DELETED', { timeout: UI_TIMEOUT });
        await expect(presetsBody.getByRole('button')).toHaveCount(0);
        await expect(presetsBody.getByRole('link')).toHaveCount(0);
        await shot(page, testInfo, '08-video-details-deleted-state.png');

    // 11) Verify direct mp4 URL from earlier download now returns 404
        expect(downloadedMp4Url).toMatch(/\.mp4(?:$|\?)/i);
        const deletedFileResponse = await page.request.get(downloadedMp4Url, {
            failOnStatusCode: false,
            timeout: NAV_TIMEOUT,
        });
        expect(deletedFileResponse.status()).toBe(404);

    // 12) Sign out
        await expect(page.getByRole('link', { name: 'Sign out' })).toBeVisible({ timeout: UI_TIMEOUT });
        await page.getByRole('link', { name: 'Sign out' }).click({ timeout: UI_TIMEOUT });
        await expect(page.getByRole('link', { name: 'Sign in' })).toHaveCount(2, { timeout: UI_TIMEOUT });
        await shot(page, testInfo, '09-sign-out.png');
    } finally {
        // collect SSE messages captured by probe and attach them
        try {
            const sseMessages = await page.evaluate(() => (window.__mercure_messages || []));
            await testInfo.attach('mercure-sse.json', {
                body: Buffer.from(JSON.stringify(sseMessages, null, 2), 'utf-8'),
                contentType: 'application/json'
            });
        } catch (e) {
            // ignore
        }

        // flush and attach console log even when the test fails early
        await capture.flushAndAttach();
    }
});
