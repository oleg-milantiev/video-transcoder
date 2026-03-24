import { h } from 'vue';

function renderTaskAction(vm, task) {
    if (task.status === 'COMPLETED' && task.id) {
        return h(
            'a',
            {
                href: vm.getTaskDownloadUrl(task.id),
                class: 'btn btn-outline-primary btn-sm',
                download: '',
            },
            'Download'
        );
    }

    if (vm.isTaskActive(task.status) && task.id) {
        return h(
            'button',
            {
                type: 'button',
                class: 'btn btn-outline-primary btn-sm',
                disabled: vm.taskActionKey === 'cancel-' + String(task.id),
                onClick: () => vm.cancelTask(task.id),
            },
            vm.taskActionKey === 'cancel-' + String(task.id) ? 'Cancelling...' : 'Cancel'
        );
    }

    return h('span', { class: 'text-muted' }, '-');
}

export function renderTasksPane(vm, paneClass) {
    return h('div', { class: paneClass }, [
        vm.tasksError ? h('div', { class: 'alert alert-danger' }, vm.tasksError) : null,
        vm.tasksLoading ? h('p', { class: 'mb-2 text-muted' }, 'Loading tasks...') : null,
        h('table', { id: 'tasksTable', class: 'table table-striped w-100 align-middle' }, [
            h('thead', [
                h('tr', [h('th', 'Video'), h('th', 'Preset'), h('th', 'Status'), h('th', 'Progress'), h('th', 'Created'), h('th', 'Actions')]),
            ]),
            h(
                'tbody',
                vm.tasks.length > 0
                    ? vm.tasks.map((task) =>
                          h('tr', [
                              h('td', { class: task.deleted === true ? 'video-title-deleted' : '' }, task.videoTitle || '-'),
                              h('td', task.presetTitle || '-'),
                              h('td', task.status || '-'),
                              h('td', typeof task.progress === 'number' ? String(task.progress) + '%' : '-'),
                              h('td', task.createdAt || '-'),
                              h('td', [renderTaskAction(vm, task)]),
                          ])
                      )
                    : [h('tr', [h('td', { colspan: '6', class: 'text-muted text-center' }, 'No tasks')])]
            ),
        ]),
        h('div', { class: 'd-flex justify-content-between align-items-center' }, [
            h(
                'button',
                {
                    type: 'button',
                    class: 'btn btn-outline-secondary btn-sm',
                    disabled: vm.tasksMeta.page <= 1 || vm.tasksLoading,
                    onClick: () => vm.loadTasks(vm.tasksMeta.page - 1),
                },
                'Prev'
            ),
            h(
                'span',
                { class: 'text-muted small' },
                'Page ' + vm.tasksMeta.page + ' / ' + vm.tasksMeta.totalPages + ' (total ' + vm.tasksMeta.total + ')'
            ),
            h(
                'button',
                {
                    type: 'button',
                    class: 'btn btn-outline-secondary btn-sm',
                    disabled: vm.tasksMeta.page >= vm.tasksMeta.totalPages || vm.tasksLoading,
                    onClick: () => vm.loadTasks(vm.tasksMeta.page + 1),
                },
                'Next'
            ),
        ]),
    ]);
}

