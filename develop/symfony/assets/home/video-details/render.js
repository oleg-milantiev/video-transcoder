import { h } from 'vue';
import { bytesToHuman, humanReadableDateTime } from '../shared.js';
import { renderTaskAction } from '../task/render.js';

// ── helpers ───────────────────────────────────────────────────────────────────

function formatDelayClock(seconds) {
    const normalized = Number(seconds);
    if (!Number.isFinite(normalized) || normalized < 0) {
        return '-';
    }
    const totalSeconds = Math.floor(normalized);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const remainderSeconds = totalSeconds % 60;
    return `${hours}:${String(minutes).padStart(2, '0')}:${String(remainderSeconds).padStart(2, '0')}`;
}

function calculateExpectedFileSize(bitrateMap, height, duration) {
    if (!bitrateMap || typeof bitrateMap !== 'object' || !duration) {
        return null;
    }
    const bitrateMbps = bitrateMap[String(height)];
    if (typeof bitrateMbps !== 'number' || bitrateMbps <= 0) {
        return null;
    }
    const sizeInMB = (bitrateMbps * duration) / 8;
    return Math.round(sizeInMB * 1024 * 1024);
}

function buildPendingStatusHint(vm, task) {
    if (!task || task.status !== 'PENDING') {
        return null;
    }
    const messages = [];
    const tariff = vm.config?.tariff || {};
    if (task.waitingTariffInstance === true) {
        messages.push(
            `Your tariff limits the number of transcoding tasks that can run at the same time — no more than ${tariff.instance ?? '-'}.`
        );
    }
    if (task.waitingTariffDelay === true) {
        messages.push(
            `Your tariff allows transcoding tasks to start no more often than every ${formatDelayClock(tariff.delay)}. The next video will start at ${humanReadableDateTime(task.willStartAt)}.`
        );
    }
    if (messages.length === 0) {
        return null;
    }
    return ['Why isn\'t my video transcoding?', '', ...messages.map((message) => `• ${message}`)].join('\n');
}

function renderHelpIcon(tooltipText, className = 'text-secondary border-secondary') {
    return h(
        'span',
        {
            class: `d-inline-flex align-items-center justify-content-center rounded-circle border fw-semibold ${className}`,
            title: tooltipText,
            'aria-label': tooltipText,
            tabindex: '0',
            role: 'img',
            style: 'width: 1rem; height: 1rem; font-size: 0.75rem; line-height: 1; cursor: help; user-select: none; flex-shrink: 0;',
        },
        '?'
    );
}

// ── top navigation bar ────────────────────────────────────────────────────────

function renderTopNavBar(vm) {
    function navTabButton(label, tab) {
        const isActive = tab === 'videos'; // always on video-details page
        return h('li', { class: 'nav-item', role: 'presentation' }, [
            h('button', {
                type: 'button',
                class: 'nav-link' + (isActive ? ' active' : ''),
                onClick: () => vm.navigateToTab(tab),
            }, label),
        ]);
    }

    return h('div', { class: 'd-flex align-items-center mb-0' }, [
        h('ul', { class: 'nav nav-tabs flex-grow-1 border-bottom-0' }, [
            navTabButton('📤 Upload', 'upload'),
            navTabButton('🎬 Videos', 'videos'),
            navTabButton('⚙️ Tasks', 'tasks'),
        ]),
    ]);
}

// ── video list (left column) ──────────────────────────────────────────────────

function renderVideoListItem(vm, video) {
    const isActive = video.uuid === (vm.dto?.video?.uuid ?? '');
    const isDeleted = video.deleted === true;

    const borderStyle = isActive
        ? 'box-shadow: 0 0 0 2px #0d6efd; border-radius: 10px;'
        : 'border-radius: 10px;';

    const activeProps = isActive
        ? {
            onVnodeMounted(vnode) {
                vnode.el?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            },
        }
        : {};

    return h('div', {
        key: video.uuid,
        ...activeProps,
        class: 'mb-2 overflow-hidden',
        style: `cursor: pointer; background: #f8f9fa; ${borderStyle}`,
        onClick: () => vm.openVideoDetails(video.uuid),
    }, [
        video.poster
            ? h('img', {
                src: video.poster,
                alt: video.title || '',
                style: `width: 100%; aspect-ratio: 16/9; object-fit: cover; display: block; border-radius: 10px 10px 0 0;${isDeleted ? ' filter: saturate(0); opacity: 0.6;' : ''}`,
            })
            : h('div', {
                style: 'width: 100%; aspect-ratio: 16/9; background: #dee2e6; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #6c757d; border-radius: 10px 10px 0 0;',
            }, 'No poster'),
        h('div', { style: 'padding: 6px 8px 8px; background: white; border-radius: 0 0 10px 10px;' }, [
            h('div', {
                class: 'small fw-semibold text-truncate' + (isDeleted ? ' text-muted text-decoration-line-through' : ''),
                title: video.title || '',
            }, video.title || '-'),
            h('div', { class: 'small text-muted' },
                humanReadableDateTime(video.createdAt) + (isDeleted ? ' · Deleted' : '')
            ),
        ]),
    ]);
}

function renderVideoListColumn(vm) {
    const meta = vm.videoListMeta;

    return h('div', { class: 'col-md-3' }, [
        h('div', { class: 'card border-0 shadow-sm h-100', style: 'border-radius: 12px; overflow: hidden;' }, [
            h('div', { class: 'card-header bg-white border-0 py-3' }, [
                h('span', { class: 'fw-semibold' }, '🎬 Videos'),
                h('span', { class: 'badge bg-secondary ms-2' }, String(meta.total || 0)),
            ]),
            h('div', { class: 'card-body p-0' }, [
                h('div', {
                    class: 'p-3 pt-1',
                    style: 'max-height: 65vh; overflow-y: auto;',
                },
                    vm.videoListLoading
                        ? [h('p', { class: 'text-muted small py-2' }, 'Loading...')]
                        : vm.videoListItems.length > 0
                            ? vm.videoListItems.map(video => renderVideoListItem(vm, video))
                            : [h('p', { class: 'text-muted small py-2' }, 'No videos')]
                ),
                h('div', { class: 'd-flex justify-content-between align-items-center p-3 border-top' }, [
                    h('button', {
                        type: 'button',
                        class: 'btn btn-outline-secondary btn-sm',
                        disabled: meta.page <= 1 || vm.videoListLoading,
                        onClick: () => vm.loadVideoList(meta.page - 1),
                    }, 'Prev'),
                    h('span', { class: 'text-muted small' },
                        `Page ${meta.page} / ${meta.totalPages} (${meta.total})`
                    ),
                    h('button', {
                        type: 'button',
                        class: 'btn btn-outline-secondary btn-sm',
                        disabled: meta.page >= meta.totalPages || vm.videoListLoading,
                        onClick: () => vm.loadVideoList(meta.page + 1),
                    }, 'Next'),
                ]),
            ]),
        ]),
    ]);
}

// ── right column tab helpers ──────────────────────────────────────────────────

function renderTabButton(vm, tabName, label) {
    return h('li', { class: 'nav-item' }, [
        h('button', {
            type: 'button',
            class: 'nav-link' + (vm.activeTab === tabName ? ' active' : ''),
            onClick: () => vm.setActiveTab(tabName),
        }, label),
    ]);
}

// ── Info tab ──────────────────────────────────────────────────────────────────

function renderInfoTab(vm) {
    const video = vm.dto.video || {};
    const metaEntries = Object.entries(video.meta || {});
    const createdAt = humanReadableDateTime(video.createdAt);
    const updatedAt = humanReadableDateTime(video.updatedAt);
    const expiredAt = humanReadableDateTime(video.expiredAt);
    const expiredAtLabel = expiredAt === '-' ? '-' : `${expiredAt} (${video.expiredInterval || ''})`;

    const originWidth = Number((video.meta || {})._width) || 0;
    const originHeight = Number((video.meta || {})._height) || 0;
    const isLandscape = originWidth > 0 && originHeight > 0 && originWidth > originHeight;

    // Title heading (above poster + data in all layouts)
    const titleRow = h('div', { class: 'd-flex align-items-center gap-2 mb-3' }, [
        h('h6', { class: 'mb-0 fw-semibold' + (video.deleted ? ' text-decoration-line-through text-muted' : '') }, video.title || '-'),
        !video.deleted
            ? h('button', {
                type: 'button',
                class: 'btn btn-link btn-sm p-0',
                title: 'Rename',
                onClick: vm.openRenameModal,
            }, '✏️')
            : null,
        video.deleted
            ? h('span', { class: 'badge bg-warning text-dark' }, 'Deleted')
            : null,
    ]);

    const posterNode = video.poster
        ? h('img', {
            src: video.poster,
            class: 'img-fluid rounded-3',
            style: 'width: 100%;' + (video.deleted ? ' filter: saturate(0); opacity: 0.6;' : ''),
            alt: video.title || '',
        })
        : h('div', {
            style: 'width:100%; aspect-ratio: 16/9; background:#eee; display:flex; align-items:center; justify-content:center; color:#aaa; border-radius: 12px;',
        }, 'No poster');

    const dlNode = h('dl', { class: 'row mb-2' }, [
        h('dt', { class: 'col-sm-4 small' }, 'Created'),
        h('dd', { class: 'col-sm-8 small' }, createdAt),
        h('dt', { class: 'col-sm-4 small' }, 'Updated'),
        h('dd', { class: 'col-sm-8 small' }, updatedAt),
        h('dt', { class: 'col-sm-4 small' }, 'Expires'),
        h('dd', { class: 'col-sm-8 small' }, expiredAtLabel),
    ]);

    const metaNode = metaEntries.length > 0
        ? h('div', {}, [
            h('h6', { class: 'mb-1' }, 'Meta'),
            h('ul', { class: 'small text-secondary ps-3 mb-0' },
                metaEntries
                    .filter(([k]) => !k.startsWith('_'))
                    .map(([k, v]) => h('li', {}, [h('strong', {}, k + ': '), vm.formatMetaValue(v)]))
            ),
        ])
        : null;

    if (isLandscape) {
        // Horizontal poster: full-width poster on top, two columns below
        return h('div', {}, [
            titleRow,
            h('div', { class: 'mb-3' }, [posterNode]),
            h('div', { class: 'row g-3' }, [
                h('div', { class: 'col-md-6' }, [dlNode]),
                h('div', { class: 'col-md-6' }, [metaNode]),
            ]),
        ]);
    }

    // Vertical / unknown: poster left, data right
    return h('div', {}, [
        titleRow,
        h('div', { class: 'row g-3' }, [
            h('div', { class: 'col-md-5' }, [posterNode]),
            h('div', { class: 'col-md-7' }, [dlNode, metaNode]),
        ]),
    ]);
}

// ── Transcode tab ─────────────────────────────────────────────────────────────

function renderResolutionButton(vm, preset, height, isOrigin, taskExists) {
    const video = vm.dto?.video || {};
    const meta = video.meta || {};
    const originWidth = Number(meta._width) || 0;
    const originHeight = Number(meta._height) || 0;
    const duration = Number(meta._duration) || 0;
    const width = originHeight > 0 && originWidth > 0 && !isOrigin
        ? Math.round((originWidth > originHeight) ? originWidth / originHeight * height : originHeight / originWidth * height)
        : ((originWidth > originHeight) ? originWidth : originHeight);
    const label = (originWidth > originHeight) ? `${width}×${height}` : `${height}×${width}`;
    const expectedSize = calculateExpectedFileSize(preset.bitrate, height, duration);
    const actionKey = 'transcode-' + String(preset.id) + '-' + String(height);
    const isActive = vm.activeActionKey === actionKey;
    const isHeightTaskExists = taskExists[preset.id]?.[height] === true;

    return h('div', { class: 'text-center', style: 'min-width: 90px; max-width: 110px;' }, [
        h('button', {
            type: 'button',
            class: 'btn btn-outline-primary btn-sm w-100'
                + (isOrigin ? ' fw-semibold' : '')
                + (isHeightTaskExists ? ' text-decoration-line-through' : ''),
            disabled: isActive || video.deleted || isHeightTaskExists,
            onClick: () => {
                vm.startTranscode(preset.id, isOrigin ? originHeight : height);
                vm.setActiveTab('tasks');
            },
            title: isOrigin ? `Transcode (same as origin: ${originWidth}×${originHeight})` : undefined,
        }, isActive ? '...' : label),
        expectedSize !== null
            ? h('div', { class: 'small text-muted mt-1', style: 'font-size: 0.7rem;' }, `~${bytesToHuman(expectedSize)}`)
            : null,
    ]);
}

function renderPresetBlock(vm, preset, taskExists) {
    const video = vm.dto?.video || {};
    const meta = video.meta || {};
    const originHeight = Number(meta._height) || 0;
    const tariffHeight = Number(vm.config?.tariff?.height) || 0;

    if (!preset.bitrate || typeof preset.bitrate !== 'object') {
        return h('div', { key: preset.id, class: 'mb-3' }, [
            h('h6', { class: 'mb-2' }, preset.title + ' (' + preset.videoCodec + '/' + preset.audioCodec + '/' + preset.format + ')'),
            h('p', { class: 'text-muted small' }, 'No bitrate configuration available'),
        ]);
    }

    const heights = Object.keys(preset.bitrate)
        .map(k => parseInt(k, 10))
        .filter(k => !isNaN(k) && k > 0)
        .filter(k => tariffHeight === 0 || k <= tariffHeight)
        .sort((a, b) => b - a);

    if (heights.length === 0) {
        return null;
    }

    const buttons = heights.map(height =>
        renderResolutionButton(vm, preset, height, height === originHeight, taskExists)
    );

    return h('div', { key: preset.id, class: 'mb-3' }, [
        h('h6', { class: 'mb-2' }, preset.title + ' (' + preset.videoCodec + '/' + preset.audioCodec + '/' + preset.format + ')'),
        h('div', { class: 'd-flex flex-wrap gap-2' }, buttons),
    ]);
}

function renderTranscodePresets(vm, taskExists) {
    const presets = vm.dto?.presets || [];
    const video = vm.dto?.video || {};
    const meta = video.meta || {};

    const heading = h('h5', { class: 'mb-3' }, 'Transcode Video');

    if (presets.length === 0) {
        return h('div', {}, [heading, h('p', { class: 'text-muted' }, 'No presets available')]);
    }

    if (typeof meta._width === 'undefined' || typeof meta._height === 'undefined') {
        return h('div', {}, [heading, h('p', { class: 'text-muted' }, 'Video metadata not yet available')]);
    }

    const blocks = presets
        .map(preset => renderPresetBlock(vm, preset, taskExists))
        .filter(Boolean);

    return h('div', {}, [heading, ...blocks]);
}

// ── Transcode Builder constants ───────────────────────────────────────────────

const TRANSCODE_GOALS = [
    { key: 'social',  icon: '📱', label: 'Social media',   sub: 'TikTok, Reels, Shorts' },
    { key: 'quality', icon: '⭐', label: 'Max quality',    sub: 'Best video quality' },
    { key: 'compact', icon: '⚡', label: 'Fast & compact', sub: 'Minimum file size' },
    { key: 'pc',      icon: '🖥', label: 'For PC',         sub: 'Universal format' },
    { key: 'archive', icon: '🗄️', label: 'Archive',        sub: 'Long-term storage' },
    { key: 'custom',  icon: '⚙️', label: 'Custom',         sub: 'Full control' },
];

const TRANSCODE_QUALITY_OPTIONS = [
    { key: 'super',  label: 'Super',  hint: 'Super quality — very slow encoding with best results (AV1).' },
    { key: 'good',   label: 'Good',   hint: 'Good quality — good balance between encoding speed and file size (HEVC, VP9).' },
    { key: 'normal', label: 'Normal', hint: 'Normal quality — quick encoding, clever compromise of quality. Maximum compatibility with devices (H264).' },
];

const TRANSCODE_FORMAT_OPTIONS = [
    { key: 'mp4',  label: 'MP4',  sub: 'Recommended', hint: 'MP4 — widest compatibility with devices and platforms.' },
    { key: 'webm', label: 'WebM', sub: 'Smaller size', hint: 'WebM — smaller files, ideal for modern browsers and web delivery.' },
];

const GOAL_DEFAULTS = {
    social:  { quality: 'good',   resolution: 'auto', format: 'mp4'  },
    quality: { quality: 'super',  resolution: 'auto', format: 'webm'  },
    compact: { quality: 'normal', resolution: '480',  format: 'webm' },
    pc:      { quality: 'good',   resolution: '1080', format: 'mp4'  },
    archive: { quality: 'good',   resolution: 'auto', format: 'mp4'  },
};

const BUILDER_RESOLUTIONS = ['2160', '1440', '1080', '720', '480'];

// Maps (quality, format) → expected videoCodec value in preset
const QUALITY_CODEC_MAP = {
    super:  { mp4: 'av1',  webm: 'av1'  },
    good:   { mp4: 'h265', webm: 'vp9'  },
    normal: { mp4: 'h264', webm: 'h264' },
};

function resolveBuilderPreset(presets, quality, format) {
    const codecByFormat = QUALITY_CODEC_MAP[quality] || QUALITY_CODEC_MAP.normal;
    const targetCodec   = codecByFormat[format] || codecByFormat.mp4;

    // 1. Exact match: codec + format
    let found = presets.find(p => p.videoCodec === targetCodec && p.format === format);
    // 2. Codec only
    if (!found) { found = presets.find(p => p.videoCodec === targetCodec); }
    // 3. Format only
    if (!found) { found = presets.find(p => p.format === format); }
    // 4. First available
    return found || presets[0] || null;
}

/**
 * Resolves 'auto' to the closest available resolution string.
 * The short side (min of width/height) is used for matching.
 */
function resolveEffectiveResolution(transcodeResolution, originWidth, originHeight, availableResolutions) {
    if (transcodeResolution !== 'auto') {
        return transcodeResolution;
    }
    const shortSide = (originWidth > 0 && originHeight > 0)
        ? Math.min(originWidth, originHeight)
        : 1080;
    let closest = availableResolutions[0] || '1080';
    let minDiff = Infinity;
    for (const r of availableResolutions) {
        const diff = Math.abs(parseInt(r, 10) - shortSide);
        if (diff < minDiff) {
            minDiff = diff;
            closest = r;
        }
    }
    return closest;
}

// ── Transcode Builder UI ──────────────────────────────────────────────────────

function renderGoalCards(vm) {
    const cardStyle = (active) =>
        'border:' + (active ? '2px solid #0d6efd;background:#f0f6ff' : '1px solid #dee2e6;background:#fff')
        + ';border-radius:12px;padding:12px 8px;text-align:center;cursor:pointer;height:100%;transition:border-color .15s';

    return h('div', { class: 'row g-2 mb-3' }, [
        h('div', { class: 'col-12 mb-1' }, [
            h('h6', { class: 'fw-bold mb-0' }, 'Goals'),
        ]),
        ...TRANSCODE_GOALS.map(g =>
            h('div', { class: 'col-4 col-sm-4 col-lg-2' }, [
                h('div', {
                    style: cardStyle(vm.transcodeGoal === g.key),
                    onClick: () => {
                        vm.setTranscodeGoal(g.key);
                        const def = GOAL_DEFAULTS[g.key];
                        if (def) {
                            vm.setTranscodeQuality(def.quality);
                            vm.setTranscodeResolution(def.resolution);
                            vm.setTranscodeFormat(def.format);
                        }
                    },
                }, [
                    h('div', { style: 'font-size:1.6rem;line-height:1;margin-bottom:6px' }, g.icon),
                    h('div', { class: 'fw-semibold', style: 'font-size:0.82rem' }, g.label),
                    h('div', { class: 'text-muted', style: 'font-size:0.7rem' }, g.sub),
                ]),
            ])
        ),
    ]);
}

function renderTranscodeBuilder(vm, taskExists) {
    const video = vm.dto?.video || {};
    const meta = video.meta || {};
    const originWidth  = Number(meta._width)    || 0;
    const originHeight = Number(meta._height)   || 0;
    const duration     = Number(meta._duration) || 0;

    // ── Quality ────────────────────────────────────────────────────────────────
    const qualityHint = TRANSCODE_QUALITY_OPTIONS.find(q => q.key === vm.transcodeQuality)?.hint || '';
    const qualityButtons = TRANSCODE_QUALITY_OPTIONS.map(q =>
        h('button', {
            type: 'button',
            class: 'btn btn-sm ' + (vm.transcodeQuality === q.key ? 'btn-primary' : 'btn-outline-secondary'),
            style: 'border-radius:8px',
            onClick: () => vm.setTranscodeQuality(q.key),
        }, q.label)
    );

    // ── Resolution ─────────────────────────────────────────────────────────────
    const tariffMaxHeight = Number(vm.config?.tariff?.height) || 0;
    const isLandscape = originWidth > 0 && originHeight > 0 ? originWidth >= originHeight : true;

    // Filter available resolutions by tariff
    const filteredResolutions = BUILDER_RESOLUTIONS.filter(r =>
        !(tariffMaxHeight > 0 && parseInt(r, 10) > tariffMaxHeight)
    );

    // Resolve 'auto' to closest available button
    const effectiveResolution = resolveEffectiveResolution(
        vm.transcodeResolution, originWidth, originHeight, filteredResolutions
    );

    const resolutionButtons = filteredResolutions.map(r => {
        const shortSide = parseInt(r, 10);
        let subLabel;
        if (isLandscape) {
            const longSide = originWidth && originHeight
                ? Math.round(shortSide * originWidth / originHeight)
                : Math.round(shortSide * 16 / 9);
            subLabel = `${longSide}×${shortSide}`;
        } else {
            const longSide = originWidth && originHeight
                ? Math.round(shortSide * originHeight / originWidth)
                : Math.round(shortSide * 16 / 9);
            subLabel = `${shortSide}×${longSide}`;
        }
        const isActive = r === effectiveResolution;
        return h('button', {
            type: 'button',
            class: 'btn btn-sm ' + (isActive ? 'btn-primary' : 'btn-outline-secondary'),
            style: 'border-radius:8px',
            onClick: () => vm.setTranscodeResolution(r),
        }, [
            h('span', {}, r + 'p'),
            h('br'),
            h('small', { class: isActive ? 'text-white opacity-75' : 'text-muted' }, subLabel),
        ]);
    });

    // ── Format ─────────────────────────────────────────────────────────────────
    const formatHint = TRANSCODE_FORMAT_OPTIONS.find(f => f.key === vm.transcodeFormat)?.hint || '';
    const formatButtons = TRANSCODE_FORMAT_OPTIONS.map(fmt => {
        const isActive = vm.transcodeFormat === fmt.key;
        return h('button', {
            type: 'button',
            class: 'btn btn-sm ' + (isActive ? 'btn-primary' : 'btn-outline-secondary'),
            style: 'border-radius:8px',
            onClick: () => vm.setTranscodeFormat(fmt.key),
        }, [
            h('span', {}, fmt.label),
            h('br'),
            h('small', { class: isActive ? 'text-white opacity-75' : 'text-muted' }, fmt.sub),
        ]);
    });

    // ── Result / CTA ───────────────────────────────────────────────────────────
    const presets = vm.dto?.presets || [];
    const preset = resolveBuilderPreset(presets, vm.transcodeQuality, vm.transcodeFormat);
    const selectedHeight = parseInt(effectiveResolution, 10);
    const expectedSize = preset && selectedHeight > 0
        ? calculateExpectedFileSize(preset.bitrate, selectedHeight, duration)
        : null;
    const alreadyExists = !!(preset && taskExists[preset.id]?.[selectedHeight]);
    const actionKey = preset ? 'transcode-' + preset.id + '-' + selectedHeight : null;
    const isRunning = !!(actionKey && vm.activeActionKey === actionKey);
    const canStart = !!(preset && selectedHeight > 0 && !video.deleted && !alreadyExists && !isRunning);

    const resultSection = h('div', {
        class: 'rounded-3 p-3',
        style: 'background:#eef2f7',
    }, [
        h('div', { class: 'row align-items-center' }, [
            h('div', { class: 'col-md-8' }, [
                preset
                    ? h('span', { class: 'badge bg-primary me-1 mb-2' }, preset.title)
                    : null,
                alreadyExists
                    ? h('span', { class: 'badge bg-secondary mb-2' }, 'Already transcoded')
                    : null,
                h('h5', { class: 'mb-1 mt-1' }, [
                    vm.transcodeResolution === 'auto' ? 'Original' : vm.transcodeResolution + 'p',
                    ' · ',
                    vm.transcodeFormat.toUpperCase(),
                ]),
                h('div', { class: 'd-flex flex-wrap gap-3 small text-muted' }, [
                    expectedSize !== null
                        ? h('div', {}, '📦 ~' + bytesToHuman(expectedSize))
                        : null,
                ]),
            ]),
            h('div', { class: 'col-md-4 text-md-end mt-3 mt-md-0' }, [
                h('button', {
                    type: 'button',
                    class: 'btn btn-primary px-4',
                    disabled: !canStart,
                    onClick: () => {
                        if (canStart) {
                            vm.startTranscode(preset.id, selectedHeight);
                            vm.setActiveTab('tasks');
                        }
                    },
                }, isRunning ? '...' : 'Start transcoding'),
            ]),
        ]),
    ]);

    const sectionCard = (children) =>
        h('div', { class: 'bg-white rounded-3 p-3 border h-100' }, children);

    return h('div', {}, [
        renderGoalCards(vm),
        h('div', { class: 'row g-3 mb-3' }, [
            h('div', { class: 'col-md-4' }, [sectionCard([
                h('h6', { class: 'fw-bold mb-2' }, 'Quality'),
                h('div', { class: 'd-flex gap-2 mb-2' }, qualityButtons),
                h('div', { class: 'text-muted', style: 'font-size:0.78rem' }, qualityHint),
            ])]),
            h('div', { class: 'col-md-4' }, [sectionCard([
                h('h6', { class: 'fw-bold mb-2' }, 'Resolution'),
                h('div', { class: 'd-flex flex-wrap gap-2' }, resolutionButtons),
            ])]),
            h('div', { class: 'col-md-4' }, [sectionCard([
                h('h6', { class: 'fw-bold mb-2' }, 'Format'),
                h('div', { class: 'd-flex gap-2 mb-2' }, formatButtons),
                h('div', { class: 'text-muted', style: 'font-size:0.78rem' }, formatHint),
            ])]),
        ]),
        resultSection,
    ]);
}

// ── Transcode tab dispatcher ──────────────────────────────────────────────────

function renderTranscodeTab(vm, taskExists) {
    if (vm.transcodeGoal === 'custom') {
        return h('div', {}, [
            renderGoalCards(vm),
            renderTranscodePresets(vm, taskExists),
        ]);
    }
    return renderTranscodeBuilder(vm, taskExists);
}

// ── Tasks tab ─────────────────────────────────────────────────────────────────

function renderTaskStatus(vm, task) {
    if (!task) {
        return h('em', 'No task');
    }
    const tooltipText = buildPendingStatusHint(vm, task);
    if (tooltipText === null) {
        return task.status;
    }
    return h('span', { class: 'd-inline-flex align-items-center gap-1' }, [
        h('span', task.status),
        renderHelpIcon(tooltipText),
    ]);
}

function renderTasksTab(vm) {
    const tasks = vm.dto?.tasks || [];

    const heading = h('h5', { class: 'mb-3' }, 'Transcoding Tasks');

    if (tasks.length === 0) {
        return h('div', {}, [heading, h('p', { class: 'text-muted' }, 'No tasks yet')]);
    }

    const rows = tasks.map((task) =>
        h('tr', { key: task.id }, [
            h('td', { style: 'font-size: 0.8rem;' }, task.presetTitle || '-'),
            h('td', { style: 'font-size: 0.8rem;' }, task.height ? String(task.height) + 'p' : '-'),
            h('td', { style: 'font-size: 0.8rem;' }, renderTaskStatus(vm, task)),
            h('td', { style: 'font-size: 0.8rem;' }, task.progress ? String(task.progress) + '%' : '-'),
            h('td', { style: 'font-size: 0.8rem;' }, task.createdAt ? humanReadableDateTime(task.createdAt) : '-'),
            h('td', [renderTaskAction(task, vm.taskActions)]),
        ])
    );

    return h('div', {}, [
        heading,
        h('div', { id: 'transcoding-tasks-section', class: 'table-responsive' }, [
        h('table', { class: 'table table-bordered align-middle mb-0' }, [
            h('thead', { class: 'table-light' }, [
                h('tr', [
                    h('th', { style: 'font-size: 0.8rem;' }, 'Preset'),
                    h('th', { style: 'font-size: 0.8rem;' }, 'Resolution'),
                    h('th', { style: 'font-size: 0.8rem;' }, 'Status'),
                    h('th', { style: 'font-size: 0.8rem;' }, 'Progress'),
                    h('th', { style: 'font-size: 0.8rem;' }, 'Created'),
                    h('th', { style: 'font-size: 0.8rem;' }, 'Actions'),
                ]),
            ]),
            h('tbody', rows),
        ]),
    ]),
]);
}

// ── right detail column ───────────────────────────────────────────────────────

function renderDetailColumn(vm, taskExists) {
    return h('div', { class: 'col-md-9' }, [
        vm.actionError
            ? h('div', { class: 'alert alert-danger mb-2' }, vm.actionError)
            : null,
        h('div', { style: 'position: relative;' }, [
            // Close button — white label sticking to the top-right of the card
            h('button', {
                type: 'button',
                title: 'Close',
                'aria-label': 'Close',
                style: 'position: absolute; top: -14px; right: 8px; z-index: 10; background: white; border: 1px solid #dee2e6; border-radius: 20px; padding: 1px 10px; font-size: 0.82rem; line-height: 1.5; box-shadow: 0 1px 4px rgba(0,0,0,.1); cursor: pointer;',
                onClick: () => vm.closeDetails(),
            }, '✕'),
            h('div', { class: 'card border-0 shadow-sm', style: 'border-radius: 12px; overflow: hidden;' }, [
                // Tab header (right-aligned)
                h('div', { class: 'card-header bg-white border-0 pt-2 pb-0' }, [
                    h('ul', { class: 'nav nav-tabs justify-content-end' }, [
                        renderTabButton(vm, 'info', '📄 Details'),
                        renderTabButton(vm, 'transcode', '⚙️ Transcode'),
                        renderTabButton(vm, 'tasks', '📋 Tasks'),
                    ]),
                ]),
                // Tab content
                h('div', { class: 'card-body' }, [
                    vm.activeTab === 'info' ? renderInfoTab(vm) : null,
                    vm.activeTab === 'transcode' ? renderTranscodeTab(vm, taskExists) : null,
                    vm.activeTab === 'tasks' ? renderTasksTab(vm) : null,
                ]),
            ]),
        ]),
    ]);
}

// ── main render ───────────────────────────────────────────────────────────────

export function renderVideoDetails(vm) {
    if (vm.loading && vm.videoListItems.length === 0) {
        return h('div', { class: 'py-4 text-center text-muted' }, 'Loading...');
    }

    if (vm.error) {
        return h('div', { class: 'py-4' }, [
            h('div', { class: 'alert alert-danger' }, vm.error),
            h('button', {
                type: 'button',
                class: 'btn btn-outline-secondary',
                onClick: vm.goHome,
            }, 'Back to home'),
        ]);
    }

    if (!vm.dto) {
        return h('div', { class: 'py-4 text-muted' }, 'Loading video details...');
    }

    const tasks = vm.dto.tasks || [];
    const taskExists = {};
    tasks.forEach(task => {
        if (!taskExists[task.presetId]) {
            taskExists[task.presetId] = {};
        }
        taskExists[task.presetId][task.height] = true;
    });

    return h('div', {}, [
        renderTopNavBar(vm),
        h('div', { class: 'row g-3' }, [
            renderVideoListColumn(vm),
            renderDetailColumn(vm, taskExists),
        ]),
    ]);
}
