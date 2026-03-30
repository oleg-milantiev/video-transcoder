import { defineComponent } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { renderProfile } from './render.js';
import { createProfileState } from './state.js';

export function createProfileView(config) {
    return defineComponent({
        name: 'ProfileView',
        setup() {
            const state = createProfileState();

            return {
                config,
                dto: state.dto,
                loading: state.loading,
                error: state.error,
                actionError: state.actionError,
                activeActionKey: state.activeActionKey,
            };
        },
        render() {
            return renderProfile(this);
        },
    });
}
