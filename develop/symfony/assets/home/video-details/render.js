import { h } from 'vue';

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
            `Your tariff allows transcoding tasks to start no more often than every ${formatDelayClock(tariff.delay)}. The next video will start at ${task.willStartAt || '-'}.`
        );
    }

    if (messages.length === 0) {
        return null;
    }

    return ['Why isn\'t my video transcoding?', '', ...messages.map((message) => `• ${message}`)].join('\n');
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
        h(
            'span',
            {
                class: 'd-inline-flex align-items-center justify-content-center rounded-circle border border-secondary text-secondary fw-semibold',
                title: tooltipText,
                'aria-label': tooltipText,
                tabindex: '0',
                role: 'img',
                style: 'width: 1rem; height: 1rem; font-size: 0.75rem; line-height: 1; cursor: help; user-select: none;',
            },
            '?'
        ),
    ]);
}

function createTaskAction(vm, preset) {
    const task = preset.task;

    if (vm.dto.deleted) {
        return '';
    }

    if (task && task.id) {
        if (task.status === 'COMPLETED') {
            return h(
                'a',
                {
                    href: vm.taskDownloadUrl(task.id),
                    class: 'btn btn-outline-primary btn-sm',
                    download: task.downloadFilename,
                },
                'Download'
            );
        }

        if (task.status === 'PENDING' || task.status === 'STARTING' || task.status === 'PROCESSING') {
            return h(
                'button',
                {
                    type: 'button',
                    class: 'btn btn-outline-primary btn-sm',
                    disabled: vm.activeActionKey === 'cancel-' + String(task.id),
                    onClick: () => vm.cancelTask(task.id),
                },
                vm.activeActionKey === 'cancel-' + String(task.id) ? 'Cancelling...' : 'Cancel'
            );
        }
    }

    return h(
        'button',
        {
            type: 'button',
            class: 'btn btn-outline-primary btn-sm',
            disabled: vm.activeActionKey === 'transcode-' + String(preset.id),
            onClick: () => vm.startTranscode(preset.id),
        },
        vm.activeActionKey === 'transcode-' + String(preset.id) ? 'Processing...' : 'Transcode'
    );
}

function renderPresetRows(vm) {
    return (vm.dto.presetsWithTasks || []).map((preset) => {
        const task = preset.task;
        const actionNode = createTaskAction(vm, preset);

        return h('tr', [
            h('td', preset.title),
            h('td', renderTaskStatus(vm, task)),
            h('td', task ? String(task.progress) + '%' : '-'),
            h('td', task ? task.createdAt : '-'),
            h('td', [actionNode]),
        ]);
    });
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

    const rows = renderPresetRows(vm);
    const metaEntries = Object.entries(vm.dto.meta || {});

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
                vm.dto.poster
                    ? h('div', {}, [
                          vm.dto.deleted === true
                              ? h('div', { class: 'mb-2' }, [
                                    h('span', { class: 'badge bg-warning text-dark me-2' }, 'Deleted'),
                                    h('span', { class: 'text-muted' }, 'This video has been deleted'),
                                ])
                              : null,
                          h('img', {
                              src: vm.dto.poster,
                              class: 'img-fluid mb-3 rounded' + (vm.dto.deleted === true ? ' video-poster--deleted' : ''),
                              alt: vm.dto.title,
                              style: 'max-width: 520px;',
                          }),
                      ])
                    : null,
                h('dl', { class: 'row mb-0' }, [
                    h('dt', { class: 'col-sm-3' }, 'Title'),
                    h(
                        'dd',
                        { class: 'col-sm-9' + (vm.dto.deleted === true ? ' video-title-deleted' : '') },
                        [
                            h('span', {}, vm.dto.title),
                            // Edit icon button (pencil). Opens rename modal via vm.openRenameModal
                            vm.dto.deleted
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
                    h('dt', { class: 'col-sm-3' }, 'Extension'),
                    h('dd', { class: 'col-sm-9' }, vm.dto.extension),
                    h('dt', { class: 'col-sm-3' }, 'Created At'),
                    h('dd', { class: 'col-sm-9' }, vm.dto.createdAt),
                    h('dt', { class: 'col-sm-3' }, 'Updated At'),
                    h('dd', { class: 'col-sm-9' }, vm.dto.updatedAt || '-'),
                    h('dt', { class: 'col-sm-3' }, 'Expired At'),
                    h('dd', { class: 'col-sm-9' }, vm.dto.expiredAt + ' (' + vm.dto.expiredInterval + ')' || '-'),
                ]),
            ]),
        ]),
        (vm.dto.poster && Object.keys(vm.dto.meta || {}).length > 0)
            ? h('div', {}, [
                h('h5', { class: 'mb-2' }, 'Presets'),
                h('table', { class: 'table table-bordered align-middle mb-4' }, [
                    h('thead', [h('tr', [h('th', 'Preset'), h('th', 'Status'), h('th', 'Progress'), h('th', 'Created'), h('th', 'Actions')])]),
                    h('tbody', rows.length > 0 ? rows : [h('tr', [h('td', { colspan: '5', class: 'text-muted text-center' }, 'No presets')])]),
                ]),
            ])
            : h('div', { class: 'mb-4 text-muted' }, 'Presets will be available when poster and metadata are ready.'),
        h('h5', { class: 'mb-2' }, 'Meta'),
        h(
            'ul',
            { class: 'mb-0' },
            metaEntries.length > 0
                ? metaEntries.map(([key, value]) => h('li', [h('strong', key + ': '), vm.formatMetaValue(value)]))
                : [h('li', [h('em', 'No meta data')])]
        ),
    ]);
}

