import { ref } from 'vue';

export function createVideoDetailsState(initialTariff) {
    return {
        dto: ref(null),
        loading: ref(false),
        error: ref(''),
        actionError: ref(''),
        tariff: ref(initialTariff || null),
        // video list (left pane)
        videoListItems: ref([]),
        videoListMeta: ref({ page: 1, limit: 10, total: 0, totalPages: 1 }),
        videoListLoading: ref(false),
        // active tab in right pane: 'info' | 'transcode' | 'tasks'
        activeTab: ref('info'),
        // transcode builder state
        transcodeGoal: ref('social'),
        transcodeQuality: ref(typeof config !== 'undefined' && config?.tariff?.title === 'Free' ? 'normal' : 'good'),
        transcodeResolution: ref('auto'),
        transcodeFormat: ref('mp4'),
        // custom tab filters
        customFilterFormat: ref('all'),
        customFilterVideoCodec: ref('all'),
        customFilterAudioCodec: ref('all'),
    };
}
