import { h } from 'vue';
import { renderUploadPane } from './tabs/upload/render.js';
import { renderVideosPane } from './tabs/videos/render.js';
import { renderTasksPane } from './tabs/tasks/render.js';

function renderTabButton(vm, id, label) {
    return h('li', { class: 'nav-item', role: 'presentation' }, [
        h(
            'button',
            {
                type: 'button',
                class: vm.activeTab === id ? 'nav-link active' : 'nav-link',
                onClick: () => vm.setTab(id),
            },
            label
        ),
    ]);
}

function paneClass(vm, id) {
    return vm.activeTab === id ? 'tab-pane fade show active' : 'tab-pane fade';
}

export function renderHomeTabs(vm) {
    return h('div', {}, [
        h('h1', { class: 'mb-4' }, 'Video Transcoder'),
        h('p', {}, ['You are logged in as ', h('strong', {}, vm.userIdentifier), '.']),
        h('ul', { class: 'nav nav-tabs', role: 'tablist' }, [
            renderTabButton(vm, 'upload', 'Upload'),
            renderTabButton(vm, 'videos', 'Videos'),
            renderTabButton(vm, 'tasks', 'Tasks'),
        ]),
        h('div', { class: 'tab-content border border-top-0 p-4 bg-light' }, [
            renderUploadPane(paneClass(vm, 'upload'), vm.uppyReady),
            renderVideosPane(vm, paneClass(vm, 'videos')),
            renderTasksPane(vm, paneClass(vm, 'tasks')),
        ]),
    ]);
}
