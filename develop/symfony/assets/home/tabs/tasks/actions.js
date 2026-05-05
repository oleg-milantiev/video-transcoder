import { parseJsonResponse, extractApiErrorMessage } from '../../shared.js';
import { authFetch } from '../../apiAuth.js';
import { createTaskActions } from '../../task/actions.js';
import { ROUTE_TASK_LIST } from '../../routes.js';

export function isTaskActive(status) {
    return status === 'PENDING' || status === 'PROCESSING';
}

export function createTasksTabActions(params) {
    const { config, tasksState, pageLimit } = params;

    const taskActions = createTaskActions({
        config,
        onSuccess: () => {
            const targetPage = tasksState.tasksMeta.value.page;
            void loadTasks(targetPage);
        },
        onError: (msg) => { tasksState.tasksError.value = msg; },
    });

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
            const payload = await fetchList(ROUTE_TASK_LIST, page, pageLimit);
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
                videoTitle: typeof update.videoTitle === 'string' ? update.videoTitle : task.videoTitle,
                presetTitle: typeof update.presetTitle === 'string' ? update.presetTitle : task.presetTitle,
                size: typeof update.size === 'number' ? update.size : task.size,
                updatedAt: typeof update.updatedAt === 'string' ? update.updatedAt : task.updatedAt,
            };
        });
    }

    return {
        loadTasks,
        ensureTasksLoaded,
        taskActions,
        applyTaskRealtimeUpdate,
    };
}

