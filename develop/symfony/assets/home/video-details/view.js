import { defineComponent, onBeforeUnmount, onMounted } from 'vue';
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

            onBeforeUnmount(function () {
                unbindRealtime();
            });

            return {
                dto: state.dto,
                loading: state.loading,
                error: state.error,
                actionError: state.actionError,
                activeActionKey: state.activeActionKey,
                startTranscode: actions.startTranscode,
                cancelTask: actions.cancelTask,
                taskDownloadUrl: actions.taskDownloadUrl,
                formatMetaValue: actions.formatMetaValue,
                goHome: actions.goHome,
                openRenameModal: actions.openRenameModal,
            };
        },
        render() {
            return renderVideoDetails(this);
        },
    });
}
