import { ref } from 'vue';

export function createTasksTabState(pageLimit) {
    return {
        tasks: ref([]),
        tasksMeta: ref({ page: 1, limit: pageLimit, total: 0, totalPages: 1 }),
        tasksLoading: ref(false),
        tasksError: ref(''),
        tasksLoaded: ref(false),
        taskActionKey: ref(''),
    };
}

