import { createApp, h } from 'vue';
import { createRouter, createWebHistory, RouterView } from 'vue-router';
import { connectMercure } from './connectMercure.js';
import { createHomeTabsView } from './HomeTabsView.js';
import { createVideoDetailsView } from './VideoDetailsView.js';
import { createProfileView } from './profile/view.js';
import { initAuth } from './apiAuth.js';

export function mountHomeSpa() {
    const rootElement = document.getElementById('home-spa') || document.getElementById('video-details-spa');
    if (!rootElement) {
        return;
    }

    if (rootElement.dataset.vueMounted === '1') {
        return;
    }

    initAuth({
        accessToken: config.token.access || null,
        refreshToken: config.token.refresh || null,
        refreshUrl: config.route.refreshToken || '',
    });
    connectMercure(config, rootElement);
    const HomeTabsView = createHomeTabsView(config);
    const VideoDetailsView = createVideoDetailsView(config);
    const ProfileView = createProfileView(config);

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
                path: '/profile',
                name: 'profile',
                component: ProfileView,
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

