import { computed } from 'vue';
import {
    createJsonAuthHeaders,
    extractApiErrorMessage,
    normalizeErrorMessage,
    parseJsonResponse,
    replaceTemplateValue,
    toInt,
} from '../shared.js';

function formatMetaValue(value) {
    if (value === null || value === undefined) {
        return '-';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

export function createVideoDetailsActions(params) {
    const { config, route, router, state } = params;
    const authHeaders = createJsonAuthHeaders(config.apiBearerToken || null);

    const uuid = computed(() => {
        if (typeof route.params.uuid === 'string' && route.params.uuid) {
            return route.params.uuid;
        }

        return config.videoUuid || '';
    });

    async function loadDetails() {
        if (!uuid.value) {
            state.error.value = 'Video UUID is missing';
            state.dto.value = null;
            return;
        }

        state.loading.value = true;
        state.error.value = '';
        state.actionError.value = '';

        const url = replaceTemplateValue(config.apiVideoDetailsUrlTemplate, '__UUID__', uuid.value);

        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: authHeaders,
                credentials: 'same-origin',
            });
            const payload = await parseJsonResponse(response);

            if (!response.ok) {
                state.dto.value = null;
                state.error.value = extractApiErrorMessage(payload, 'Failed to load video details');
                return;
            }

            state.dto.value = payload;
        } catch (e) {
            state.dto.value = null;
            state.error.value = normalizeErrorMessage(e, 'Failed to load video details');
        } finally {
            state.loading.value = false;
        }
    }

    async function runPostAction(url, actionKey, fallbackError) {
        state.activeActionKey.value = actionKey;
        state.actionError.value = '';

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: authHeaders,
                credentials: 'same-origin',
            });
            const payload = await parseJsonResponse(response);

            if (!response.ok) {
                state.actionError.value = extractApiErrorMessage(payload, fallbackError);
                return;
            }

            await loadDetails();
        } catch (e) {
            state.actionError.value = normalizeErrorMessage(e, fallbackError);
        } finally {
            state.activeActionKey.value = '';
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

    function applyTaskRealtimeUpdate(update) {
        if (!state.dto.value) {
            return;
        }

        if (typeof update.videoId === 'string' && update.videoId !== state.dto.value.id) {
            return;
        }

        const taskId = typeof update.taskId === 'string' ? update.taskId : '';
        const presetId = typeof update.presetId === 'string' ? update.presetId : '';

        const nextPresets = (state.dto.value.presetsWithTasks || []).map((preset) => {
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

        state.dto.value = {
            ...state.dto.value,
            presetsWithTasks: nextPresets,
        };
    }

    function applyVideoRealtimeUpdate(payload) {
        if (!state.dto.value) {
            return;
        }

        if (typeof payload.videoId === 'string' && payload.videoId !== state.dto.value.id) {
            return;
        }

        state.dto.value = {
            ...state.dto.value,
            poster: typeof payload.poster === 'string' ? payload.poster : state.dto.value.poster,
            meta: payload.meta || state.dto.value.meta,
            updatedAt: payload.updatedAt || state.dto.value.updatedAt,
        };
    }

    return {
        loadDetails,
        startTranscode,
        cancelTask,
        taskDownloadUrl,
        goHome,
        formatMetaValue,
        applyTaskRealtimeUpdate,
        applyVideoRealtimeUpdate,
    };
}

