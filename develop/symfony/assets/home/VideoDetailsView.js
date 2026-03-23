import { computed, defineComponent, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { renderVideoDetails } from './VideoDetailsRender.js';
import {
    createJsonAuthHeaders,
    extractApiErrorMessage,
    normalizeErrorMessage,
    parseJsonResponse,
    replaceTemplateValue,
    toInt,
} from './shared.js';

function formatMetaValue(value) {
    if (value === null || value === undefined) {
        return '-';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

function isTaskMessage(message) {
    return message && typeof message === 'object' && message.entity === 'task' && message.payload && typeof message.payload === 'object';
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
            const authHeaders = createJsonAuthHeaders(config.apiBearerToken || null);
            const onTaskMessage = function (event) {
                const message = event.detail;
                if (!isTaskMessage(message) || !dto.value) {
                    return;
                }

                const update = message.payload;
                if (typeof update.videoId === 'string' && update.videoId !== dto.value.id) {
                    return;
                }

                const taskId = typeof update.taskId === 'string' ? update.taskId : '';
                const presetId = typeof update.presetId === 'string' ? update.presetId : '';

                const nextPresets = (dto.value.presetsWithTasks || []).map((preset) => {
                    const task = preset.task;
                    const sameTask = taskId && task && String(task.id) === taskId;
                    const samePreset = presetId && String(preset.id) === presetId;

                    if (!sameTask && !samePreset) {
                        return preset;
                    }

                    const currentTask = task || {
                        id: taskId || null,
                        status: 'PENDING',
                        progress: 0,
                        createdAt: typeof update.createdAt === 'string' ? update.createdAt : '-',
                    };

                    return {
                        ...preset,
                        task: {
                            ...currentTask,
                            id: taskId || currentTask.id || null,
                            status: typeof update.status === 'string' ? update.status : currentTask.status,
                            progress: toInt(update.progress, currentTask.progress),
                            createdAt: typeof update.createdAt === 'string' ? update.createdAt : currentTask.createdAt,
                        },
                    };
                });

                dto.value = {
                    ...dto.value,
                    presetsWithTasks: nextPresets,
                };
            };

            const onVideoMessage = function (event) {
                const message = event.detail;
                if (!message || typeof message !== 'object' || !dto.value) {
                    return;
                }

                if (message.entity !== 'video') {
                    return;
                }

                const payload = message.payload || {};
                if (typeof payload.videoId === 'string' && payload.videoId !== dto.value.id) {
                    return;
                }

                dto.value = {
                    ...dto.value,
                    poster: typeof payload.poster === 'string' ? payload.poster : dto.value.poster,
                    meta: payload.meta || dto.value.meta,
                    updatedAt: payload.updatedAt || dto.value.updatedAt,
                };
            };

            const uuid = computed(() => {
                if (typeof route.params.uuid === 'string' && route.params.uuid) {
                    return route.params.uuid;
                }

                return config.videoUuid || '';
            });

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
                window.addEventListener('app:task', onTaskMessage);
                window.addEventListener('app:video', onVideoMessage);
            });

            onBeforeUnmount(function () {
                window.removeEventListener('app:task', onTaskMessage);
                window.removeEventListener('app:video', onVideoMessage);
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
            return renderVideoDetails(this);
        },
    });
}
