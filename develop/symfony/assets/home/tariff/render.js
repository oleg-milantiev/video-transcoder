import { h } from 'vue';
import { PLANS, renderPlanCard } from './planCard.js';

export function renderTariffs() {
    return h('div', { class: 'py-4' }, [
        h('div', { class: 'text-center mb-5' }, [
            h('h1', { class: 'display-5 fw-bold mb-2' }, 'Choose Your Plan'),
            h('p', { class: 'text-muted fs-5' }, 'Flexible plans for every need. Upgrade or downgrade at any time.'),
        ]),
        h('div', { class: 'row g-4 justify-content-center' },
            PLANS.map((plan) => renderPlanCard(plan))
        ),
    ]);
}
