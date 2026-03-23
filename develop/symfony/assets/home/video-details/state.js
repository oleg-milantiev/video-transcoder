import { ref } from 'vue';

export function createVideoDetailsState() {
    return {
        dto: ref(null),
        loading: ref(false),
        error: ref(''),
        actionError: ref(''),
        activeActionKey: ref(''),
    };
}

