import { createApp, h } from 'vue';
import { createRouter, createWebHistory, RouterView } from 'vue-router';
import { createHomeTabsView } from './HomeTabsView.js';

function readConfig(element) {
    return {
        userIdentifier: element.dataset.userIdentifier || '',
        apiBearerToken: element.dataset.apiBearerToken || null,
        apiUploadUrl: element.dataset.apiUploadUrl || '',
        apiVideoListUrl: element.dataset.apiVideoListUrl || '',
        apiTaskListUrl: element.dataset.apiTaskListUrl || '',
        videoDetailsUrlTemplate: element.dataset.videoDetailsUrlTemplate || '',
        taskDownloadUrlTemplate: element.dataset.taskDownloadUrlTemplate || '',
    };
}

export function mountHomeSpa() {
    const rootElement = document.getElementById('home-spa');
    if (!rootElement) {
        return;
    }

    const config = readConfig(rootElement);
    const HomeTabsView = createHomeTabsView(config);

    const router = createRouter({
        history: createWebHistory(),
        routes: [
            {
                path: '/:pathMatch(.*)*',
                name: 'home-tabs',
                component: HomeTabsView,
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

