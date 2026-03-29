import { initHomeLegacyWidgets } from '../../legacyHomeWidgets.js';

export function createUploadTabActions(config, uploadState) {
    function mountUploadWidgets() {
        // Defer Uppy initialisation to the next animation frame so the browser
        // has completed layout for the current render before we hand the DOM
        // element off to Uppy (which measures the container on mount).
        requestAnimationFrame(function () {
            uploadState.cleanup = initHomeLegacyWidgets(config);
            uploadState.uppyReady.value = true;
        });
    }

    function unmountUploadWidgets() {
        uploadState.cleanup();
        uploadState.cleanup = function noop() {};
        uploadState.uppyReady.value = false;
    }

    return {
        mountUploadWidgets,
        unmountUploadWidgets,
    };
}
