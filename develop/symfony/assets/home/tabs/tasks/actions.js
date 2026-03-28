import { parseJsonResponse, extractApiErrorMessage } from '../../shared.js';
import { authFetch } from '../../apiAuth.js';

export function isTaskActive(status) {
    return status === 'PENDING' || status === 'PROCESSING';
}

export function createTasksTabActions(params) {
    const { config, tasksState, pageLimit } = params;

    function normalizeListResponse(payload, page, limit) {
        if (!payload || typeof payload !== 'object') {
            return {
                items: [],
                total: 0,
                page,
                limit,
                totalPages: 1,
            };
        }

        if (Array.isArray(payload)) {
            return {
                items: payload,
                total: payload.length,
                page,
                limit,
                totalPages: 1,
            };
        }

        return {
            items: Array.isArray(payload.items) ? payload.items : [],
            total: Number.isInteger(payload.total) ? payload.total : 0,
            page: Number.isInteger(payload.page) ? payload.page : page,
            limit: Number.isInteger(payload.limit) ? payload.limit : limit,
            totalPages: Number.isInteger(payload.totalPages) ? payload.totalPages : 1,
        };
    }

    function buildPageUrl(baseUrl, page, limit) {
        const url = new URL(baseUrl, window.location.origin);
        url.searchParams.set('page', String(page));
        url.searchParams.set('limit', String(limit));

        return url.toString();
    }

    async function fetchList(url, page, limit) {
        const response = await authFetch(buildPageUrl(url, page, limit), {
            method: 'GET',
        });

        const payload = await parseJsonResponse(response);

        if (!response.ok) {
            throw new Error(extractApiErrorMessage(payload, 'Failed to load list'));
        }

        return normalizeListResponse(payload, page, limit);
    }

    async function loadTasks(page = 1) {
        tasksState.tasksLoading.value = true;
        tasksState.tasksError.value = '';

        try {
            const payload = await fetchList(config.apiTaskListUrl, page, pageLimit);
            tasksState.tasks.value = payload.items;
            tasksState.tasksMeta.value = {
                page: payload.page,
                limit: payload.limit,
                total: payload.total,
                totalPages: Math.max(1, payload.totalPages),
            };
        } catch (e) {
            tasksState.tasks.value = [];
            tasksState.tasksError.value = 'Failed to load tasks';
        } finally {
            tasksState.tasksLoading.value = false;
            tasksState.tasksLoaded.value = true;
        }
    }

    function ensureTasksLoaded() {
        if (tasksState.tasksLoading.value) {
            return;
        }

        const targetPage = tasksState.tasksLoaded.value ? tasksState.tasksMeta.value.page : 1;
        void loadTasks(targetPage);
    }

    async function cancelTask(taskId) {
        if (!taskId) {
            return;
        }

        tasksState.taskActionKey.value = 'cancel-' + String(taskId);

        try {
            const url = config.apiTaskCancelUrlTemplate.replace('__TASK_ID__', String(taskId));
            const response = await authFetch(url, {
                method: 'POST',
            });

            const payload = await parseJsonResponse(response);
            if (!response.ok) {
                tasksState.tasksError.value = extractApiErrorMessage(payload, 'Failed to cancel task');
            }
        } catch (e) {
            tasksState.tasksError.value = 'Failed to cancel task';
        } finally {
            tasksState.taskActionKey.value = '';
        }
    }

    function getTaskDownloadUrl(taskId) {
        return config.taskDownloadUrlTemplate.replace('__TASK_ID__', String(taskId));
    }

    function applyTaskRealtimeUpdate(update) {
        const taskId = typeof update.taskId === 'string' ? update.taskId : '';
        if (!taskId) {
            return;
        }

        tasksState.tasks.value = tasksState.tasks.value.map((task) => {
            if (String(task.id) !== taskId) {
                return task;
            }

            return {
                ...task,
                status: typeof update.status === 'string' ? update.status : task.status,
                progress: typeof update.progress === 'number' ? update.progress : task.progress,
                createdAt: typeof update.createdAt === 'string' ? update.createdAt : task.createdAt,
                downloadFilename: (typeof update.videoTitle === 'string' && typeof update.presetTitle === 'string')
                    ? (update.videoTitle + ' - ' + update.presetTitle)
                    : (task.videoTitle + ' - ' + task.presetTitle),
                videoTitle: typeof update.videoTitle === 'string' ? update.videoTitle : task.videoTitle,
                presetTitle: typeof update.presetTitle === 'string' ? update.presetTitle : task.presetTitle,
            };
        });
    }

    return {
        loadTasks,
        ensureTasksLoaded,
        cancelTask,
        getTaskDownloadUrl,
        applyTaskRealtimeUpdate,
    };
}

