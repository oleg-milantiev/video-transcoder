import { defineComponent, h, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useRoute } from 'vue-router';
import { initHomeLegacyWidgets } from './legacyHomeWidgets.js';

function createAuthHeader(apiBearerToken) {
    return apiBearerToken ? { Authorization: 'Bearer ' + apiBearerToken } : {};
}

function normalizeListResponse(payload, page, limit) {
    if (!payload || typeof payload !== 'object') {
        return {
            items: [],
            total: 0,
            page,
            limit,
            totalPages: 1,
        };
    }

    if (Array.isArray(payload)) {
        return {
            items: payload,
            total: payload.length,
            page,
            limit,
            totalPages: 1,
        };
    }

    return {
        items: Array.isArray(payload.items) ? payload.items : [],
        total: Number.isInteger(payload.total) ? payload.total : 0,
        page: Number.isInteger(payload.page) ? payload.page : page,
        limit: Number.isInteger(payload.limit) ? payload.limit : limit,
        totalPages: Number.isInteger(payload.totalPages) ? payload.totalPages : 1,
    };
}

function buildPageUrl(baseUrl, page, limit) {
    const url = new URL(baseUrl, window.location.origin);
    url.searchParams.set('page', String(page));
    url.searchParams.set('limit', String(limit));

    return url.toString();
}

export function createHomeTabsView(config) {
    return defineComponent({
        name: 'HomeTabsView',
        setup() {
            const router = useRouter();
            const route = useRoute();
            const allowedTabs = ['upload', 'videos', 'tasks'];
            const queryTab = typeof route.query.tab === 'string' ? route.query.tab : '';
            const initialTab = allowedTabs.includes(queryTab) ? queryTab : 'upload';
            const activeTab = ref(initialTab);
            let cleanup = function noop() {};
            const pageLimit = 10;
            const authHeader = createAuthHeader(config.apiBearerToken || null);

            const videos = ref([]);
            const videosMeta = ref({ page: 1, limit: pageLimit, total: 0, totalPages: 1 });
            const videosLoading = ref(false);
            const videosError = ref('');
            const videosLoaded = ref(false);

            const tasks = ref([]);
            const tasksMeta = ref({ page: 1, limit: pageLimit, total: 0, totalPages: 1 });
            const tasksLoading = ref(false);
            const tasksError = ref('');
            const tasksLoaded = ref(false);

            async function fetchList(url, page, limit) {
                const response = await fetch(buildPageUrl(url, page, limit), {
                    method: 'GET',
                    headers: authHeader,
                });

                if (!response.ok) {
                    throw new Error('Failed to load list');
                }

                return normalizeListResponse(await response.json(), page, limit);
            }

            async function loadVideos(page = 1) {
                videosLoading.value = true;
                videosError.value = '';

                try {
                    const payload = await fetchList(config.apiVideoListUrl, page, pageLimit);
                    videos.value = payload.items;
                    videosMeta.value = {
                        page: payload.page,
                        limit: payload.limit,
                        total: payload.total,
                        totalPages: Math.max(1, payload.totalPages),
                    };
                } catch (e) {
                    videos.value = [];
                    videosError.value = 'Failed to load videos';
                } finally {
                    videosLoading.value = false;
                    videosLoaded.value = true;
                }
            }

            async function loadTasks(page = 1) {
                tasksLoading.value = true;
                tasksError.value = '';

                try {
                    const payload = await fetchList(config.apiTaskListUrl, page, pageLimit);
                    tasks.value = payload.items;
                    tasksMeta.value = {
                        page: payload.page,
                        limit: payload.limit,
                        total: payload.total,
                        totalPages: Math.max(1, payload.totalPages),
                    };
                } catch (e) {
                    tasks.value = [];
                    tasksError.value = 'Failed to load tasks';
                } finally {
                    tasksLoading.value = false;
                    tasksLoaded.value = true;
                }
            }

            onMounted(function () {
                cleanup = initHomeLegacyWidgets(config);

                if (initialTab === 'videos') {
                    void loadVideos(1);
                }

                if (initialTab === 'tasks') {
                    void loadTasks(1);
                }
            });

            onBeforeUnmount(function () {
                cleanup();
            });

            function setTab(tab) {
                activeTab.value = tab;

                if (tab === 'videos' && !videosLoading.value && !videosLoaded.value) {
                    void loadVideos(1);
                }

                if (tab === 'tasks' && !tasksLoading.value && !tasksLoaded.value) {
                    void loadTasks(1);
                }
            }

            function openVideoDetails(uuid) {
                void router.push(config.videoDetailsUrlTemplate.replace('__UUID__', uuid));
            }

            function getTaskDownloadUrl(taskId) {
                return config.taskDownloadUrlTemplate.replace('__TASK_ID__', String(taskId));
            }

            return {
                activeTab,
                setTab,
                userIdentifier: config.userIdentifier,
                videos,
                videosMeta,
                videosLoading,
                videosError,
                tasks,
                tasksMeta,
                tasksLoading,
                tasksError,
                loadVideos,
                loadTasks,
                openVideoDetails,
                getTaskDownloadUrl,
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
                        this.videosError ? h('div', { class: 'alert alert-danger' }, this.videosError) : null,
                        this.videosLoading ? h('p', { class: 'mb-2 text-muted' }, 'Loading videos...') : null,
                        h('table', { id: 'videosTable', class: 'table table-striped w-100 align-middle' }, [
                            h('thead', [
                                h('tr', [h('th', 'Poster'), h('th', 'Name'), h('th', 'Status'), h('th', 'Created')]),
                            ]),
                            h(
                                'tbody',
                                this.videos.length > 0
                                    ? this.videos.map((video) =>
                                          h(
                                              'tr',
                                              {
                                                  style: 'cursor:pointer;',
                                                  onClick: () => this.openVideoDetails(video.uuid),
                                              },
                                              [
                                                  h('td', [
                                                      video.poster
                                                          ? h('img', {
                                                                src: '/uploads/' + video.poster,
                                                                alt: 'poster',
                                                                style: 'width:120px;max-width:100%;height:auto;object-fit:cover;border-radius:6px;',
                                                            })
                                                          : h(
                                                                'div',
                                                                {
                                                                    style: 'width:120px;height:68px;background:#eee;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:12px;border-radius:6px;',
                                                                },
                                                                'No poster'
                                                            ),
                                                  ]),
                                                  h('td', video.title || '-'),
                                                  h('td', video.status || '-'),
                                                  h('td', video.createdAt || '-'),
                                              ]
                                          )
                                      )
                                    : [h('tr', [h('td', { colspan: '4', class: 'text-muted text-center' }, 'No videos')])]
                            ),
                        ]),
                        h('div', { class: 'd-flex justify-content-between align-items-center' }, [
                            h(
                                'button',
                                {
                                    type: 'button',
                                    class: 'btn btn-outline-secondary btn-sm',
                                    disabled: this.videosMeta.page <= 1 || this.videosLoading,
                                    onClick: () => this.loadVideos(this.videosMeta.page - 1),
                                },
                                'Prev'
                            ),
                            h(
                                'span',
                                { class: 'text-muted small' },
                                'Page ' + this.videosMeta.page + ' / ' + this.videosMeta.totalPages + ' (total ' + this.videosMeta.total + ')'
                            ),
                            h(
                                'button',
                                {
                                    type: 'button',
                                    class: 'btn btn-outline-secondary btn-sm',
                                    disabled: this.videosMeta.page >= this.videosMeta.totalPages || this.videosLoading,
                                    onClick: () => this.loadVideos(this.videosMeta.page + 1),
                                },
                                'Next'
                            ),
                        ]),
                    ]),
                    h('div', { class: paneClass('tasks') }, [
                        this.tasksError ? h('div', { class: 'alert alert-danger' }, this.tasksError) : null,
                        this.tasksLoading ? h('p', { class: 'mb-2 text-muted' }, 'Loading tasks...') : null,
                        h('table', { id: 'tasksTable', class: 'table table-striped w-100 align-middle' }, [
                            h('thead', [
                                h('tr', [h('th', 'Video'), h('th', 'Preset'), h('th', 'Status'), h('th', 'Progress'), h('th', 'Created'), h('th', 'Actions')]),
                            ]),
                            h(
                                'tbody',
                                this.tasks.length > 0
                                    ? this.tasks.map((task) =>
                                          h('tr', [
                                              h('td', task.videoTitle || '-'),
                                              h('td', task.presetTitle || '-'),
                                              h('td', task.status || '-'),
                                              h('td', task.progress || '-'),
                                              h('td', task.createdAt || '-'),
                                              h('td', [
                                                  task.status === 'COMPLETED' && task.id
                                                      ? h(
                                                            'a',
                                                            {
                                                                href: this.getTaskDownloadUrl(task.id),
                                                                class: 'btn btn-outline-primary btn-sm',
                                                                download: '',
                                                            },
                                                            'Download'
                                                        )
                                                      : h('span', { class: 'text-muted' }, '-'),
                                              ]),
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
                                    disabled: this.tasksMeta.page <= 1 || this.tasksLoading,
                                    onClick: () => this.loadTasks(this.tasksMeta.page - 1),
                                },
                                'Prev'
                            ),
                            h(
                                'span',
                                { class: 'text-muted small' },
                                'Page ' + this.tasksMeta.page + ' / ' + this.tasksMeta.totalPages + ' (total ' + this.tasksMeta.total + ')'
                            ),
                            h(
                                'button',
                                {
                                    type: 'button',
                                    class: 'btn btn-outline-secondary btn-sm',
                                    disabled: this.tasksMeta.page >= this.tasksMeta.totalPages || this.tasksLoading,
                                    onClick: () => this.loadTasks(this.tasksMeta.page + 1),
                                },
                                'Next'
                            ),
                        ]),
                    ]),
                ]),
            ]);
        },
    });
}


