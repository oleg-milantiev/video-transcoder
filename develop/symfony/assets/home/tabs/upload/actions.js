import { initHomeLegacyWidgets } from '../../legacyHomeWidgets.js';

const UPPY_POLL_INTERVAL_MS  = 100;
const UPPY_POLL_MAX_ATTEMPTS = 100; // wait at most 10 seconds

export function createUploadTabActions(config, uploadState) {
    let pollTimer = null;

    function stopPolling() {
        if (pollTimer !== null) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function doInit() {
        const widgets = initHomeLegacyWidgets(config);
        uploadState.cleanup = widgets.cleanup;
        uploadState.updateStorage = widgets.updateStorage;
        uploadState.uppyReady.value = true;
    }

    function mountUploadWidgets() {
        // First rAF: let Vue finish rendering #drag-drop-area into the DOM.
        requestAnimationFrame(function () {
            // Happy path: Uppy already available (normal page load / warm cache).
            if (typeof window.Uppy !== 'undefined') {
                doInit();
                return;
            }

            // Uppy CDN script was injected by Turbo Drive into <head> but hasn't
            // finished downloading yet — poll every 100 ms until it appears.
            let attempts = 0;
            pollTimer = setInterval(function () {
                attempts++;

                if (typeof window.Uppy !== 'undefined') {
                    stopPolling();
                    doInit();
                    return;
                }

                if (attempts >= UPPY_POLL_MAX_ATTEMPTS) {
                    stopPolling();
                    console.warn('[upload] Uppy did not load within '
                        + (UPPY_POLL_MAX_ATTEMPTS * UPPY_POLL_INTERVAL_MS) + 'ms');
                    // Remove spinner so the user isn't stuck in a loading state.
                    uploadState.uppyReady.value = true;
                }
            }, UPPY_POLL_INTERVAL_MS);
        });
    }

    function unmountUploadWidgets() {
        // Cancel any in-flight polling if the user navigates away before Uppy loads.
        stopPolling();
        uploadState.cleanup();
        uploadState.cleanup = function noop() {};
        uploadState.updateStorage = function noop() {};
        uploadState.uppyReady.value = false;
    }

    return {
        mountUploadWidgets,
        unmountUploadWidgets,
    };
}
