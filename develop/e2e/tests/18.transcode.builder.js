/**
 * 18  transcode.builder
 *
 * - Login as test-18 (Free tariff)
 * - Upload one video
 * - Open video details → Transcode tab
 * - Verify builder UI state for each Goal preset:
 *   Social media, Max quality, Fast & compact, For PC, Archive, Custom
 */

const {test, expect} = require('@playwright/test');
const {
    UI_TIMEOUT,
    NAV_TIMEOUT,
    loginAs,
    logoutToPublic,
    uploadFixtureAs,
    openVideosTab,
    expectVideosTableVisible,
    activeVideoRowByTitle,
    waitForVideoDetailsVisible,
    waitForPosterAndMeta,
    switchToVideoTab,
    shot,
} = require('../helpers');

const EMAIL = 'test-18@test.com';
const PASSWORD = 'test-18';
const SRC = '2022_10_04_Two_Maxes.mp4';
const VIDEO_NAME = '2022_10_04_Two_Maxes-18.mp4';
const VIDEO_TITLE = '2022_10_04_Two_Maxes-18';

test('18  transcode.builder: goal presets UI for Free tariff', async ({page}, testInfo) => {
    // ── helpers ──────────────────────────────────────────────────────────────────

    /** Click a goal card by its label text in the Goals section */
    const clickGoal = async (label) => {
        const card = page.locator('div.fw-semibold', {hasText: label}).first();
        await expect(card).toBeVisible({timeout: UI_TIMEOUT});
        await card.click({timeout: UI_TIMEOUT});
    };

    /**
     * The three section cards in Builder mode (Quality / Resolution / Format).
     * Each is a div.bg-white.rounded-3.p-3.border that contains an h6 heading.
     */
    const qualityCard = () => page.locator('.bg-white.rounded-3', {has: page.locator('h6', {hasText: 'Quality'})}).first();
    const resolutionCard = () => page.locator('.bg-white.rounded-3', {has: page.locator('h6', {hasText: 'Resolution'})}).first();
    const formatCard = () => page.locator('.bg-white.rounded-3', {has: page.locator('h6', {hasText: 'Format'})}).first();

    /** Quality buttons inside the Quality section card */
    const superBtn = () => qualityCard().getByRole('button', {name: 'Super'});
    const goodBtn = () => qualityCard().getByRole('button', {name: 'Good'});
    const normalBtn = () => qualityCard().getByRole('button', {name: 'Normal'});

    /**
     * Resolution button in the builder – each button has a <span> "Xp" so we
     * can reliably filter by the exact text "720p", "1080p", …
     */
    const resBtn = (heightPx) =>
        resolutionCard().locator('button').filter({hasText: `${heightPx}p`}).first();

    /** Format buttons; text content is "MP4..." / "WebM..." */
    const mp4Btn = () => formatCard().locator('button').filter({hasText: /MP4/}).first();
    const webmBtn = () => formatCard().locator('button').filter({hasText: /WebM/}).first();

    /** The "Start transcoding" CTA button */
    const startBtn = () => page.locator('button', {hasText: 'Start transcoding'}).first();

    /** Assert that the result section shows a valid preset (badge visible, start enabled) */
    const assertPresetAvailable = async (badgeText) => {
        await expect(page.locator('.badge.bg-primary', {hasText: badgeText})).toBeVisible({timeout: UI_TIMEOUT});
        await expect(startBtn()).toBeEnabled({timeout: UI_TIMEOUT});
    };

    /** Assert that the result section shows "no preset" error and a tariff upgrade link */
    const assertNoPreset = async () => {
        await expect(
            page.locator('.text-danger', {hasText: 'No preset available for the selected quality and format.'}).first()
        ).toBeVisible({timeout: UI_TIMEOUT});
        await expect(
            page.getByRole('link', {name: 'Upgrade your Tariff'}).first()
        ).toBeVisible({timeout: UI_TIMEOUT});
        await expect(startBtn()).toBeDisabled({timeout: UI_TIMEOUT});
    };

    // ── Login ────────────────────────────────────────────────────────────────────
    await loginAs(page, EMAIL, PASSWORD);

    // ── Upload ───────────────────────────────────────────────────────────────────
    await uploadFixtureAs(page, SRC, VIDEO_NAME);
    await shot(page, testInfo, '18-01-uploaded.png');

    // ── Open video list and navigate to video details ────────────────────────────
    await openVideosTab(page);
    await expectVideosTableVisible(page);
    const videoRow = activeVideoRowByTitle(page, VIDEO_TITLE);
    await expect(videoRow).toBeVisible({timeout: NAV_TIMEOUT});
    await videoRow.click({timeout: UI_TIMEOUT});
    await waitForVideoDetailsVisible(page);
    await waitForPosterAndMeta(page, testInfo, '18-02-poster-meta');

    // ── Switch to Transcode tab ───────────────────────────────────────────────────
    await switchToVideoTab(page, 'transcode');

    // ═══════════════════════════════════════════════════════════════════════════════
    // Social media  (initial / default goal for Free tariff)
    // quality=normal  resolution=auto→720p  format=mp4
    // ═══════════════════════════════════════════════════════════════════════════════

    // Quality: Normal active; Super and Good disabled (no av1/mp4 or h265/mp4)
    await expect(normalBtn()).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(superBtn()).toBeDisabled({timeout: UI_TIMEOUT});
    await expect(goodBtn()).toBeDisabled({timeout: UI_TIMEOUT});

    // Resolution: 720p active (auto → closest to origin 720); 1080p and 480p enabled
    await expect(resBtn(720)).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(resBtn(1080)).toBeVisible({timeout: UI_TIMEOUT});
    await expect(resBtn(1080)).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(resBtn(480)).toBeVisible({timeout: UI_TIMEOUT});
    await expect(resBtn(480)).toBeEnabled({timeout: UI_TIMEOUT});

    // Format: MP4 active and enabled; WebM disabled with "Not available"
    await expect(mp4Btn()).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(mp4Btn()).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(webmBtn()).toBeDisabled({timeout: UI_TIMEOUT});
    await expect(webmBtn().locator('small')).toContainText('Not available', {timeout: UI_TIMEOUT});

    // Bottom block: Standard video Quality badge, Start transcoding enabled
    await assertPresetAvailable('Standard video Quality');

    await shot(page, testInfo, '18-03-social-media.png');

    // ── Click 1080p – bottom block should mention 1080p ──────────────────────────
    await resBtn(1080).click({timeout: UI_TIMEOUT});
    await expect(
        page.locator('h5').filter({hasText: '1080p'}).first()
    ).toBeVisible({timeout: UI_TIMEOUT});
    await assertPresetAvailable('Standard video Quality');
    await shot(page, testInfo, '18-04-resolution-1080p.png');

    // ── Click 480p – bottom block should mention 480p ────────────────────────────
    await resBtn(480).click({timeout: UI_TIMEOUT});
    await expect(
        page.locator('h5').filter({hasText: '480p'}).first()
    ).toBeVisible({timeout: UI_TIMEOUT});
    await assertPresetAvailable('Standard video Quality');
    await shot(page, testInfo, '18-05-resolution-480p.png');

    // ═══════════════════════════════════════════════════════════════════════════════
    // Max quality  (key: quality)
    // quality=super  resolution=auto→720p  format=webm
    // All quality/format buttons disabled (no av1 or vp9/vp8 presets in Free)
    // ═══════════════════════════════════════════════════════════════════════════════
    await clickGoal('Max quality');

    // Quality: Super active (btn-primary); Good and Normal disabled
    await expect(superBtn()).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(goodBtn()).toBeDisabled({timeout: UI_TIMEOUT});
    await expect(normalBtn()).toBeDisabled({timeout: UI_TIMEOUT});

    // Resolution: 720p active; 1080p and 480p available
    await expect(resBtn(720)).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(resBtn(1080)).toBeVisible({timeout: UI_TIMEOUT});
    await expect(resBtn(480)).toBeVisible({timeout: UI_TIMEOUT});

    // Format: WebM active but disabled; MP4 also disabled – both "Not available"
    await expect(webmBtn()).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(webmBtn()).toBeDisabled({timeout: UI_TIMEOUT});
    await expect(webmBtn().locator('small')).toContainText('Not available', {timeout: UI_TIMEOUT});
    await expect(mp4Btn()).toBeDisabled({timeout: UI_TIMEOUT});
    await expect(mp4Btn().locator('small')).toContainText('Not available', {timeout: UI_TIMEOUT});

    // Bottom block: no preset, upgrade link, start disabled
    await assertNoPreset();
    await shot(page, testInfo, '18-06-max-quality.png');

    // ═══════════════════════════════════════════════════════════════════════════════
    // Fast & compact  (key: compact)
    // quality=normal  resolution=480  format=webm
    // ═══════════════════════════════════════════════════════════════════════════════
    await clickGoal('Fast & compact');

    // Quality: Normal active; Good and Super disabled (no webm presets for Free)
    await expect(normalBtn()).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(goodBtn()).toBeDisabled({timeout: UI_TIMEOUT});
    await expect(superBtn()).toBeDisabled({timeout: UI_TIMEOUT});

    // Resolution: 480p active (explicit); 1080p and 720p available
    await expect(resBtn(480)).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(resBtn(1080)).toBeVisible({timeout: UI_TIMEOUT});
    await expect(resBtn(1080)).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(resBtn(720)).toBeVisible({timeout: UI_TIMEOUT});
    await expect(resBtn(720)).toBeEnabled({timeout: UI_TIMEOUT});

    // Format: WebM active but disabled and "Not Available"; MP4 enabled – both "Recommended"
    await expect(webmBtn()).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(webmBtn()).toBeDisabled({timeout: UI_TIMEOUT});
    await expect(webmBtn().locator('small')).toContainText('Not available', {timeout: UI_TIMEOUT});
    await expect(mp4Btn()).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(mp4Btn().locator('small')).toContainText('Recommended', {timeout: UI_TIMEOUT});

    await assertNoPreset();
    await shot(page, testInfo, '18-07-fast-compact.png');

    // ═══════════════════════════════════════════════════════════════════════════════
    // For PC  (key: pc)
    // quality=good  resolution=1080  format=webm
    // ═══════════════════════════════════════════════════════════════════════════════
    await clickGoal('For PC');

    // Quality: Good active; Normal and Super disabled (no webm presets for Free)
    await expect(goodBtn()).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(normalBtn()).toBeDisabled({timeout: UI_TIMEOUT});
    await expect(superBtn()).toBeDisabled({timeout: UI_TIMEOUT});

    // Resolution: 1080p active (explicit); 480p and 720p available
    await expect(resBtn(1080)).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(resBtn(480)).toBeVisible({timeout: UI_TIMEOUT});
    await expect(resBtn(480)).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(resBtn(720)).toBeVisible({timeout: UI_TIMEOUT});
    await expect(resBtn(720)).toBeEnabled({timeout: UI_TIMEOUT});

    // Format: WebM active but disabled; MP4 disabled – both "Not available"
    await expect(webmBtn()).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(webmBtn()).toBeDisabled({timeout: UI_TIMEOUT});
    await expect(webmBtn().locator('small')).toContainText('Not available', {timeout: UI_TIMEOUT});
    await expect(mp4Btn()).toBeDisabled({timeout: UI_TIMEOUT});
    await expect(mp4Btn().locator('small')).toContainText('Not available', {timeout: UI_TIMEOUT});

    await assertNoPreset();
    await shot(page, testInfo, '18-08-for-pc.png');

    // ═══════════════════════════════════════════════════════════════════════════════
    // Archive  (key: archive)
    // quality=good  resolution=auto→720p  format=mp4
    // No h265/mp4 preset in Free → no preset available
    // ═══════════════════════════════════════════════════════════════════════════════
    await clickGoal('Archive');

    // Quality: Good active but also disabled (no h265/mp4 for Free); Super disabled; Normal not selected
    await expect(goodBtn()).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(goodBtn()).toBeDisabled({timeout: UI_TIMEOUT});
    await expect(superBtn()).toBeDisabled({timeout: UI_TIMEOUT});
    await expect(normalBtn()).not.toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});

    // Resolution: 720p active (auto → 720p origin); 480p and 1080p available
    await expect(resBtn(720)).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(resBtn(480)).toBeVisible({timeout: UI_TIMEOUT});
    await expect(resBtn(480)).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(resBtn(1080)).toBeVisible({timeout: UI_TIMEOUT});
    await expect(resBtn(1080)).toBeEnabled({timeout: UI_TIMEOUT});

    // Format: MP4 active but disabled (no h265/mp4); WebM disabled – both "Not available"
    await expect(mp4Btn()).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(mp4Btn()).toBeDisabled({timeout: UI_TIMEOUT});
    await expect(mp4Btn().locator('small')).toContainText('Not available', {timeout: UI_TIMEOUT});
    await expect(webmBtn()).toBeDisabled({timeout: UI_TIMEOUT});
    await expect(webmBtn().locator('small')).toContainText('Not available', {timeout: UI_TIMEOUT});

    await assertNoPreset();
    await shot(page, testInfo, '18-09-archive.png');

    // ═══════════════════════════════════════════════════════════════════════════════
    // Custom  (key: custom)
    // Shows raw preset list with Format / Video Codec / Audio Codec filter rows
    // ═══════════════════════════════════════════════════════════════════════════════
    await clickGoal('Custom');

    // ── Filter row ───────────────────────────────────────────────────────────────
    // The filters container: div.bg-white.rounded-3.border that has a "Format:" span
    const filtersContainer = page.locator('div.bg-white.rounded-3.border').first();
    await expect(filtersContainer).toBeVisible({timeout: UI_TIMEOUT});

    // Helper: find a filter group div by its label text
    const filterGroup = (labelText) =>
        filtersContainer.locator('span', {hasText: labelText}).first();

    const formatGroup = filterGroup('Format:');
    const videoCodecGroup = filterGroup('Video Codec:');
    const audioCodecGroup = filterGroup('Audio Codec:');

    // Format: All (active), MP4, WebM
    await expect(formatGroup.locator('..').getByRole('button', {name: 'All'})).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(formatGroup.locator('..').getByRole('button', {name: 'MP4'})).toBeVisible({timeout: UI_TIMEOUT});
    await expect(formatGroup.locator('..').getByRole('button', {name: 'WebM'})).toBeVisible({timeout: UI_TIMEOUT});

    // Video Codec: All (active), H264, H265, VP8, VP9, AV1
    await expect(videoCodecGroup.locator('..').getByRole('button', {name: 'All'})).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(videoCodecGroup.locator('..').getByRole('button', {name: 'H264'})).toBeVisible({timeout: UI_TIMEOUT});
    await expect(videoCodecGroup.locator('..').getByRole('button', {name: 'H265'})).toBeVisible({timeout: UI_TIMEOUT});
    await expect(videoCodecGroup.locator('..').getByRole('button', {name: 'VP8'})).toBeVisible({timeout: UI_TIMEOUT});
    await expect(videoCodecGroup.locator('..').getByRole('button', {name: 'VP9'})).toBeVisible({timeout: UI_TIMEOUT});
    await expect(videoCodecGroup.locator('..').getByRole('button', {name: 'AV1'})).toBeVisible({timeout: UI_TIMEOUT});

    // Audio Codec: All (active), AAC, Opus
    await expect(audioCodecGroup.locator('..').getByRole('button', {name: 'All'})).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(audioCodecGroup.locator('..').getByRole('button', {name: 'AAC'})).toBeVisible({timeout: UI_TIMEOUT});
    await expect(audioCodecGroup.locator('..').getByRole('button', {name: 'Opus'})).toBeVisible({timeout: UI_TIMEOUT});

    // ── "Standard video Quality (h264/aac/mp4)" preset block ────────────────────
    const presetBlock = page.locator('.mb-3', {
        has: page.locator('h6', {hasText: 'Standard video Quality'}),
    }).first();
    await expect(presetBlock).toBeVisible({timeout: UI_TIMEOUT});

    // h6 title includes codec/format annotation
    await expect(presetBlock.locator('h6')).toContainText('Standard video Quality (h264/aac/mp4)', {timeout: UI_TIMEOUT});

    // Exactly 6 resolution buttons
    await expect(presetBlock.locator('button.btn-outline-primary')).toHaveCount(6, {timeout: UI_TIMEOUT});

    // 720p is the origin height → rendered fw-semibold (bold), exactly one such button
    await expect(presetBlock.locator('button.btn-outline-primary.fw-semibold')).toHaveCount(1, {timeout: UI_TIMEOUT});
    await expect(presetBlock.locator('button.btn-outline-primary.fw-semibold')).toContainText('720', {timeout: UI_TIMEOUT});

    // Each expected height has a corresponding button
    // Labels are `${width}${height}` (e.g. "19201080"); we match on the height at end of label
    for (const h of [1080, 720, 480, 360, 240, 144]) {
        await expect(
            presetBlock.locator('button.btn-outline-primary', {hasText: new RegExp(String(h) + '$')})
        ).toBeVisible({timeout: UI_TIMEOUT});
    }

    await shot(page, testInfo, '18-10-custom.png');

    // ── Logout ───────────────────────────────────────────────────────────────────
    await logoutToPublic(page);
    await shot(page, testInfo, '18-99-logout.png');
});


