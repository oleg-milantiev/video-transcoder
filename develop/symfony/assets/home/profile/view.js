import { defineComponent } from 'vue';
import { useRouter } from 'vue-router';
import { renderProfile } from './render.js';
import { createProfileState } from './state.js';

export function createProfileView(config) {
    return defineComponent({
        name: 'ProfileView',
        setup() {
            const router = useRouter();
            const state = createProfileState();

            function goHome() {
                void router.push({
                    path: config.route?.home ?? '/',
                });
            }

            return {
                config,
                dto: state.dto,
                loading: state.loading,
                error: state.error,
                actionError: state.actionError,
                activeActionKey: state.activeActionKey,
                goHome,
            };
        },
        render() {
            return renderProfile(this);
        },
    });
}
