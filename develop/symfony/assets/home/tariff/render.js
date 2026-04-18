import { h } from 'vue';
import { PLANS, renderPlanCard } from './planCard.js';
import { useRouter } from 'vue-router';

export function renderTariffs(vm) {
    const backBtn = h('button', {
        type: 'button',
        class: 'btn btn-outline-secondary btn-sm',
        onClick: vm.goHome,
    }, 'Back');

    return h('div', { class: 'py-2' }, [
        h('div', { class: 'd-flex justify-content-between align-items-start mb-3' }, [
            h('div', { class: 'text-center flex-grow-1' }, [
                h('h1', { class: 'display-5 fw-bold mb-2' }, 'Choose Your Plan'),
                h('p', { class: 'text-muted fs-5' }, 'Flexible plans for every need. Upgrade or downgrade at any time.'),
            ]),
            backBtn,
        ]),
        h('div', { class: 'row g-4 justify-content-center' },
            PLANS.map((plan) => renderPlanCard(plan))
        ),
    ]);
}
