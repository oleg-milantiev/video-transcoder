import { h } from 'vue';

function createTaskAction(vm, preset) {
    const task = preset.task;

    if (!task || !task.id) {
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

    if (task.status === 'COMPLETED') {
        return h(
            'a',
            {
                href: vm.taskDownloadUrl(task.id),
                class: 'btn btn-outline-primary btn-sm',
                download: '',
            },
            'Download'
        );
    }

    if (task.status === 'PENDING' || task.status === 'PROCESSING') {
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
            h('td', task ? task.status : h('em', 'No task')),
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
                    ? h('img', {
                          src: '/uploads/' + vm.dto.poster,
                          class: 'img-fluid mb-3 rounded',
                          alt: vm.dto.title,
                          style: 'max-width: 520px;',
                      })
                    : null,
                h('dl', { class: 'row mb-0' }, [
                    h('dt', { class: 'col-sm-3' }, 'Title'),
                    h('dd', { class: 'col-sm-9' }, vm.dto.title),
                    h('dt', { class: 'col-sm-3' }, 'Extension'),
                    h('dd', { class: 'col-sm-9' }, vm.dto.extension),
                    h('dt', { class: 'col-sm-3' }, 'Created At'),
                    h('dd', { class: 'col-sm-9' }, vm.dto.createdAt),
                    h('dt', { class: 'col-sm-3' }, 'Updated At'),
                    h('dd', { class: 'col-sm-9' }, vm.dto.updatedAt || '-'),
                    h('dt', { class: 'col-sm-3' }, 'User ID'),
                    h('dd', { class: 'col-sm-9' }, String(vm.dto.userId)),
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

