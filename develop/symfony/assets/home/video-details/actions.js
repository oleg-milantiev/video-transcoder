import { computed } from 'vue';
import {
    extractApiErrorMessage,
    normalizeErrorMessage,
    parseJsonResponse,
} from '../shared.js';
import { authFetch } from '../apiAuth.js';
import { createTaskActions } from '../task/actions.js';
import Swal from '../../vendor/sweetalert2/sweetalert2.index.js';
import { apiVideoDetailsUrl, apiVideoPatchUrl, videoDetailsPath, ROUTE_VIDEO_LIST } from '../routes.js';

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

    const taskActions = createTaskActions({
        config,
        onSuccess: () => loadDetails(),
        onError: (msg) => { state.actionError.value = msg; },
    });

    function applyVideoListPayload(videoList) {
        if (!videoList || !Array.isArray(videoList.items)) {
            return;
        }
        state.videoListItems.value = videoList.items;
        state.videoListMeta.value = {
            page: Number.isInteger(videoList.page) ? videoList.page : 1,
            limit: Number.isInteger(videoList.limit) ? videoList.limit : 10,
            total: Number.isInteger(videoList.total) ? videoList.total : 0,
            totalPages: Number.isInteger(videoList.totalPages) ? videoList.totalPages : 1,
        };
    }

    async function loadDetails() {
        if (!uuid.value) {
            state.error.value = 'Video UUID is missing';
            state.dto.value = null;
            return;
        }

        state.loading.value = true;
        state.error.value = '';
        state.actionError.value = '';

        const url = apiVideoDetailsUrl(uuid.value);

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
            applyVideoListPayload(payload.videoList);
        } catch (e) {
            state.dto.value = null;
            state.error.value = normalizeErrorMessage(e, 'Failed to load video details');
        } finally {
            state.loading.value = false;
        }
    }

    async function loadVideoList(page) {
        state.videoListLoading.value = true;
        try {
            const limit = state.videoListMeta.value.limit || 10;
            const url = new URL(ROUTE_VIDEO_LIST, window.location.origin);
            url.searchParams.set('page', String(page));
            url.searchParams.set('limit', String(limit));
            const response = await authFetch(url.toString(), { method: 'GET' });
            const payload = await parseJsonResponse(response);
            if (!response.ok) {
                return;
            }
            applyVideoListPayload(payload);
        } catch (_e) {
            // silently ignore
        } finally {
            state.videoListLoading.value = false;
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

        const url = apiVideoPatchUrl(uuid.value);

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
                await Swal.fire({ title: 'Error', text: msg, icon: 'error' });
                return;
            }

            await Swal.fire({ title: 'Renamed', text: 'Rename request accepted', icon: 'success', timer: 1200, showConfirmButton: false });
        } catch (e) {
            const msg = normalizeErrorMessage(e, 'Failed to rename video');
            state.actionError.value = msg;
            await Swal.fire({ title: 'Error', text: msg, icon: 'error' });
        }
    }

    function startTranscode(presetId, height) {
        void taskActions.startTranscode(uuid.value, presetId, height);
    }

    function cancelTask(taskId) {
        void taskActions.cancelTask(taskId);
    }

    function taskDownloadUrl(taskId) {
        return taskActions.getDownloadUrl(taskId);
    }

    function goHome() {
        window.location.href = '/';
    }

    function closeDetails() {
        const page = state.videoListMeta.value.page;
        void router.push({
            path: '/',
            query: { tab: 'videos', page: String(page) },
        });
    }

    function navigateToTab(tab) {
        if (tab === 'upload') {
            window.location.href = '/?tab=upload';
            return;
        }
        const query = { tab };
        if (tab === 'videos') {
            const page = state.videoListMeta.value.page;
            if (page > 1) {
                query.page = String(page);
            }
        }
        void router.push({ path: '/', query });
    }

    function openVideoDetails(videoUuid) {
        void router.push(videoDetailsPath(videoUuid));
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
                    videoTitle: typeof update.videoTitle === 'string' ? update.videoTitle : task.videoTtitle,
                    waitingTariffInstance: typeof update.waitingTariffInstance === 'boolean' ? update.waitingTariffInstance : (task.waitingTariffInstance ?? null),
                    waitingTariffDelay: typeof update.waitingTariffDelay === 'boolean' ? update.waitingTariffDelay : (task.waitingTariffDelay ?? null),
                    willStartAt: typeof update.willStartAt === 'string' ? update.willStartAt : (update.willStartAt === null ? null : (task.willStartAt ?? null)),
                };
            });
        } else {
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

    async function applyVideoUploadedToList(videoDto) {
        if (state.videoListMeta.value.page === 1) {
            state.videoListItems.value = [videoDto, ...state.videoListItems.value];
            state.videoListMeta.value = {
                ...state.videoListMeta.value,
                total: state.videoListMeta.value.total + 1,
            };
        } else {
            await loadVideoList(state.videoListMeta.value.page);
        }
    }

    function applyVideoRealtimeUpdate(payload) {
        if (typeof payload.uuid !== 'string' || !payload.uuid) {
            return;
        }

        // Update the main video in dto
        if (state.dto.value) {
            const video = state.dto.value.video || {};

            if (payload.uuid === video.uuid) {
                const nextDto = {
                    ...state.dto.value,
                    video: { ...video, ...payload },
                };
                // Propagate new title into tasks' videoTitle so the download filename stays correct
                if (typeof payload.title === 'string' && Array.isArray(state.dto.value.tasks)) {
                    nextDto.tasks = state.dto.value.tasks.map((task) => ({
                        ...task,
                        videoTitle: payload.title,
                    }));
                }
                state.dto.value = nextDto;
            }
        }

        // Also update the matching item in the video list
        if (state.videoListItems.value.length > 0) {
            state.videoListItems.value = state.videoListItems.value.map((item) => {
                if (item.uuid !== payload.uuid) {
                    return item;
                }

                return {
                    ...item,
                    poster: Object.prototype.hasOwnProperty.call(payload, 'poster') ? payload.poster : item.poster,
                    title: typeof payload.title === 'string' ? payload.title : item.title,
                    deleted: payload.deleted === true ? true : (item.deleted === true),
                };
            });
        }
    }

    return {
        loadDetails,
        loadVideoList,
        startTranscode,
        cancelTask,
        taskDownloadUrl,
        taskActions,
        goHome,
        closeDetails,
        navigateToTab,
        openVideoDetails,
        formatMetaValue,
        openRenameModal,
        applyTaskRealtimeUpdate,
        applyVideoUploadedToList,
        applyVideoRealtimeUpdate,
    };
}

