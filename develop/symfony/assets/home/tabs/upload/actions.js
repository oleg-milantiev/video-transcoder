import { initHomeLegacyWidgets } from '../../legacyHomeWidgets.js';

const UPPY_POLL_INTERVAL_MS  = 100;
const UPPY_POLL_MAX_ATTEMPTS = 100; // wait at most 10 seconds

// Uppy CDN coordinates — must match home/index.html.twig
const UPPY_VERSION  = 'v3.21.0';
const UPPY_BASE_URL = `https://releases.transloadit.com/uppy/${UPPY_VERSION}`;
const UPPY_CSS_HREF = `${UPPY_BASE_URL}/uppy.min.css`;
const UPPY_JS_SRC   = `${UPPY_BASE_URL}/uppy.min.js`;

/**
 * Idempotently inject Uppy CSS + JS into <head> when they were not pre-loaded
 * (e.g. the SPA was bootstrapped from /video/:uuid, not from the home page).
 */
function ensureUppyAssets() {
    if (!document.querySelector(`link[href="${UPPY_CSS_HREF}"]`)) {
        const link = document.createElement('link');
        link.rel  = 'stylesheet';
        link.href = UPPY_CSS_HREF;
        document.head.appendChild(link);
    }
    if (!document.querySelector(`script[src="${UPPY_JS_SRC}"]`)) {
        const script = document.createElement('script');
        script.src = UPPY_JS_SRC;
        document.head.appendChild(script);
    }
}

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

            // Uppy scripts were not pre-loaded (e.g. navigation from /video/:uuid).
            // Inject them dynamically so the poll below can pick them up.
            ensureUppyAssets();

            // Poll every 100 ms until Uppy appears or we time out.
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
