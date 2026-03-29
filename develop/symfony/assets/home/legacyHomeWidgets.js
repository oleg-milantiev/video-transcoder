function uuidv4() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
        const r = crypto.getRandomValues(new Uint8Array(1))[0] & 15;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

function createAuthHeader(apiBearerToken) {
    return apiBearerToken ? { Authorization: 'Bearer ' + apiBearerToken } : {};
}

export function initHomeLegacyWidgets(config) {
    if (typeof window.Uppy === 'undefined') {
        return function noop() {};
    }

    const apiBearerToken = config.token ? (config.token.access || null) : null;
    const authHeader = createAuthHeader(apiBearerToken);

    const maxFileSize = (config.tariff && config.tariff.videoSize) ? parseFloat(config.tariff.videoSize) * 1024 * 1024 : null;

    const uppyConfig = {
        autoProceed: true,
        restrictions: {
            allowedFileTypes: ['.mp4', '.mkv', '.avi', '.mov'],
        },
    };

    if (maxFileSize !== null) {
        uppyConfig.restrictions.maxFileSize = maxFileSize;
    }

    const uppy = new window.Uppy.Uppy(uppyConfig)
        .use(window.Uppy.Dashboard, {
            inline: true,
            target: '#drag-drop-area',
            proudlyDisplayPoweredByUppy: false,
        })
        .use(window.Uppy.Tus, {
            endpoint: config.route.upload,
            chunkSize: 5 * 1024 * 1024,
            headers: function () {
                return authHeader;
            },
        });

    // Display tariff limit warning
    if (maxFileSize !== null && maxFileSize > 0) {
        const warningMessage = `Your tariff allows uploading videos up to ${Math.round(maxFileSize)} MB.`;
        uppy.info(warningMessage, 'info', 5000);
    }

    uppy.on('file-added', function (file) {
        const uuid = uuidv4();
        const ext = file.name.split('.').pop().toLowerCase();

        uppy.setFileMeta(file.id, {
            name: uuid + '.' + ext,
            originalName: file.name,
        });
    });

    return function cleanup() {
        try {
            uppy.close();
        } catch (e) {
            // Ignore cleanup errors from third-party widgets.
        }
    };
}
