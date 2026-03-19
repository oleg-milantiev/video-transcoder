import { createApp, h } from 'vue';
import { createRouter, createWebHistory, RouterView } from 'vue-router';
import { createHomeTabsView } from './HomeTabsView.js';
import { createVideoDetailsView } from './VideoDetailsView.js';

function readConfig(element) {
    return {
        userIdentifier: element.dataset.userIdentifier || '',
        apiBearerToken: element.dataset.apiBearerToken || null,
        apiUploadUrl: element.dataset.apiUploadUrl || '',
        apiVideoListUrl: element.dataset.apiVideoListUrl || '',
        apiTaskListUrl: element.dataset.apiTaskListUrl || '',
        apiVideoDetailsUrlTemplate: element.dataset.apiVideoDetailsUrlTemplate || '',
        apiVideoTranscodeUrlTemplate: element.dataset.apiVideoTranscodeUrlTemplate || '',
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

    const config = readConfig(rootElement);
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
        ],
    });

    const app = createApp({
        render() {
            return h(RouterView);
        },
    });
    app.use(router);
    app.mount(rootElement);
}

