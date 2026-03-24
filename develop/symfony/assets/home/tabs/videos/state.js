import { ref } from 'vue';

export function createVideosTabState(pageLimit) {
    return {
        videos: ref([]),
        videosMeta: ref({ page: 1, limit: pageLimit, total: 0, totalPages: 1 }),
        videosLoading: ref(false),
        videosError: ref(''),
        videosLoaded: ref(false),
        videoDeletePending: ref({}),
    };
}

