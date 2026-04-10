import { ref } from 'vue';

export function createUploadTabState() {
    return {
        cleanup: function noop() {},
        updateStorage: function noop() {},
        uppyReady: ref(false),
    };
}
