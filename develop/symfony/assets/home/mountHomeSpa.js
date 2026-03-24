import { createApp, h } from 'vue';
import { createRouter, createWebHistory, RouterView } from 'vue-router';
import { connectMercure } from './connectMercure.js';
import { createHomeTabsView } from './HomeTabsView.js';
import { createVideoDetailsView } from './VideoDetailsView.js';

function readConfig(element) {
    return {
        userIdentifier: element.dataset.userIdentifier || '',
        userId: element.dataset.userId || '',
        apiBearerToken: element.dataset.apiBearerToken || null,
        mercureHubUrl: element.dataset.mercureHubUrl || '',
        mercureTopic: element.dataset.mercureTopic || '',
        mercureSubscriberToken: element.dataset.mercureSubscriberToken || '',
        apiUploadUrl: element.dataset.apiUploadUrl || '',
        apiVideoListUrl: element.dataset.apiVideoListUrl || '',
        apiTaskListUrl: element.dataset.apiTaskListUrl || '',
        apiVideoDetailsUrlTemplate: element.dataset.apiVideoDetailsUrlTemplate || '',
        apiVideoTranscodeUrlTemplate: element.dataset.apiVideoTranscodeUrlTemplate || '',
        apiVideoDeleteUrlTemplate: element.dataset.apiVideoDeleteUrlTemplate || '',
        apiTaskCancelUrlTemplate: element.dataset.apiTaskCancelUrlTemplate || '',
        videoDetailsUrlTemplate: element.dataset.videoDetailsUrlTemplate || '',
        taskDownloadUrlTemplate: element.dataset.taskDownloadUrlTemplate || '',
        homeUrl: element.dataset.homeUrl || '/',
        videoUuid: element.dataset.videoUuid || '',
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

