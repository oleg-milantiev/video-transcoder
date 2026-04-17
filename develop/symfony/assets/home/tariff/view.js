import { defineComponent } from 'vue';
import { useRouter } from 'vue-router';
import { renderTariffs } from './render.js';

export function createTariffView(config) {
    return defineComponent({
        name: 'TariffView',
        setup() {
            const router = useRouter();

            function goHome() {
                window.location.href = config.route?.home ?? '/';
            }

            return { config, goHome };
        },
        render() {
            return renderTariffs(this);
        },
    });
}
