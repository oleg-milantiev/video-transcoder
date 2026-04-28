import { defineComponent, onBeforeUnmount, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { renderVideoDetails } from './render.js';
import { createVideoDetailsState } from './state.js';
import { createVideoDetailsActions } from './actions.js';
import { bindVideoDetailsRealtime } from './realtime.js';

export function createVideoDetailsView(config) {
    return defineComponent({
        name: 'VideoDetailsView',
        setup() {
            const route = useRoute();
            const router = useRouter();
            const state = createVideoDetailsState();
            const actions = createVideoDetailsActions({
                config,
                route,
                router,
                state,
            });
            let unbindRealtime = function noop() {};

            onMounted(function () {
                void actions.loadDetails();
                unbindRealtime = bindVideoDetailsRealtime({
                    onTask: actions.applyTaskRealtimeUpdate,
                    onVideo: actions.applyVideoRealtimeUpdate,
                });
            });

            watch(() => route.params.uuid, (newUuid, oldUuid) => {
                if (newUuid && newUuid !== oldUuid) {
                    state.activeTab.value = 'info';
                    void actions.loadDetails();
                }
            });

            onBeforeUnmount(function () {
                unbindRealtime();
            });

            return {
                config,
                dto: state.dto,
                loading: state.loading,
                error: state.error,
                actionError: state.actionError,
                activeActionKey: actions.taskActions.activeKey,
                taskActions: actions.taskActions,
                // video list (left pane)
                videoListItems: state.videoListItems,
                videoListMeta: state.videoListMeta,
                videoListLoading: state.videoListLoading,
                // tab state
                activeTab: state.activeTab,
                setActiveTab: (tab) => { state.activeTab.value = tab; },
                // actions
                startTranscode: actions.startTranscode,
                cancelTask: actions.cancelTask,
                taskDownloadUrl: actions.taskDownloadUrl,
                formatMetaValue: actions.formatMetaValue,
                goHome: actions.goHome,
                closeDetails: actions.closeDetails,
                navigateToTab: actions.navigateToTab,
                openVideoDetails: actions.openVideoDetails,
                loadVideoList: actions.loadVideoList,
                openRenameModal: actions.openRenameModal,
            };
        },
        render() {
            return renderVideoDetails(this);
        },
    });
}
