import { computed, defineComponent, h, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

function createAuthHeaders(apiBearerToken) {
    const headers = {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (apiBearerToken) {
        headers.Authorization = 'Bearer ' + apiBearerToken;
    }

    return headers;
}

function replaceTemplateValue(template, placeholder, value) {
    return template.replace(placeholder, String(value));
}

function formatMetaValue(value) {
    if (value === null || value === undefined) {
        return '-';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

function normalizeErrorMessage(error, fallback) {
    if (error instanceof Error && error.message) {
        return error.message;
    }

    return fallback;
}

function extractApiErrorMessage(payload, fallback) {
    if (payload && payload.error && typeof payload.error === 'object' && typeof payload.error.message === 'string') {
        return payload.error.message;
    }

    if (payload && typeof payload.error === 'string') {
        return payload.error;
    }

    return fallback;
}

export function createVideoDetailsView(config) {
    return defineComponent({
        name: 'VideoDetailsView',
        setup() {
            const route = useRoute();
            const router = useRouter();
            const dto = ref(null);
            const loading = ref(false);
            const error = ref('');
            const actionError = ref('');
            const activeActionKey = ref('');
            const authHeaders = createAuthHeaders(config.apiBearerToken || null);

            const uuid = computed(() => {
                if (typeof route.params.uuid === 'string' && route.params.uuid) {
                    return route.params.uuid;
                }

                return config.videoUuid || '';
            });

            async function parseJsonResponse(response) {
                try {
                    return await response.json();
                } catch (e) {
                    return null;
                }
            }

            async function loadDetails() {
                if (!uuid.value) {
                    error.value = 'Video UUID is missing';
                    dto.value = null;
                    return;
                }

                loading.value = true;
                error.value = '';
                actionError.value = '';

                const url = replaceTemplateValue(config.apiVideoDetailsUrlTemplate, '__UUID__', uuid.value);

                try {
                    const response = await fetch(url, {
                        method: 'GET',
                        headers: authHeaders,
                        credentials: 'same-origin',
                    });
                    const payload = await parseJsonResponse(response);

                    if (!response.ok) {
                        dto.value = null;
                        error.value = extractApiErrorMessage(payload, 'Failed to load video details');
                        return;
                    }

                    dto.value = payload;
                } catch (e) {
                    dto.value = null;
                    error.value = normalizeErrorMessage(e, 'Failed to load video details');
                } finally {
                    loading.value = false;
                }
            }

            async function runPostAction(url, actionKey, fallbackError) {
                activeActionKey.value = actionKey;
                actionError.value = '';

                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: authHeaders,
                        credentials: 'same-origin',
                    });
                    const payload = await parseJsonResponse(response);

                    if (!response.ok) {
                        actionError.value = extractApiErrorMessage(payload, fallbackError);
                        return;
                    }

                    await loadDetails();
                } catch (e) {
                    actionError.value = normalizeErrorMessage(e, fallbackError);
                } finally {
                    activeActionKey.value = '';
                }
            }

            function startTranscode(presetId) {
                const url = replaceTemplateValue(
                    replaceTemplateValue(config.apiVideoTranscodeUrlTemplate, '__UUID__', uuid.value),
                    '__PRESET_ID__',
                    presetId
                );

                void runPostAction(url, 'transcode-' + String(presetId), 'Failed to start transcode');
            }

            function cancelTask(taskId) {
                const url = replaceTemplateValue(config.apiTaskCancelUrlTemplate, '__TASK_ID__', taskId);
                void runPostAction(url, 'cancel-' + String(taskId), 'Failed to cancel task');
            }

            function taskDownloadUrl(taskId) {
                return replaceTemplateValue(config.taskDownloadUrlTemplate, '__TASK_ID__', taskId);
            }

            function goHome() {
                if (config.homeUrl) {
                    void router.push({
                        path: config.homeUrl,
                        query: { tab: 'videos' },
                    });
                    return;
                }

                void router.push({
                    path: '/',
                    query: { tab: 'videos' },
                });
            }

            onMounted(function () {
                void loadDetails();
            });

            return {
                dto,
                loading,
                error,
                actionError,
                activeActionKey,
                startTranscode,
                cancelTask,
                taskDownloadUrl,
                formatMetaValue,
                goHome,
            };
        },
        render() {
            if (this.loading) {
                return h('div', { class: 'py-4' }, [
                    h('h1', { class: 'mb-3' }, 'Video Details'),
                    h('p', { class: 'text-muted' }, 'Loading...'),
                ]);
            }

            if (this.error) {
                return h('div', { class: 'py-4' }, [
                    h('h1', { class: 'mb-3' }, 'Video Details'),
                    h('div', { class: 'alert alert-danger' }, this.error),
                    h(
                        'button',
                        {
                            type: 'button',
                            class: 'btn btn-outline-secondary',
                            onClick: this.goHome,
                        },
                        'Back to home'
                    ),
                ]);
            }

            if (!this.dto) {
                return h('div', { class: 'py-4' }, [h('h1', { class: 'mb-3' }, 'Video Details')]);
            }

            const rows = (this.dto.presetsWithTasks || []).map((preset) => {
                const task = preset.task;
                let actionNode = h('button', {
                    type: 'button',
                    class: 'btn btn-outline-primary btn-sm',
                    disabled: this.activeActionKey === 'transcode-' + String(preset.id),
                    onClick: () => this.startTranscode(preset.id),
                }, this.activeActionKey === 'transcode-' + String(preset.id) ? 'Processing...' : 'Transcode');

                if (task && task.status === 'COMPLETED' && task.id) {
                    actionNode = h('a', {
                        href: this.taskDownloadUrl(task.id),
                        class: 'btn btn-outline-primary btn-sm',
                        download: '',
                    }, 'Download');
                } else if (task && (task.status === 'PENDING' || task.status === 'PROCESSING') && task.id) {
                    actionNode = h('button', {
                        type: 'button',
                        class: 'btn btn-outline-primary btn-sm',
                        disabled: this.activeActionKey === 'cancel-' + String(task.id),
                        onClick: () => this.cancelTask(task.id),
                    }, this.activeActionKey === 'cancel-' + String(task.id) ? 'Cancelling...' : 'Cancel');
                }

                return h('tr', [
                    h('td', preset.title),
                    h('td', task ? task.status : h('em', 'No task')),
                    h('td', task ? String(task.progress) + '%' : '-'),
                    h('td', task ? task.createdAt : '-'),
                    h('td', [actionNode]),
                ]);
            });

            const metaEntries = Object.entries(this.dto.meta || {});

            return h('div', { class: 'py-4' }, [
                h('div', { class: 'd-flex justify-content-between align-items-center mb-3' }, [
                    h('h1', { class: 'mb-0' }, 'Video Details'),
                    h(
                        'button',
                        {
                            type: 'button',
                            class: 'btn btn-outline-secondary btn-sm',
                            onClick: this.goHome,
                        },
                        'Back'
                    ),
                ]),
                this.actionError ? h('div', { class: 'alert alert-danger' }, this.actionError) : null,
                h('div', { class: 'card mb-4' }, [
                    h('div', { class: 'card-body' }, [
                        this.dto.poster
                            ? h('img', {
                                  src: '/uploads/' + this.dto.poster,
                                  class: 'img-fluid mb-3 rounded',
                                  alt: this.dto.title,
                                  style: 'max-width: 520px;',
                              })
                            : null,
                        h('dl', { class: 'row mb-0' }, [
                            h('dt', { class: 'col-sm-3' }, 'Title'),
                            h('dd', { class: 'col-sm-9' }, this.dto.title),
                            h('dt', { class: 'col-sm-3' }, 'Extension'),
                            h('dd', { class: 'col-sm-9' }, this.dto.extension),
                            h('dt', { class: 'col-sm-3' }, 'Status'),
                            h('dd', { class: 'col-sm-9' }, this.dto.status),
                            h('dt', { class: 'col-sm-3' }, 'Created At'),
                            h('dd', { class: 'col-sm-9' }, this.dto.createdAt),
                            h('dt', { class: 'col-sm-3' }, 'Updated At'),
                            h('dd', { class: 'col-sm-9' }, this.dto.updatedAt || '-'),
                            h('dt', { class: 'col-sm-3' }, 'User ID'),
                            h('dd', { class: 'col-sm-9' }, String(this.dto.userId)),
                        ]),
                    ]),
                ]),
                h('h5', { class: 'mb-2' }, 'Presets'),
                h('table', { class: 'table table-bordered align-middle mb-4' }, [
                    h('thead', [h('tr', [h('th', 'Preset'), h('th', 'Status'), h('th', 'Progress'), h('th', 'Created'), h('th', 'Actions')])]),
                    h('tbody', rows.length > 0 ? rows : [h('tr', [h('td', { colspan: '5', class: 'text-muted text-center' }, 'No presets')])]),
                ]),
                h('h5', { class: 'mb-2' }, 'Meta'),
                h(
                    'ul',
                    { class: 'mb-0' },
                    metaEntries.length > 0
                        ? metaEntries.map(([key, value]) => h('li', [h('strong', key + ': '), this.formatMetaValue(value)]))
                        : [h('li', [h('em', 'No meta data')])]
                ),
            ]);
        },
    });
}



