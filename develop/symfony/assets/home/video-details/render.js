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

    return h('div', { class: 'row g-3' }, [
        // Poster
        h('div', { class: 'col-md-5' }, [
            video.poster
                ? h('img', {
                    src: video.poster,
                    class: 'img-fluid rounded-3',
                    style: 'width: 100%;' + (video.deleted ? ' filter: saturate(0); opacity: 0.6;' : ''),
                    alt: video.title || '',
                })
                : h('div', {
                    style: 'width:100%; aspect-ratio: 16/9; background:#eee; display:flex; align-items:center; justify-content:center; color:#aaa; border-radius: 12px;',
                }, 'No poster'),
            video.deleted
                ? h('div', { class: 'mt-2' }, [
                    h('span', { class: 'badge bg-warning text-dark me-1' }, 'Deleted'),
                    h('span', { class: 'text-muted small' }, 'This video has been deleted'),
                ])
                : null,
        ]),
        // Metadata
        h('div', { class: 'col-md-7' }, [
            h('dl', { class: 'row mb-2' }, [
                h('dt', { class: 'col-sm-4 small' }, 'Title'),
                h('dd', { class: 'col-sm-8 fw-semibold small' }, [
                    h('span', {}, video.title || '-'),
                    !video.deleted
                        ? h('button', {
                            type: 'button',
                            class: 'btn btn-link btn-sm p-0 ms-1',
                            title: 'Rename',
                            onClick: vm.openRenameModal,
                        }, '✏️')
                        : null,
                ]),
                h('dt', { class: 'col-sm-4 small' }, 'Created'),
                h('dd', { class: 'col-sm-8 small' }, createdAt),
                h('dt', { class: 'col-sm-4 small' }, 'Updated'),
                h('dd', { class: 'col-sm-8 small' }, updatedAt),
                h('dt', { class: 'col-sm-4 small' }, 'Expires'),
                h('dd', { class: 'col-sm-8 small' }, expiredAtLabel),
            ]),
            metaEntries.length > 0
                ? h('div', {}, [
                    h('h6', { class: 'mb-1' }, 'Meta'),
                    h('ul', { class: 'small text-secondary ps-3 mb-0' },
                        metaEntries
                            .filter(([k]) => !k.startsWith('_'))
                            .map(([k, v]) => h('li', {}, [h('strong', {}, k + ': '), vm.formatMetaValue(v)]))
                    ),
                ])
                : null,
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

function renderTranscodeTab(vm, taskExists) {
    const presets = vm.dto?.presets || [];
    const video = vm.dto?.video || {};
    const meta = video.meta || {};

    if (presets.length === 0) {
        return h('p', { class: 'text-muted' }, 'No presets available');
    }

    if (typeof meta._width === 'undefined' || typeof meta._height === 'undefined') {
        return h('p', { class: 'text-muted' }, 'Video metadata not yet available');
    }

    const blocks = presets
        .map(preset => renderPresetBlock(vm, preset, taskExists))
        .filter(Boolean);

    return h('div', {}, blocks);
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
    if (tasks.length === 0) {
        return h('p', { class: 'text-muted' }, 'No tasks yet');
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

    return h('div', { id: 'transcoding-tasks-section', class: 'table-responsive' }, [
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
                    h('ul', { class: 'nav nav-tabs border-0 justify-content-end' }, [
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
