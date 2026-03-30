import { ref } from 'vue';

export function createProfileState() {
    return {
        dto: ref(null),
        loading: ref(false),
        error: ref(''),
        actionError: ref(''),
        activeActionKey: ref(''),
    };
}
