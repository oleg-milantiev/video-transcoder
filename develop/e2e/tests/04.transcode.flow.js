const { test, expect } = require('@playwright/test');
const { attachConsoleCapture } = require('../consoleCapture');
    const {
    UI_TIMEOUT,
    NAV_TIMEOUT,
    loginAsTest,
    uploadFixtureAsName,
    openVideosTab,
    expectVideosTableVisible,
    videoRowByTitle,
    waitForVideoDetailsVisible,
    presetBlock,
    presetRow,
    readPresetTaskState,
    expectFlashPopupTitle,
    clickAndAcceptConfirm,
    clickDownloadAndVerifyMp4,
    tasksTable,
    logoutToPublic,
    shot, renameVideoFromDetails, expectVideoDetailsTitle, expectDownloadFilename,
    switchToVideoTab,
} = require('../helpers');

test('transcode flow from video details to downloadable mp4', async ({ page }, testInfo) => {
    // start console capture for this test
    const capture = attachConsoleCapture(page, testInfo, { maxBodyChars: 4000 });
    await capture.start();
    const sourceVideoFileName = '2022_10_04_Two_Maxes.mp4';
    const uploadedVideoName = '2022_10_04_Two_Maxes-04.mp4';
    const baseFileName = uploadedVideoName.substring(0, uploadedVideoName.lastIndexOf('.'));
    const renamedBaseFileName = `${baseFileName}-renamed`;
    const presetTitle = 'Standard video Quality';
    let downloadedMp4Url = '';

    try {
    // 1) Home + login
        await loginAsTest(page);
        await expect(page.getByRole('button', { name: 'Videos' })).toBeVisible({ timeout: UI_TIMEOUT });
        await shot(page, testInfo, '01-login-success.png');

    // 2) Upload the same source video under a -04 suffix and open Videos tab
        await uploadFixtureAsName(page, sourceVideoFileName, uploadedVideoName);
        await shot(page, testInfo, '01c-upload-with-04-name.png');
        await openVideosTab(page);
        await expectVideosTableVisible(page);
        await shot(page, testInfo, '02-videos-tab-open.png');

    // 3) Open previously uploaded video card
        const row = videoRowByTitle(page, baseFileName);
        await expect(row).toBeVisible({ timeout: NAV_TIMEOUT });
        await row.click({ timeout: UI_TIMEOUT });

    // 4) Verify preset block exists (transcoding section)
        await waitForVideoDetailsVisible(page);
        await switchToVideoTab(page, 'transcode');
        const blockPreset = presetBlock(page, presetTitle);
        await expect(blockPreset).toBeVisible({ timeout: UI_TIMEOUT });
        await shot(page, testInfo, '03-video-details-with-presets.png');

    // 5) Click the 2 (HD = 720p) button for fast, but not instant transcoding
        const transcodeButton = blockPreset.locator('button.btn-outline-primary:not([disabled])').nth(1);
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
                { timeout: NAV_TIMEOUT, intervals: [1000, 2000, 2000] }
            )
            .toMatch(/PENDING|PROCESSING|COMPLETED/);

    // 7) Poll every 1s, validate progress increase, wait until COMPLETED
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

            await page.waitForTimeout(1000);
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

    // Before rename - check download filename matches video title + codec + resolution
        const expectedFilenameBeforeRename = `${baseFileName}-aac-h264-720p.mp4`;
        await switchToVideoTab(page, 'tasks');
        await expectDownloadFilename(page, expectedFilenameBeforeRename);
    // Rename video from details page
        await renameVideoFromDetails(page, renamedBaseFileName);
        await expectVideoDetailsTitle(page, renamedBaseFileName);

    // After rename - check download filename matches new video title with preset
        const expectedFilenameAfterRename = `${renamedBaseFileName}-aac-h264-720p.mp4`;
        await switchToVideoTab(page, 'tasks');
        await expectDownloadFilename(page, expectedFilenameAfterRename);

    // 9) Go back to videos list, delete video, verify deleted state in list
        await page.goto('/?tab=videos', { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
        await page.getByRole('button', { name: 'Videos' }).click({ timeout: UI_TIMEOUT });
        await expect(page.locator('#videosTable')).toBeVisible({ timeout: UI_TIMEOUT });

        const listRow = videoRowByTitle(page, renamedBaseFileName);
        await expect(listRow).toBeVisible({ timeout: NAV_TIMEOUT });

        await clickAndAcceptConfirm(
            page,
            listRow.getByRole('button', { name: 'Delete' }),
            'Delete this video?'
        );

        await expect.poll(async () => listRow.locator('td.video-title-deleted').count(), {
            timeout: NAV_TIMEOUT,
            intervals: [1000, 2000, 5000],
        }).toBeGreaterThan(0);

        await expect(listRow.locator('td.video-title-deleted')).toContainText(renamedBaseFileName, { timeout: UI_TIMEOUT });
        // Deleted row must not allow a real delete action anymore.
        await expect(listRow.locator('button:not([disabled])', { hasText: 'Delete' })).toHaveCount(0);
        await shot(page, testInfo, '07-video-marked-deleted-in-list.png');

    // 10) Open deleted video details and verify deleted UI state
        await listRow.click({ timeout: UI_TIMEOUT });
        await waitForVideoDetailsVisible(page);
        await expect(page.getByText('This video has been deleted')).toBeVisible({ timeout: UI_TIMEOUT });
        // Verify the title is shown in the Info tab title field
        const deletedTitleSpan = page.locator('dt', { hasText: 'Title' }).first()
            .locator('xpath=following-sibling::dd[1]//span[1]');
        await expect(deletedTitleSpan).toContainText(renamedBaseFileName, { timeout: UI_TIMEOUT });

        await switchToVideoTab(page, 'tasks');
        const tasksBody = tasksTable(page).locator('tbody').first();
        await expect(tasksBody).toContainText('DELETED', { timeout: UI_TIMEOUT });
        await expect(tasksBody.getByRole('button')).toHaveCount(0);
        await expect(tasksBody.getByRole('link')).toHaveCount(0);
        await shot(page, testInfo, '08-video-details-deleted-state.png');

    // 11) Verify direct mp4 URL from earlier download now returns 404
        expect(downloadedMp4Url).toMatch(/\.mp4(?:$|\?)/i);
        const deletedFileResponse = await page.request.get(downloadedMp4Url, {
            failOnStatusCode: false,
            timeout: NAV_TIMEOUT,
        });
        expect(deletedFileResponse.status()).toBe(404);

    // 12) Sign out
        await logoutToPublic(page);
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
