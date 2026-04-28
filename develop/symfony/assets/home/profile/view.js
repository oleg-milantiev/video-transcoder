import { defineComponent, onMounted } from 'vue';
import { renderProfile } from './render.js';
import { createProfileState } from './state.js';
import { createProfileActions } from './actions.js';

export function createProfileView(config) {
    return defineComponent({
        name: 'ProfileView',
        setup() {
            const state = createProfileState();
            const actions = createProfileActions({ config, profileState: state });

            function goHome() {
                window.location.href = '/';
            }

            onMounted(() => {
                actions.fetchProfile();
            });

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
