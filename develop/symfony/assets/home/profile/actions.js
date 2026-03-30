import { computed } from 'vue';

export function createProfileActions(params) {
    const { config, route, router, state } = params;

    const uuid = computed(() => {
        if (typeof route.params.uuid === 'string' && route.params.uuid) {
            return route.params.uuid;
        }

        return config.videoUuid || '';
    });

    function goHome() {
        if (config.route.home) {
            void router.push({
                path: config.route.home,
                query: { tab: 'videos' },
            });
            return;
        }

        void router.push({
            path: '/',
            query: { tab: 'videos' },
        });
    }

    return {
        goHome,
    };
}

