import { computed } from 'vue';
import {
    extractApiErrorMessage,
    normalizeErrorMessage,
    parseJsonResponse,
    replaceTemplateValue,
} from '../shared.js';
import { authFetch } from '../apiAuth.js';
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

        const url = replaceTemplateValue(config.route.video.details, '__UUID__', uuid.value);

        try {
            const response = await authFetch(url, {
                method: 'GET',
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

        const video = state.dto.value.video || {};
        const currentTitle = video.title || '';

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

        const url = replaceTemplateValue(config.route.video.patch || config.route.video.details, '__UUID__', uuid.value);

        state.activeActionKey.value = 'rename';
        state.actionError.value = '';

        try {
            const response = await authFetch(url, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
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

    async function runPostAction(url, actionKey, fallbackError, body = null) {
        state.activeActionKey.value = actionKey;
        state.actionError.value = '';

        try {
            const fetchOptions = {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            };

            if (body !== null) {
                fetchOptions.headers['Content-Type'] = 'application/json';
                fetchOptions.body = JSON.stringify(body);
            }

            const response = await authFetch(url, fetchOptions);
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

    function startTranscode(presetId, height) {
        const url = replaceTemplateValue(replaceTemplateValue(replaceTemplateValue(config.route.video.transcode, '__HEIGHT__', height), '__UUID__', uuid.value), '__PRESET_ID__', presetId);
        const actionKey = 'transcode-' + String(presetId) + '-' + String(height);
        void runPostAction(url, actionKey, 'Failed to start transcode');
    }

    function cancelTask(taskId) {
        const url = replaceTemplateValue(config.route.task.cancel, '__TASK_ID__', taskId);
        void runPostAction(url, 'cancel-' + String(taskId), 'Failed to cancel task');
    }

    function taskDownloadUrl(taskId) {
        return replaceTemplateValue(config.route.task.download, '__TASK_ID__', taskId);
    }

    function goHome() {
        if (config.route.home) {
            void router.push({
                path: config.route.home,
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

        const video = state.dto.value.video || {};
        if (typeof update.videoId === 'string' && update.videoId !== video.uuid) {
            return;
        }

        const taskId = typeof update.taskId === 'string' ? update.taskId : '';

        if (!taskId) {
            return;
        }

        const tasks = state.dto.value.tasks || [];
        const taskIndex = tasks.findIndex(t => t.id === taskId);

        let nextTasks;
        if (taskIndex >= 0) {
            // Update existing task
            nextTasks = tasks.map((task, index) => {
                if (index !== taskIndex) {
                    return task;
                }

                return {
                    ...task,
                    status: typeof update.status === 'string' ? update.status : task.status,
                    progress: typeof update.progress === 'number' ? update.progress : task.progress,
                    createdAt: typeof update.createdAt === 'string' ? update.createdAt : task.createdAt,
                    updatedAt: typeof update.updatedAt === 'string' ? update.updatedAt : task.updatedAt,
                    expiredAt: typeof update.expiredAt === 'string' ? update.expiredAt : task.expiredAt,
                    waitingTariffInstance: typeof update.waitingTariffInstance === 'boolean' ? update.waitingTariffInstance : (task.waitingTariffInstance ?? null),
                    waitingTariffDelay: typeof update.waitingTariffDelay === 'boolean' ? update.waitingTariffDelay : (task.waitingTariffDelay ?? null),
                    willStartAt: typeof update.willStartAt === 'string' ? update.willStartAt : (update.willStartAt === null ? null : (task.willStartAt ?? null)),
                    downloadFilename: (typeof update.videoTitle === 'string' && typeof update.presetTitle === 'string')
                        ? (update.videoTitle + ' - ' + update.presetTitle)
                        : task.downloadFilename,
                };
            });
        } else {
            // Add new task
            nextTasks = [
                ...tasks,
                {
                    id: taskId,
                    status: typeof update.status === 'string' ? update.status : 'PENDING',
                    progress: typeof update.progress === 'number' ? update.progress : 0,
                    createdAt: typeof update.createdAt === 'string' ? update.createdAt : '-',
                    updatedAt: typeof update.updatedAt === 'string' ? update.updatedAt : undefined,
                    expiredAt: typeof update.expiredAt === 'string' ? update.expiredAt : undefined,
                    presetTitle: typeof update.presetTitle === 'string' ? update.presetTitle : '-',
                    downloadFilename: (typeof update.videoTitle === 'string' && typeof update.presetTitle === 'string')
                        ? (update.videoTitle + ' - ' + update.presetTitle)
                        : '',
                    waitingTariffInstance: typeof update.waitingTariffInstance === 'boolean' ? update.waitingTariffInstance : null,
                    waitingTariffDelay: typeof update.waitingTariffDelay === 'boolean' ? update.waitingTariffDelay : null,
                    willStartAt: typeof update.willStartAt === 'string' ? update.willStartAt : null,
                },
            ];
        }

        state.dto.value = {
            ...state.dto.value,
            tasks: nextTasks,
        };
    }

    function applyVideoRealtimeUpdate(payload) {
        if (!state.dto.value) {
            return;
        }

        const video = state.dto.value.video || {};

        // Require videoId to be present and matching
        if (typeof payload.videoId !== 'string' || !payload.videoId) {
            return;
        }

        if (payload.videoId !== video.uuid) {
            return;
        }

        const updatedVideo = {
            ...video,
            poster: typeof payload.poster === 'string' ? payload.poster : video.poster,
            title: typeof payload.title === 'string' ? payload.title : video.title,
            meta: payload.meta || video.meta,
            updatedAt: payload.updatedAt || video.updatedAt,
            expiredAt: payload.expiredAt || video.expiredAt,
        };

        state.dto.value = {
            ...state.dto.value,
            video: updatedVideo,
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

