import { extractApiErrorMessage, parseJsonResponse } from '../../shared.js';
import { authFetch } from '../../apiAuth.js';

export function createVideosTabActions(params) {
    const { config, router, videosState, pageLimit } = params;

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

    async function loadVideos(page = 1) {
        videosState.videosLoading.value = true;
        videosState.videosError.value = '';

        try {
            const payload = await fetchList(config.route.video.list, page, pageLimit);
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
        void router.push(config.route.videoDetails.replace('__UUID__', uuid));
    }

    async function deleteVideo(video) {
        const videoId = String(video && (video.uuid || video.id) ? (video.uuid || video.id) : '');
        if (!videoId || video.deleted === true) {
            return;
        }

        if (!window.confirm('Delete this video?')) {
            return;
        }

        videosState.videoDeletePending.value = {
            ...videosState.videoDeletePending.value,
            [videoId]: true,
        };

        try {
            const response = await authFetch(config.route.video.delete.replace('__UUID__', videoId), {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            const payload = await parseJsonResponse(response);
            if (!response.ok) {
                throw new Error(extractApiErrorMessage(payload, 'Failed to delete video'));
            }

            applyVideoRealtimeUpdate({
                videoId,
                deleted: true,
                updatedAt: new Date().toISOString(),
            });
        } catch (e) {
            window.alert(e instanceof Error && e.message ? e.message : 'Failed to delete video');
        } finally {
            const nextPending = { ...videosState.videoDeletePending.value };
            delete nextPending[videoId];
            videosState.videoDeletePending.value = nextPending;
        }
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
                poster: Object.prototype.hasOwnProperty.call(payload, 'poster') ? payload.poster : video.poster,
                title: typeof payload.title === 'string' ? payload.title : video.title,
                meta: payload.meta || video.meta,
                updatedAt: payload.updatedAt || video.updatedAt,
                deleted: payload.deleted === true ? true : video.deleted === true,
                canBeDeleted: payload.canBeDeleted === true ? true : video.canBeDeleted === true,
            };
        });
    }

    return {
        loadVideos,
        ensureVideosLoaded,
        openVideoDetails,
        deleteVideo,
        applyVideoRealtimeUpdate,
    };
}
