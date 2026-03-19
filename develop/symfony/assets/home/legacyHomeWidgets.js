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
    if (typeof window.Uppy === 'undefined' || typeof window.DataTable === 'undefined') {
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

    const videosTable = new window.DataTable('#videosTable', {
        ajax: {
            url: config.apiVideoListUrl,
            headers: authHeader,
            dataSrc: '',
        },
        pageLength: 10,
        columns: [
            {
                data: 'poster',
                orderable: false,
                render: function (data) {
                    if (data) {
                        return '<img src="/uploads/' + data + '" alt="poster" style="width:150px;max-width:100%;height:auto;object-fit:cover;border-radius:6px;">';
                    }

                    return '<div style="width:150px;height:84px;background:#eee;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:12px;border-radius:6px;">No poster</div>';
                },
            },
            { data: 'title' },
            { data: 'status' },
            { data: 'createdAt' },
        ],
        order: [[3, 'desc']],
    });

    function onVideoRowClick(e) {
        const tr = e.target.closest('tr');
        if (!tr) {
            return;
        }

        const row = videosTable.row(tr).data();
        if (!row || !row.uuid) {
            return;
        }

        window.open(config.videoDetailsUrlTemplate.replace('__UUID__', row.uuid), '_blank');
    }

    const videosTbody = document.querySelector('#videosTable tbody');
    if (videosTbody) {
        videosTbody.addEventListener('click', onVideoRowClick);
    }

    const tasksTable = new window.DataTable('#tasksTable', {
        ajax: {
            url: config.apiTaskListUrl,
            headers: authHeader,
            dataSrc: '',
        },
        pageLength: 10,
        columns: [
            { data: 'videoTitle' },
            { data: 'presetTitle' },
            { data: 'status' },
            { data: 'progress' },
            { data: 'createdAt' },
            {
                data: null,
                orderable: false,
                render: function (_, __, row) {
                    if (row.status === 'COMPLETED' && row.id) {
                        return '<a href="' + config.taskDownloadUrlTemplate.replace('__TASK_ID__', row.id) + '" class="btn btn-outline-primary btn-sm" download>Download</a>';
                    }

                    return '<span class="text-muted">-</span>';
                },
            },
        ],
        order: [[4, 'desc']],
    });

    return function cleanup() {
        if (videosTbody) {
            videosTbody.removeEventListener('click', onVideoRowClick);
        }

        try {
            uppy.close();
        } catch (e) {
            // Ignore cleanup errors from third-party widgets.
        }

        if (videosTable && typeof videosTable.destroy === 'function') {
            videosTable.destroy();
        }

        if (tasksTable && typeof tasksTable.destroy === 'function') {
            tasksTable.destroy();
        }
    };
}

