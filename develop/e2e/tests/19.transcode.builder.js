/**
 * 19  transcode.builder (Premium tariff)
 *
 * - Login as test-19 (Premium tariff)
 * - Upload one video
 * - Open video details → Transcode tab
 * - Verify builder UI state for each Goal: Social media, Max quality,
 *   Fast & compact, For PC, Archive, Custom
 *
 * Premium has all presets: h264/aac/mp4, h265/aac/mp4, h265/opus/mp4,
 * vp9/opus/webm, av1/opus/webm, av1/opus/mp4, vp8/opus/webm.
 * All quality buttons are always enabled; WebM is available with "Smaller size".
 * Social media defaults to quality=good (not "normal" like Free).
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

const EMAIL = 'test-19@test.com';
const PASSWORD = 'test-19';
const SRC = '2022_10_04_Two_Maxes.mp4';
const VIDEO_NAME = '2022_10_04_Two_Maxes-19.mp4';
const VIDEO_TITLE = '2022_10_04_Two_Maxes-19';

test('19  transcode.builder: goal presets UI for Premium tariff', async ({page}, testInfo) => {
    // ── helpers ──────────────────────────────────────────────────────────────────

    /** Click a goal card by its label text */
    const clickGoal = async (label) => {
        const card = page.locator('div.fw-semibold', {hasText: label}).first();
        await expect(card).toBeVisible({timeout: UI_TIMEOUT});
        await card.click({timeout: UI_TIMEOUT});
    };

    /** Section cards in Builder mode */
    const qualityCard    = () => page.locator('.bg-white.rounded-3', {has: page.locator('h6', {hasText: 'Quality'})}).first();
    const resolutionCard = () => page.locator('.bg-white.rounded-3', {has: page.locator('h6', {hasText: 'Resolution'})}).first();
    const formatCard     = () => page.locator('.bg-white.rounded-3', {has: page.locator('h6', {hasText: 'Format'})}).first();

    /** Quality buttons */
    const superBtn  = () => qualityCard().getByRole('button', {name: 'Super'});
    const goodBtn   = () => qualityCard().getByRole('button', {name: 'Good'});
    const normalBtn = () => qualityCard().getByRole('button', {name: 'Normal'});

    /** Resolution button ("Xp" span inside) */
    const resBtn = (heightPx) =>
        resolutionCard().locator('button').filter({hasText: `${heightPx}p`}).first();

    /** Format buttons */
    const mp4Btn  = () => formatCard().locator('button').filter({hasText: /MP4/}).first();
    const webmBtn = () => formatCard().locator('button').filter({hasText: /WebM/}).first();

    /** "Start transcoding" CTA */
    const startBtn = () => page.locator('button', {hasText: 'Start transcoding'}).first();

    /**
     * Assert result section:
     * - badge with badgeText is visible
     * - h5 contains resText (e.g. "Original · MP4" or "1080p · MP4")
     * - size hint shows sizeStr (e.g. "22.4 MB")
     * - Start transcoding is enabled
     */
    const assertResult = async (badgeText, resText, sizeStr) => {
        await expect(page.locator('.badge.bg-primary', {hasText: badgeText})).toBeVisible({timeout: UI_TIMEOUT});
        await expect(page.locator('h5').filter({hasText: resText}).first()).toBeVisible({timeout: UI_TIMEOUT});
        if (sizeStr) {
            await expect(page.getByText(`📦 ~${sizeStr}`).first()).toBeVisible({timeout: UI_TIMEOUT});
        }
        await expect(startBtn()).toBeEnabled({timeout: UI_TIMEOUT});
    };

    // ── Login ────────────────────────────────────────────────────────────────────
    await loginAs(page, EMAIL, PASSWORD);

    // ── Upload ───────────────────────────────────────────────────────────────────
    await uploadFixtureAs(page, SRC, VIDEO_NAME);
    await shot(page, testInfo, '19-01-uploaded.png');

    // ── Open video list and navigate to video details ────────────────────────────
    await openVideosTab(page);
    await expectVideosTableVisible(page);
    const videoRow = activeVideoRowByTitle(page, VIDEO_TITLE);
    await expect(videoRow).toBeVisible({timeout: NAV_TIMEOUT});
    await videoRow.click({timeout: UI_TIMEOUT});
    await waitForVideoDetailsVisible(page);
    await waitForPosterAndMeta(page, testInfo, '19-02-poster-meta');

    // ── Switch to Transcode tab ───────────────────────────────────────────────────
    await switchToVideoTab(page, 'transcode');

    // ═══════════════════════════════════════════════════════════════════════════════
    // Social media (initial goal) — Premium defaults: quality=good, auto, mp4
    // ═══════════════════════════════════════════════════════════════════════════════

    // Quality: Good active; Super and Normal both enabled (all presets available for Premium)
    await expect(goodBtn()).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(superBtn()).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(normalBtn()).toBeEnabled({timeout: UI_TIMEOUT});

    // Resolution: 720p active (auto → origin 720p); 2160p, 1440p, 1080p, 480p available
    await expect(resBtn(720)).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(resBtn(2160)).toBeVisible({timeout: UI_TIMEOUT});
    await expect(resBtn(2160)).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(resBtn(1440)).toBeVisible({timeout: UI_TIMEOUT});
    await expect(resBtn(1440)).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(resBtn(1080)).toBeVisible({timeout: UI_TIMEOUT});
    await expect(resBtn(1080)).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(resBtn(480)).toBeVisible({timeout: UI_TIMEOUT});
    await expect(resBtn(480)).toBeEnabled({timeout: UI_TIMEOUT});

    // Format: MP4 active ("Recommended"); WebM enabled ("Smaller size"), not active
    await expect(mp4Btn()).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(mp4Btn()).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(mp4Btn().locator('small')).toContainText('Recommended', {timeout: UI_TIMEOUT});
    await expect(webmBtn()).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(webmBtn()).not.toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(webmBtn().locator('small')).toContainText('Smaller size', {timeout: UI_TIMEOUT});

    // Result: "High video Quality" preset, Original · MP4, ~2.4 MB, Start enabled
    await assertResult('High video Quality', 'Original', '2.4 MB');
    await shot(page, testInfo, '19-03-social-media.png');

    // ── Click 1080p ───────────────────────────────────────────────────────────────
    await resBtn(2160).click({timeout: UI_TIMEOUT});
    await assertResult('High video Quality', '2160p', '16.2 MB');
    await shot(page, testInfo, '19-04-resolution-1080p.png');

    // ── Click 1080p ───────────────────────────────────────────────────────────────
    await resBtn(1080).click({timeout: UI_TIMEOUT});
    await assertResult('High video Quality', '1080p', '4.1 MB');
    await shot(page, testInfo, '19-04-resolution-1080p.png');

    // ── Click 480p ────────────────────────────────────────────────────────────────
    await resBtn(480).click({timeout: UI_TIMEOUT});
    await assertResult('High video Quality', '480p', '1.2 MB');
    await shot(page, testInfo, '19-05-resolution-480p.png');

    // ── Click Super quality ───────────────────────────────────────────────────────
    // super + 480p + mp4 → av1/mp4 → "Ultra video Quality, High Efficiency Audio"
    await superBtn().click({timeout: UI_TIMEOUT});
    await expect(superBtn()).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await assertResult('Ultra video Quality, High Efficiency Audio', '480p', '829.4 KB');
    await shot(page, testInfo, '19-06-super-quality.png');

    // ═══════════════════════════════════════════════════════════════════════════════
    // Max quality — quality=super, resolution=auto→720p, format=webm
    // av1/webm preset exists → preset shown; Start disabled (per spec)
    // ═══════════════════════════════════════════════════════════════════════════════
    await clickGoal('Max quality');

    // Quality: Super active; Good and Normal enabled
    await expect(superBtn()).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(goodBtn()).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(normalBtn()).toBeEnabled({timeout: UI_TIMEOUT});

    // Resolution: 720p active (auto); 2160p, 1440p, 1080p, 480p available
    await expect(resBtn(720)).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(resBtn(2160)).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(resBtn(1440)).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(resBtn(1080)).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(resBtn(480)).toBeEnabled({timeout: UI_TIMEOUT});

    // Format: WebM active ("Smaller size"); MP4 enabled ("Recommended"), not active
    await expect(webmBtn()).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(webmBtn()).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(webmBtn().locator('small')).toContainText('Smaller size', {timeout: UI_TIMEOUT});
    await expect(mp4Btn()).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(mp4Btn()).not.toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(mp4Btn().locator('small')).toContainText('Recommended', {timeout: UI_TIMEOUT});

    // Result: "Ultra video Quality, High Efficiency Audio" badge, Original · WEBM, ~15 MB
    await expect(page.locator('.badge.bg-primary', {hasText: 'Ultra video Quality, High Efficiency Audio'})).toBeVisible({timeout: UI_TIMEOUT});
    await expect(page.locator('h5').filter({hasText: 'Original'}).first()).toBeVisible({timeout: UI_TIMEOUT});
    await expect(page.getByText('📦 ~1.6 MB').first()).toBeVisible({timeout: UI_TIMEOUT});
    // Start transcoding active
    await expect(startBtn()).toBeEnabled({timeout: UI_TIMEOUT});

    await shot(page, testInfo, '19-07-max-quality.png');

    // ═══════════════════════════════════════════════════════════════════════════════
    // Fast & compact — quality=normal, resolution=480, format=webm
    // normal+webm → vp8/webm → "Standard video Quality (WebM)"
    // ═══════════════════════════════════════════════════════════════════════════════
    await clickGoal('Fast & compact');

    // Quality: Normal active; Good and Super enabled
    await expect(normalBtn()).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(goodBtn()).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(superBtn()).toBeEnabled({timeout: UI_TIMEOUT});

    // Resolution: 480p active; 2160p, 1440p, 1080p, 720p available
    await expect(resBtn(480)).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(resBtn(2160)).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(resBtn(1440)).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(resBtn(1080)).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(resBtn(720)).toBeEnabled({timeout: UI_TIMEOUT});

    // Format: WebM active+enabled ("Smaller size"); MP4 enabled ("Recommended")
    await expect(webmBtn()).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(webmBtn()).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(webmBtn().locator('small')).toContainText('Smaller size', {timeout: UI_TIMEOUT});
    await expect(mp4Btn()).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(mp4Btn()).not.toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(mp4Btn().locator('small')).toContainText('Recommended', {timeout: UI_TIMEOUT});

    // Result: "Standard video Quality (WebM)", 480p · WEBM, ~18.7 MB, Start enabled
    await assertResult('Standard video Quality (WebM)', '480p', '2 MB');
    await shot(page, testInfo, '19-08-fast-compact.png');

    // ═══════════════════════════════════════════════════════════════════════════════
    // For PC — quality=good, resolution=1080, format=webm
    // good+webm → vp9/webm → "High video Quality+, High Efficiency Audio"
    // ═══════════════════════════════════════════════════════════════════════════════
    await clickGoal('For PC');

    // Quality: Good active; Normal and Super enabled
    await expect(goodBtn()).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(normalBtn()).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(superBtn()).toBeEnabled({timeout: UI_TIMEOUT});

    // Resolution: 1080p active; 2160p, 1440p, 720p, 480p available
    await expect(resBtn(1080)).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(resBtn(2160)).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(resBtn(1440)).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(resBtn(720)).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(resBtn(480)).toBeEnabled({timeout: UI_TIMEOUT});

    // Format: WebM active+enabled ("Smaller size"); MP4 enabled ("Recommended")
    await expect(webmBtn()).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(webmBtn()).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(webmBtn().locator('small')).toContainText('Smaller size', {timeout: UI_TIMEOUT});
    await expect(mp4Btn()).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(mp4Btn()).not.toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(mp4Btn().locator('small')).toContainText('Recommended', {timeout: UI_TIMEOUT});

    // Result: "High video Quality+, High Efficiency Audio", 1080p · WEBM, ~4.1 MB, Start enabled
    await assertResult('High video Quality+, High Efficiency Audio', '1080p', '4.1 MB');
    await shot(page, testInfo, '19-09-for-pc.png');

    // ═══════════════════════════════════════════════════════════════════════════════
    // Archive — quality=good, resolution=auto→720p, format=mp4
    // good+mp4 → h265/mp4 → "High video Quality"
    // ═══════════════════════════════════════════════════════════════════════════════
    await clickGoal('Archive');

    // Quality: Good active; Normal and Super enabled
    await expect(goodBtn()).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(normalBtn()).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(superBtn()).toBeEnabled({timeout: UI_TIMEOUT});

    // Resolution: 720p active (auto); 2160p, 1440p, 1080p, 480p available
    await expect(resBtn(720)).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(resBtn(2160)).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(resBtn(1440)).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(resBtn(1080)).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(resBtn(480)).toBeEnabled({timeout: UI_TIMEOUT});

    // Format: MP4 active+enabled ("Recommended"); WebM enabled, not active ("Smaller size")
    await expect(mp4Btn()).toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(mp4Btn()).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(mp4Btn().locator('small')).toContainText('Recommended', {timeout: UI_TIMEOUT});
    await expect(webmBtn()).toBeEnabled({timeout: UI_TIMEOUT});
    await expect(webmBtn()).not.toHaveClass(/btn-primary/, {timeout: UI_TIMEOUT});
    await expect(webmBtn().locator('small')).toContainText('Smaller size', {timeout: UI_TIMEOUT});

    // Result: "High video Quality", Original · MP4, ~2.4 MB, Start enabled
    await assertResult('High video Quality', 'Original', '2.4 MB');
    await shot(page, testInfo, '19-10-archive.png');

    // ═══════════════════════════════════════════════════════════════════════════════
    // Custom — shows raw preset list with filters
    // ═══════════════════════════════════════════════════════════════════════════════
    await clickGoal('Custom');

    // ── Filter row ───────────────────────────────────────────────────────────────
    const filtersContainer = page.locator('div.bg-white.rounded-3.border').first();
    await expect(filtersContainer).toBeVisible({timeout: UI_TIMEOUT});

    const filterGroup = (labelText) =>
        filtersContainer.locator('span', {hasText: labelText}).first();

    const formatGroup     = filterGroup('Format:');
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

    await shot(page, testInfo, '19-11-custom-filters.png');

    // ── Verify all 7 preset blocks, each with 8 resolution buttons ───────────────
    // The h6 title format is: preset.title + ' (videoCodec/audioCodec/format)'
    const expectedPresets = [
        'High video Quality (h265/aac/mp4)',
        'High video Quality, High Efficiency Audio (h265/opus/mp4)',
        'High video Quality+, High Efficiency Audio (vp9/opus/webm)',
        'Ultra video Quality, High Efficiency Audio (av1/opus/webm)',
        'Ultra video Quality, High Efficiency Audio (av1/opus/mp4)',
        'Standard video Quality (h264/aac/mp4)',
        'Standard video Quality (WebM) (vp8/opus/webm)',
    ];

    // Origin height of the fixture video (1280×720) is 720 → fw-semibold on 720p button
    for (const titleText of expectedPresets) {
        const block = page.locator('.mb-3', {
            has: page.locator('h6', {hasText: titleText}),
        }).first();
        await expect(block).toBeVisible({timeout: UI_TIMEOUT});
        await expect(block.locator('h6')).toContainText(titleText, {timeout: UI_TIMEOUT});

        // Exactly 8 resolution buttons (Premium has no height restriction)
        await expect(block.locator('button.btn-outline-primary')).toHaveCount(8, {timeout: UI_TIMEOUT});

        // Origin height (720) button has fw-semibold
        await expect(block.locator('button.btn-outline-primary.fw-semibold')).toHaveCount(1, {timeout: UI_TIMEOUT});
        await expect(block.locator('button.btn-outline-primary.fw-semibold')).toContainText('720', {timeout: UI_TIMEOUT});

        // All 8 heights are present: 2160, 1440, 1080, 720, 480, 360, 240, 144
        for (const h of [2160, 1440, 1080, 720, 480, 360, 240, 144]) {
            await expect(
                block.locator('button.btn-outline-primary', {hasText: new RegExp(String(h) + '$')})
            ).toBeVisible({timeout: UI_TIMEOUT});
        }
    }

    await shot(page, testInfo, '19-12-custom-presets.png');

    // ── Logout ───────────────────────────────────────────────────────────────────
    await logoutToPublic(page);
    await shot(page, testInfo, '19-99-logout.png');
});
