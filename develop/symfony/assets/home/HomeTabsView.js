import { defineComponent, h, onBeforeUnmount, onMounted, ref } from 'vue';
import { initHomeLegacyWidgets } from './legacyHomeWidgets.js';

export function createHomeTabsView(config) {
    return defineComponent({
        name: 'HomeTabsView',
        setup() {
            const activeTab = ref('upload');
            let cleanup = function noop() {};

            onMounted(function () {
                cleanup = initHomeLegacyWidgets(config);
            });

            onBeforeUnmount(function () {
                cleanup();
            });

            function setTab(tab) {
                activeTab.value = tab;
            }

            return {
                activeTab,
                setTab,
                userIdentifier: config.userIdentifier,
            };
        },
        render() {
            const tabBtn = (id, label) =>
                h('li', { class: 'nav-item', role: 'presentation' }, [
                    h(
                        'button',
                        {
                            type: 'button',
                            class: this.activeTab === id ? 'nav-link active' : 'nav-link',
                            onClick: () => this.setTab(id),
                        },
                        label
                    ),
                ]);

            const paneClass = (id) => (this.activeTab === id ? 'tab-pane fade show active' : 'tab-pane fade');

            return h('div', {}, [
                h('h1', { class: 'mb-4' }, 'Video Transcoder'),
                h('p', {}, ['You are logged in as ', h('strong', {}, this.userIdentifier), '.']),
                h('ul', { class: 'nav nav-tabs', role: 'tablist' }, [
                    tabBtn('upload', 'Upload'),
                    tabBtn('videos', 'Videos'),
                    tabBtn('tasks', 'Tasks'),
                ]),
                h('div', { class: 'tab-content border border-top-0 p-4 bg-light' }, [
                    h('div', { class: paneClass('upload') }, [h('div', { id: 'drag-drop-area' })]),
                    h('div', { class: paneClass('videos') }, [
                        h('table', { id: 'videosTable', class: 'table table-striped w-100' }, [
                            h('thead', {}, [
                                h('tr', {}, [
                                    h('th', {}, 'Poster'),
                                    h('th', {}, 'Name'),
                                    h('th', {}, 'Status'),
                                    h('th', {}, 'Created'),
                                ]),
                            ]),
                        ]),
                    ]),
                    h('div', { class: paneClass('tasks') }, [
                        h('table', { id: 'tasksTable', class: 'table table-striped w-100' }, [
                            h('thead', {}, [
                                h('tr', {}, [
                                    h('th', {}, 'Video'),
                                    h('th', {}, 'Preset'),
                                    h('th', {}, 'Status'),
                                    h('th', {}, 'Progress'),
                                    h('th', {}, 'Created'),
                                    h('th', {}, 'Actions'),
                                ]),
                            ]),
                        ]),
                    ]),
                ]),
            ]);
        },
    });
}


