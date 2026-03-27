import { computed } from 'vue';
import {
    createJsonAuthHeaders,
    extractApiErrorMessage,
    normalizeErrorMessage,
    parseJsonResponse,
    replaceTemplateValue,
    toInt,
} from '../shared.js';
import Swal from '../../vendor/sweetalert2/sweetalert2.index.js';

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

    async function openRenameModal() {
        if (!state.dto.value) {
            return;
        }

        const currentTitle = state.dto.value.title || '';

        const { value: newTitle } = await Swal.fire({
            title: 'Rename video',
            input: 'text',
            inputLabel: 'Enter new video title',
            inputValue: currentTitle,
            showCancelButton: true,
            confirmButtonText: 'Submit',
            inputAttributes: {
                autocapitalize: 'off',
            },
            preConfirm: (value) => {
                if (!value || String(value).trim() === '') {
                    Swal.showValidationMessage('Title must not be empty');
                    return false;
                }

                return String(value).trim();
            },
        });

        if (newTitle === undefined || newTitle === null) {
            return;
        }

        const url = replaceTemplateValue(config.apiVideoPatchUrlTemplate || config.apiVideoDetailsUrlTemplate, '__UUID__', uuid.value);

        state.activeActionKey.value = 'rename';
        state.actionError.value = '';

        try {
            const response = await fetch(url, {
                method: 'PATCH',
                headers: createJsonAuthHeaders(config.apiBearerToken || null),
                credentials: 'same-origin',
                body: JSON.stringify({ title: newTitle }),
            });

            const payload = await parseJsonResponse(response);

            if (!response.ok) {
                const msg = extractApiErrorMessage(payload, 'Failed to rename video');
                state.actionError.value = msg;
                // show error inside modal
                await Swal.fire({ title: 'Error', text: msg, icon: 'error' });
                return;
            }

            // On success simply close modal — we already awaited Swal result; update will come via SSE later
            // Optionally show a small success toast
            await Swal.fire({ title: 'Renamed', text: 'Rename request accepted', icon: 'success', timer: 1200, showConfirmButton: false });
        } catch (e) {
            const msg = normalizeErrorMessage(e, 'Failed to rename video');
            state.actionError.value = msg;
            await Swal.fire({ title: 'Error', text: msg, icon: 'error' });
        } finally {
            state.activeActionKey.value = '';
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
                downloadFilename: typeof update.downloadFilename === 'string' ? update.downloadFilename : '',
            };

            return {
                ...preset,
                task: {
                    ...currentTask,
                    id: taskId || currentTask.id || null,
                    status: typeof update.status === 'string' ? update.status : currentTask.status,
                    progress: toInt(update.progress, currentTask.progress),
                    createdAt: typeof update.createdAt === 'string' ? update.createdAt : currentTask.createdAt,
                    downloadFilename: typeof update.downloadFilename === 'string' ? update.downloadFilename : currentTask.downloadFilename,
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
            title: typeof payload.title === 'string' ? payload.title : state.dto.value.title,
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
        openRenameModal,
        applyTaskRealtimeUpdate,
        applyVideoRealtimeUpdate,
    };
}

