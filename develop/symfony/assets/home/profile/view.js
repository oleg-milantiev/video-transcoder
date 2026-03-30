import { defineComponent } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { renderProfile } from './render.js';
import { createProfileState } from './state.js';
import { createProfileActions } from './actions.js';

export function createProfileView(config) {
    return defineComponent({
        name: 'ProfileView',
        setup() {
            const route = useRoute();
            const router = useRouter();
            const state = createProfileState();
            const actions = createProfileActions({
                config,
                route,
                router,
                state,
            });

            return {
                config,
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
            return renderProfile(this);
        },
    });
}
