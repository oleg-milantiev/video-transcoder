import { defineComponent, h, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
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

function toInt(value, fallback) {
    const parsed = Number.parseInt(String(value), 10);
    return Number.isFinite(parsed) ? parsed : fallback;
}

function isTaskActive(status) {
    return status === 'PENDING' || status === 'PROCESSING';
}

function isTaskMessage(message) {
    return message && typeof message === 'object' && message.entity === 'task' && message.payload && typeof message.payload === 'object';
}

export function createHomeTabsView(config) {
    return defineComponent({
        name: 'HomeTabsView',
        setup() {
            const router = useRouter();
            const route = useRoute();
            const allowedTabs = ['upload', 'videos', 'tasks'];
            const normalizeTab = (tab) => (allowedTabs.includes(tab) ? tab : 'upload');
            const queryTab = typeof route.query.tab === 'string' ? route.query.tab : '';
            const initialTab = normalizeTab(queryTab);
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
            const taskActionKey = ref('');
            const onTaskMessage = function (event) {
                const message = event.detail;
                if (!isTaskMessage(message)) {
                    return;
                }

                const update = message.payload;
                const taskId = typeof update.taskId === 'string' ? update.taskId : '';
                if (!taskId) {
                    return;
                }

                tasks.value = tasks.value.map((task) => {
                    if (String(task.id) !== taskId) {
                        return task;
                    }

                    return {
                        ...task,
                        status: typeof update.status === 'string' ? update.status : task.status,
                        progress: toInt(update.progress, task.progress),
                    };
                });
            };

            const onVideoMessage = function (event) {
                const message = event.detail;
                if (!message || typeof message !== 'object' || message.entity !== 'video') {
                    return;
                }

                const payload = message.payload || {};
                const videoId = typeof payload.videoId === 'string' ? payload.videoId : '';
                if (!videoId) {
                    return;
                }

                // update list item if present
                videos.value = videos.value.map((video) => {
                    if (String(video.id || video.uuid) !== videoId && String(video.uuid || video.id) !== videoId) {
                        return video;
                    }

                    return {
                        ...video,
                        status: typeof payload.status === 'string' ? payload.status : video.status,
                        poster: typeof payload.poster === 'string' ? payload.poster : video.poster,
                        meta: payload.meta || video.meta,
                        updatedAt: payload.updatedAt || video.updatedAt,
                    };
                });
            };

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

            async function cancelTask(taskId) {
                if (!taskId) {
                    return;
                }

                taskActionKey.value = 'cancel-' + String(taskId);

                try {
                    const url = config.apiTaskCancelUrlTemplate.replace('__TASK_ID__', String(taskId));
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: authHeader,
                    });

                    if (!response.ok) {
                        tasksError.value = 'Failed to cancel task';
                    }
                } catch (e) {
                    tasksError.value = 'Failed to cancel task';
                } finally {
                    taskActionKey.value = '';
                }
            }

            function ensureTabDataLoaded(tab) {
                if (tab === 'videos' && !videosLoading.value) {
                    const targetPage = videosLoaded.value ? videosMeta.value.page : 1;
                    void loadVideos(targetPage);
                }

                if (tab === 'tasks' && !tasksLoading.value) {
                    const targetPage = tasksLoaded.value ? tasksMeta.value.page : 1;
                    void loadTasks(targetPage);
                }
            }

            function syncTabToRoute(tab) {
                const currentTab = typeof route.query.tab === 'string' ? route.query.tab : '';
                if (currentTab === tab) {
                    return;
                }

                void router.replace({
                    path: route.path,
                    query: {
                        ...route.query,
                        tab,
                    },
                });
            }

            onMounted(function () {
                cleanup = initHomeLegacyWidgets(config);
                ensureTabDataLoaded(initialTab);
                syncTabToRoute(initialTab);
                window.addEventListener('app:task', onTaskMessage);
                window.addEventListener('app:video', onVideoMessage);
            });

            onBeforeUnmount(function () {
                cleanup();
                window.removeEventListener('app:task', onTaskMessage);
                window.removeEventListener('app:video', onVideoMessage);
            });

            function setTab(tab) {
                const normalizedTab = normalizeTab(tab);
                if (activeTab.value === normalizedTab) {
                    syncTabToRoute(normalizedTab);
                    return;
                }

                activeTab.value = normalizedTab;
                ensureTabDataLoaded(normalizedTab);
                syncTabToRoute(normalizedTab);
            }

            watch(
                () => route.query.tab,
                (tabFromQuery) => {
                    const nextTab = normalizeTab(typeof tabFromQuery === 'string' ? tabFromQuery : '');
                    if (nextTab === activeTab.value) {
                        return;
                    }

                    activeTab.value = nextTab;
                    ensureTabDataLoaded(nextTab);
                }
            );

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
                cancelTask,
                taskActionKey,
                isTaskActive,
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
                                              h('td', typeof task.progress === 'number' ? String(task.progress) + '%' : '-'),
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
                                                       : this.isTaskActive(task.status) && task.id
                                                           ? h(
                                                                 'button',
                                                                 {
                                                                     type: 'button',
                                                                     class: 'btn btn-outline-primary btn-sm',
                                                                     disabled: this.taskActionKey === 'cancel-' + String(task.id),
                                                                     onClick: () => this.cancelTask(task.id),
                                                                 },
                                                                 this.taskActionKey === 'cancel-' + String(task.id) ? 'Cancelling...' : 'Cancel'
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


