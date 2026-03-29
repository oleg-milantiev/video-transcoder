import { createApp, h } from 'vue';
import { createRouter, createWebHistory, RouterView } from 'vue-router';
import { connectMercure } from './connectMercure.js';
import { createHomeTabsView } from './HomeTabsView.js';
import { createVideoDetailsView } from './VideoDetailsView.js';
import { initAuth } from './apiAuth.js';

function readConfig(element) {
    // todo use backend config structure
    return {
        userIdentifier: config.user.identifier || '',
        userId: config.user.id || '',
        apiBearerToken: config.token.access || null,
        apiRefreshToken: config.token.refresh || null,
        apiRefreshUrl: config.route.refreshToken || '',
        mercureHubUrl: config.mercure.hub || '',
        mercureTopic: config.mercure.topic || '',
        mercureSubscriberToken: config.mercure.token || '',
        apiUploadUrl: config.route.upload || '',
        apiVideoListUrl: config.route.video.list || '',
        apiTaskListUrl: config.route.task.list || '',
        apiVideoDetailsUrlTemplate: config.route.video.details || '',
        apiVideoTranscodeUrlTemplate: config.route.video.transcode || '',
        apiVideoDeleteUrlTemplate: config.route.video.delete || '',
        apiVideoPatchUrlTemplate: config.route.video.patch || '',
        apiTaskCancelUrlTemplate: config.route.task.cancel || '',
        videoDetailsUrlTemplate: config.route.videoDetails || '',
        taskDownloadUrlTemplate: config.route.task.download || '',
        homeUrl: config.route.home || '/',
        videoUuid: config.videoUuid || '',
        maxVideoSize: config.tariff.videoSize ? parseFloat(config.tariff.videoSize) : null,
    };
}

export function mountHomeSpa() {
    const rootElement = document.getElementById('home-spa') || document.getElementById('video-details-spa');
    if (!rootElement) {
        return;
    }

    if (rootElement.dataset.vueMounted === '1') {
        return;
    }

    const config = readConfig(rootElement);
    initAuth({
        accessToken: config.apiBearerToken,
        refreshToken: config.apiRefreshToken,
        refreshUrl: config.apiRefreshUrl,
    });
    connectMercure(config, rootElement);
    const HomeTabsView = createHomeTabsView(config);
    const VideoDetailsView = createVideoDetailsView(config);

    const router = createRouter({
        history: createWebHistory(),
        routes: [
            {
                path: '/',
                name: 'home-tabs',
                component: HomeTabsView,
            },
            {
                path: '/video/:uuid',
                name: 'video-details',
                component: VideoDetailsView,
            },
            {
                path: '/:pathMatch(.*)*',
                redirect: '/',
            },
        ],
    });

    const app = createApp({
        render() {
            return h(RouterView);
        },
    });
    app.use(router);

    // Wait for initial route resolution to avoid rendering an empty RouterView.
    void router.isReady().then(function () {
        rootElement.dataset.vueMounted = '1';
        app.mount(rootElement);
    });
}

