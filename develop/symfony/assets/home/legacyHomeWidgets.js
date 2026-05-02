import { ROUTE_UPLOAD } from './routes.js';

function createAuthHeader(apiBearerToken) {
    return apiBearerToken ? { Authorization: 'Bearer ' + apiBearerToken } : {};
}

export function initHomeLegacyWidgets(config) {
    if (typeof window.Uppy === 'undefined') {
        console.warn('[upload] Uppy is not loaded – skipping widget init');
        return { cleanup: function noop() {}, updateStorage: function noop() {} };
    }

    const dropArea = document.getElementById('drag-drop-area');
    if (!dropArea) {
        console.warn('[upload] #drag-drop-area not found – skipping widget init');
        return { cleanup: function noop() {}, updateStorage: function noop() {} };
    }

    const apiBearerToken = config.token ? (config.token.access || null) : null;
    const authHeader = createAuthHeader(apiBearerToken);

    let maxFileSize = Math.min(
        config.tariff.storage.max - config.tariff.storage.now,
        parseFloat(config.tariff.videoSize) * 1024 * 1024
    );

    const uppyConfig = {
        autoProceed: true,
        restrictions: {
            allowedFileTypes: ['.mp4', '.mkv', '.avi', '.mov', '.3gp'],
        },
    };

    if (maxFileSize !== null) {
        uppyConfig.restrictions.maxFileSize = maxFileSize;
    }

    const uppy = new window.Uppy.Uppy(uppyConfig)
        .use(window.Uppy.Dashboard, {
            inline: true,
            target: dropArea,
            proudlyDisplayPoweredByUppy: false,
        })
        .use(window.Uppy.Tus, {
            endpoint: ROUTE_UPLOAD,
            chunkSize: 5 * 1024 * 1024,
            headers: function () {
                return authHeader;
            },
        });

    function cleanup() {
        try {
            uppy.close();
        } catch (e) {
            // Ignore cleanup errors from third-party widgets.
        }
    }

    function updateStorage(storageNow, storageMax) {
        const videoSizeBytes = parseFloat(config.tariff.videoSize) * 1024 * 1024;
        const remaining = storageMax - storageNow;
        const newMaxFileSize = Math.min(remaining, videoSizeBytes);
        try {
            uppy.setOptions({ restrictions: { maxFileSize: newMaxFileSize } });
        } catch (e) {
            // ignore
        }
    }

    return { cleanup, updateStorage };
}
