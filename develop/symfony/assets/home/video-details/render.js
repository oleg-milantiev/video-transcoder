import { h } from 'vue';
import { bytesToHuman, humanReadableDateTime } from '../shared.js';
import { renderTaskAction } from '../task/render.js';
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
    // bitrate in Mbps * duration in seconds = megabits
    // megabits / 8 = megabytes
    const sizeInMB = (bitrateMbps * duration) / 8;
    return Math.round(sizeInMB * 1024 * 1024); // convert to bytes
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

const TASKS_SECTION_ID = 'transcoding-tasks-section';

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

    const scrollToTasks = () => {
        const el = document.getElementById(TASKS_SECTION_ID);
        if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    };
    const isHeightTaskExists = taskExists[preset.id]?.[height] === true;

    return h('div', { class: 'text-center', style: 'min-width: 90px; max-width: 110px;' }, [
        h(
            'button',
            {
                type: 'button',
                class: 'btn btn-outline-primary btn-sm w-100' + (isOrigin ? ' fw-semibold' : '') + (isHeightTaskExists ? ' text-decoration-line-through' : ''),
                disabled: isActive || video.deleted || isHeightTaskExists,
                onClick: () => {
                    vm.startTranscode(preset.id, isOrigin ? originHeight : height);
                    scrollToTasks();
                },
                title: isOrigin ? `Transcode (same as origin: ${originWidth}×${originHeight})` : undefined,
            },
            isActive ? '...' : label
        ),
        expectedSize !== null
            ? h('div', { class: 'small text-muted mt-1', style: 'font-size: 0.7rem;' }, `~${bytesToHuman(expectedSize)}`)
            : null,
    ]);
}

function renderPresetResolutions(vm, preset, taskExists) {
    const video = vm.dto?.video || {};
    const meta = video.meta || {};
    const originHeight = Number(meta._height) || 0;
    const tariffHeight = Number(vm.config?.tariff?.height) || 0;
    if (!preset.bitrate || typeof preset.bitrate !== 'object') {
        return h('p', { class: 'text-muted' }, 'No bitrate configuration available');
    }
    const heights = Object.keys(preset.bitrate)
        .map(k => parseInt(k, 10))
        .filter(h => !isNaN(h) && h > 0)
        .filter(h => tariffHeight === 0 || h <= tariffHeight)
        .sort((a, b) => b - a);
    if (heights.length === 0) {
        return h('p', { class: 'text-muted' }, 'No resolutions available for your tariff');
    }
    const buttons = [];
    for (const height of heights) {
        buttons.push(renderResolutionButton(vm, preset, height, height === originHeight, taskExists));
    }
    return h('div', { class: 'd-flex flex-wrap gap-2' }, buttons);
}

function renderPresetBlock(vm, preset, index, total, taskExists) {
    const elements = [
        h('h6', { class: 'mb-2' }, preset.title +' ('+ preset.videoCodec +'/'+ preset.audioCodec +'/'+ preset.format +')'),
        renderPresetResolutions(vm, preset, taskExists),
    ];
    return h('div', { key: preset.id, class: 'mb-3' }, elements);
}

function renderPresetsSection(vm, taskExists) {
    const presets = vm.dto?.presets || [];
    if (presets.length === 0) {
        return null;
    }
    const video = vm.dto?.video || {};
    const meta = video.meta || {};
    const hasWidth = typeof meta._width !== 'undefined' && meta._width !== null;
    const hasHeight = typeof meta._height !== 'undefined' && meta._height !== null;
    if (!hasWidth || !hasHeight) {
        return null;
    }
    return h('div', { class: 'mb-4' }, [
        ...presets.map((preset, index) => renderPresetBlock(vm, preset, index, presets.length, taskExists)),
    ]);
}

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

function renderTasksTable(vm) {
    const tasks = vm.dto?.tasks || [];
    if (tasks.length === 0) {
        return null;
    }
    const rows = tasks.map((task) => {
        return h('tr', { key: task.id }, [
            h('td', task.presetTitle || '-'),
            h('td', task.height ? String(task.height) + 'p' : '-'),
            h('td', renderTaskStatus(vm, task)),
            h('td', task.progress ? String(task.progress) + '%' : '-'),
            h('td', task.createdAt ? humanReadableDateTime(task.createdAt) : '-'),
            h('td', [renderTaskAction(task, vm.taskActions)]),
        ]);
    });
    return h('div', { class: 'mb-4', id: TASKS_SECTION_ID }, [
        h('h5', { class: 'mb-3' }, 'Transcoding Tasks'),
        h('table', { class: 'table table-bordered align-middle' }, [
            h('thead', [
                h('tr', [
                    h('th', 'Preset'),
                    h('th', 'Resolution'),
                    h('th', 'Status'),
                    h('th', 'Progress'),
                    h('th', 'Created'),
                    h('th', 'Actions'),
                ])
            ]),
            h('tbody', rows),
        ]),
    ]);
}

export function renderVideoDetails(vm) {
    if (vm.loading) {
        return h('div', { class: 'py-4' }, [
            h('h1', { class: 'mb-3' }, 'Video Details'),
            h('p', { class: 'text-muted' }, 'Loading...'),
        ]);
    }
    if (vm.error) {
        return h('div', { class: 'py-4' }, [
            h('h1', { class: 'mb-3' }, 'Video Details'),
            h('div', { class: 'alert alert-danger' }, vm.error),
            h(
                'button',
                {
                    type: 'button',
                    class: 'btn btn-outline-secondary',
                    onClick: vm.goHome,
                },
                'Back to home'
            ),
        ]);
    }
    if (!vm.dto) {
        return h('div', { class: 'py-4' }, [h('h1', { class: 'mb-3' }, 'Video Details')]);
    }
    const tasks = vm.dto.tasks || {};
    const taskExists = {};

    tasks.forEach(task => {
        if (!taskExists[task.presetId]) {
            taskExists[task.presetId] = {};
        }
        taskExists[task.presetId][task.height] = true;
    });

    const video = vm.dto.video || {};
    const metaEntries = Object.entries(video.meta || {});
    const createdAt = humanReadableDateTime(video.createdAt);
    const updatedAt = humanReadableDateTime(video.updatedAt);
    const expiredAt = humanReadableDateTime(video.expiredAt);
    const expiredAtLabel = expiredAt === '-' ? '-' : `${expiredAt} (${video.expiredInterval || ''})`;

    const metaSection = h('div', {}, [
        h('h5', { class: 'mb-2' }, 'Meta'),
        h(
            'ul',
            { class: 'mb-0 ps-3' },
            metaEntries.length > 0
                ? metaEntries
                    .filter(([key]) => !key.startsWith('_'))
                    .map(([key, value]) => h('li', [h('strong', key + ': '), vm.formatMetaValue(value)]))
                : [h('li', [h('em', 'No meta data')])]
        ),
    ]);

    return h('div', { class: 'py-4' }, [
        h('div', { class: 'd-flex justify-content-between align-items-center mb-3' }, [
            h('h1', { class: 'mb-0' }, 'Video Details'),
            h(
                'button',
                {
                    type: 'button',
                    class: 'btn btn-outline-secondary btn-sm',
                    onClick: vm.goHome,
                },
                'Back'
            ),
        ]),
        vm.actionError ? h('div', { class: 'alert alert-danger' }, vm.actionError) : null,
        h('div', { class: 'card mb-4' }, [
            h('div', { class: 'card-body' }, [
                h('div', { class: 'row' }, [
                    h('div', { class: 'col-md-8' }, [
                        video.poster
                            ? h('div', {}, [
                                  video.deleted === true
                                      ? h('div', { class: 'mb-2' }, [
                                            h('span', { class: 'badge bg-warning text-dark me-2' }, 'Deleted'),
                                            h('span', { class: 'text-muted' }, 'This video has been deleted'),
                                        ])
                                      : null,
                                  h('img', {
                                      src: video.poster,
                                      class: 'img-fluid mb-3 rounded' + (video.deleted === true ? ' video-poster--deleted' : ''),
                                      alt: video.title,
                                      style: 'max-width: 520px;',
                                  }),
                              ])
                            : null,
                        h('dl', { class: 'row mb-0' }, [
                            h('dt', { class: 'col-sm-3' }, 'Title'),
                            h(
                                'dd',
                                { class: 'col-sm-9' + (video.deleted === true ? ' video-title-deleted' : '') },
                                [
                                    h('span', {}, video.title),
                                    video.deleted
                                        ? null
                                        : h(
                                              'button',
                                              {
                                                  type: 'button',
                                                  class: 'btn btn-link p-0 ms-2',
                                                  title: 'Rename video',
                                                  onClick: vm.openRenameModal,
                                              },
                                              '✏️'
                                          ),
                                ]
                            ),
                            h('dt', { class: 'col-sm-3' }, 'Created At'),
                            h('dd', { class: 'col-sm-9' }, createdAt),
                            h('dt', { class: 'col-sm-3' }, 'Updated At'),
                            h('dd', { class: 'col-sm-9' }, updatedAt),
                            h('dt', { class: 'col-sm-3' }, 'Expired At'),
                            h('dd', { class: 'col-sm-9' }, expiredAtLabel),
                        ]),
                    ]),
                    h('div', { class: 'col-md-4 border-start' }, [metaSection]),
                ]),
            ]),
        ]),
        renderPresetsSection(vm, taskExists),
        renderTasksTable(vm),
    ]);
}
