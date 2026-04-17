import { defineComponent } from 'vue';
import { renderTariffs } from './render.js';

export function createTariffView(config) {
    return defineComponent({
        name: 'TariffView',
        setup() {
            return { config };
        },
        render() {
            return renderTariffs();
        },
    });
}
