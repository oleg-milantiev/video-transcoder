import { defineComponent, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { renderHomeTabs } from './HomeTabsRender.js';
import { createUploadTabState } from './tabs/upload/state.js';
import { createUploadTabActions } from './tabs/upload/actions.js';
import { createVideosTabState } from './tabs/videos/state.js';
import { createVideosTabActions } from './tabs/videos/actions.js';
import { createTasksTabState } from './tabs/tasks/state.js';
import { createTasksTabActions, isTaskActive } from './tabs/tasks/actions.js';
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
            let unbindRealtime = function noop() {};
            const pageLimit = 10;
            const uploadState = createUploadTabState();
            const uploadActions = createUploadTabActions(config, uploadState);

            const videosState = createVideosTabState(pageLimit);
            const videosActions = createVideosTabActions({
                config,
                router,
                videosState,
                pageLimit,
            });

            const tasksState = createTasksTabState(pageLimit);
            const tasksActions = createTasksTabActions({
                config,
                tasksState,
                pageLimit,
            });

            function ensureTabDataLoaded(tab) {
                if (tab === 'videos') {
                    videosActions.ensureVideosLoaded();
                }

                if (tab === 'tasks') {
                    tasksActions.ensureTasksLoaded();
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
                uploadActions.mountUploadWidgets();
                ensureTabDataLoaded(initialTab);
                syncTabToRoute(initialTab);
                unbindRealtime = bindHomeRealtime({
                    onTask: tasksActions.applyTaskRealtimeUpdate,
                    onVideo: videosActions.applyVideoRealtimeUpdate,
                });
            });

            onBeforeUnmount(function () {
                uploadActions.unmountUploadWidgets();
                unbindRealtime();
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

            return {
                activeTab,
                setTab,
                uppyReady: uploadState.uppyReady,
                userIdentifier: config.user ? config.user.identifier : '',
                videos: videosState.videos,
                videosMeta: videosState.videosMeta,
                videosLoading: videosState.videosLoading,
                videosError: videosState.videosError,
                tasks: tasksState.tasks,
                tasksMeta: tasksState.tasksMeta,
                tasksLoading: tasksState.tasksLoading,
                tasksError: tasksState.tasksError,
                loadVideos: videosActions.loadVideos,
                loadTasks: tasksActions.loadTasks,
                openVideoDetails: videosActions.openVideoDetails,
                deleteVideo: videosActions.deleteVideo,
                videoDeletePending: videosState.videoDeletePending,
                getTaskDownloadUrl: tasksActions.getTaskDownloadUrl,
                cancelTask: tasksActions.cancelTask,
                taskActionKey: tasksState.taskActionKey,
                isTaskActive,
            };
        },
        render() {
            return renderHomeTabs(this);
        },
    });
}
