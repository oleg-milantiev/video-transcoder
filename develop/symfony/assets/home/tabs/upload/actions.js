import { initHomeLegacyWidgets } from '../../legacyHomeWidgets.js';

export function createUploadTabActions(config, uploadState) {
    function mountUploadWidgets() {
        uploadState.cleanup = initHomeLegacyWidgets(config);
    }

    function unmountUploadWidgets() {
        uploadState.cleanup();
        uploadState.cleanup = function noop() {};
    }

    return {
        mountUploadWidgets,
        unmountUploadWidgets,
    };
}

