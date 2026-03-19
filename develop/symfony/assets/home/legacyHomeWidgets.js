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

    const apiBearerToken = config.apiBearerToken || null;
    const authHeader = createAuthHeader(apiBearerToken);

    const uppy = new window.Uppy.Uppy({
        autoProceed: true,
        restrictions: {
            allowedFileTypes: ['.mp4', '.mkv', '.avi', '.mov'],
        },
    })
        .use(window.Uppy.Dashboard, {
            inline: true,
            target: '#drag-drop-area',
            proudlyDisplayPoweredByUppy: false,
        })
        .use(window.Uppy.Tus, {
            endpoint: config.apiUploadUrl,
            chunkSize: 5 * 1024 * 1024,
            headers: function () {
                return authHeader;
            },
        });

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

