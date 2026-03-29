import { ref } from 'vue';

export function createUploadTabState() {
    return {
        cleanup: function noop() {},
        uppyReady: ref(false),
    };
}
