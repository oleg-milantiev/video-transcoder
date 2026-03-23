export function createVideosTabActions(params) {
    const { config, authHeader, router, videosState, pageLimit } = params;

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
        const response = await fetch(buildPageUrl(url, page, limit), {
            method: 'GET',
            headers: authHeader,
        });

        if (!response.ok) {
            throw new Error('Failed to load list');
        }

        return normalizeListResponse(await response.json(), page, limit);
    }

    async function loadVideos(page = 1) {
        videosState.videosLoading.value = true;
        videosState.videosError.value = '';

        try {
            const payload = await fetchList(config.apiVideoListUrl, page, pageLimit);
            videosState.videos.value = payload.items;
            videosState.videosMeta.value = {
                page: payload.page,
                limit: payload.limit,
                total: payload.total,
                totalPages: Math.max(1, payload.totalPages),
            };
        } catch (e) {
            videosState.videos.value = [];
            videosState.videosError.value = 'Failed to load videos';
        } finally {
            videosState.videosLoading.value = false;
            videosState.videosLoaded.value = true;
        }
    }

    function ensureVideosLoaded() {
        if (videosState.videosLoading.value) {
            return;
        }

        const targetPage = videosState.videosLoaded.value ? videosState.videosMeta.value.page : 1;
        void loadVideos(targetPage);
    }

    function openVideoDetails(uuid) {
        void router.push(config.videoDetailsUrlTemplate.replace('__UUID__', uuid));
    }

    function applyVideoRealtimeUpdate(payload) {
        const videoId = typeof payload.videoId === 'string' ? payload.videoId : '';
        if (!videoId) {
            return;
        }

        videosState.videos.value = videosState.videos.value.map((video) => {
            if (String(video.id || video.uuid) !== videoId && String(video.uuid || video.id) !== videoId) {
                return video;
            }

            return {
                ...video,
                status: typeof payload.status === 'string' ? payload.status : video.status,
                poster: typeof payload.poster === 'string' ? payload.poster : video.poster,
                meta: payload.meta || video.meta,
                updatedAt: payload.updatedAt || video.updatedAt,
            };
        });
    }

    return {
        loadVideos,
        ensureVideosLoaded,
        openVideoDetails,
        applyVideoRealtimeUpdate,
    };
}

