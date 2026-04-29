import { defineComponent, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { renderHomeTabs } from './HomeTabsRender.js';
import { createUploadTabState } from './tabs/upload/state.js';
import { createUploadTabActions } from './tabs/upload/actions.js';
import { createVideosTabState } from './tabs/videos/state.js';
import { createVideosTabActions } from './tabs/videos/actions.js';
import { createTasksTabState } from './tabs/tasks/state.js';
import { createTasksTabActions } from './tabs/tasks/actions.js';
import { bindHomeRealtime } from './realtime/bindHomeRealtime.js';

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
            const queryPage = typeof route.query.page === 'string' ? parseInt(route.query.page, 10) : 1;
            const initialPage = queryPage > 0 ? queryPage : 1;
            let unbindRealtime = function noop() {};
            const pageLimitVideos = 10;
            const pageLimitTasks = 20;
            const uploadState = createUploadTabState();
            const uploadActions = createUploadTabActions(config, uploadState);
            const tariff = ref(config.tariff || null);
            const videosState = createVideosTabState(pageLimitVideos);
            const videosActions = createVideosTabActions({
                config,
                router,
                videosState,
                pageLimit: pageLimitVideos,
            });

            const tasksState = createTasksTabState(pageLimitTasks);
            const tasksActions = createTasksTabActions({
                config,
                tasksState,
                pageLimit: pageLimitTasks,
            });

            function ensureTabDataLoaded(tab) {
                if (tab === 'videos') {
                    if (videosState.videosLoading.value) { return; }
                    const page = videosState.videosLoaded.value ? videosState.videosMeta.value.page : initialPage;
                    void loadVideosSync(page);
                }

                if (tab === 'tasks') {
                    if (tasksState.tasksLoading.value) { return; }
                    const page = tasksState.tasksLoaded.value ? tasksState.tasksMeta.value.page : initialPage;
                    void loadTasksSync(page);
                }
            }

            function syncTabToRoute(tab, page) {
                const currentTab = typeof route.query.tab === 'string' ? route.query.tab : '';
                const currentPage = typeof route.query.page === 'string' ? route.query.page : '';
                const newPage = page ? String(page) : '';
                if (currentTab === tab && currentPage === newPage) {
                    return;
                }

                const newQuery = { ...route.query, tab };
                if (newPage) {
                    newQuery.page = newPage;
                } else {
                    delete newQuery.page;
                }

                void router.replace({
                    path: route.path,
                    query: newQuery,
                });
            }

            async function loadVideosSync(page) {
                await videosActions.loadVideos(page);
                syncTabToRoute('videos', videosState.videosMeta.value.page);
            }

            async function loadTasksSync(page) {
                await tasksActions.loadTasks(page);
                syncTabToRoute('tasks', tasksState.tasksMeta.value.page);
            }

            onMounted(function () {
                uploadActions.mountUploadWidgets();
                ensureTabDataLoaded(initialTab);
                // Normalize tab in URL without clearing the page param
                const currentTab = typeof route.query.tab === 'string' ? route.query.tab : '';
                if (currentTab !== initialTab) {
                    void router.replace({
                        path: route.path,
                        query: { ...route.query, tab: initialTab },
                    });
                }
                unbindRealtime = bindHomeRealtime({
                    onTask: tasksActions.applyTaskRealtimeUpdate,
                    onVideo: function (msg) {
                        if (msg.action === 'uploaded') {
                            void videosActions.applyVideoUploaded(msg.payload);
                        } else {
                            videosActions.applyVideoRealtimeUpdate(msg.payload);
                        }
                    },
                    onStorage: function (payload) {
                        // update uppy restriction
                        uploadState.updateStorage(payload.storageNow, payload.storageMax);
                        // update reactive tariff so hint re-renders
                        if (tariff.value) {
                            tariff.value = {
                                ...tariff.value,
                                storage: {
                                    ...tariff.value.storage,
                                    now: payload.storageNow,
                                    max: payload.storageMax,
                                },
                            };
                        }
                    },
                });
            });

            onBeforeUnmount(function () {
                uploadActions.unmountUploadWidgets();
                unbindRealtime();
            });

            function setTab(tab) {
                const normalizedTab = normalizeTab(tab);
                if (activeTab.value === normalizedTab) {
                    return;
                }

                activeTab.value = normalizedTab;
                ensureTabDataLoaded(normalizedTab);
                // Sync tab to URL immediately (videos/tasks will update page when data loads)
                syncTabToRoute(normalizedTab, null);
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

            return {
                activeTab,
                setTab,
                uppyReady: uploadState.uppyReady,
                tariff,
                userIdentifier: config.user ? config.user.identifier : '',
                videos: videosState.videos,
                videosMeta: videosState.videosMeta,
                videosLoading: videosState.videosLoading,
                videosError: videosState.videosError,
                tasks: tasksState.tasks,
                tasksMeta: tasksState.tasksMeta,
                tasksLoading: tasksState.tasksLoading,
                tasksError: tasksState.tasksError,
                loadVideos: loadVideosSync,
                loadTasks: loadTasksSync,
                openVideoDetails: videosActions.openVideoDetails,
                deleteVideo: videosActions.deleteVideo,
                videoDeletePending: videosState.videoDeletePending,
                taskActions: tasksActions.taskActions,
            };
        },
        render() {
            return renderHomeTabs(this);
        },
    });
}
