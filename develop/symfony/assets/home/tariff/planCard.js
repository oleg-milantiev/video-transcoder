import {h} from 'vue';
import {openContactUsModal} from '../contactUs.js';

export const PLANS = [
    {
        key: 'free',
        name: 'Free',
        price: null,
        features: [
            { label: 'Up to 500 MB per video', included: true },
            { label: 'Up to 10 minutes duration', included: true },
            { label: 'Up to 1080p resolution', included: true },
            { label: '5 GB, 24 Hours storage', included: true },
            { label: 'Standard transcoding speed', included: true },
            { label: 'MP4 output format', included: true },
            { label: 'Instant transcoding', included: false },
            { label: 'Multiple simultaneous tasks', included: false },
            { label: 'Priority queue', included: false },
            { label: 'Custom presets', included: false },
            { label: 'HLS streaming output', included: false },
        ],
    },
    {
        key: 'premium',
        name: 'Premium',
        price: '$5 / month',
        features: [
            { label: 'Up to 4 GB per video', included: true },
            { label: 'Up to 3 hours duration', included: true },
            { label: 'Up to 4K resolution', included: true },
            { label: '50 GB, 7 Days storage', included: true },
            { label: 'Fast transcoding speed', included: true },
            { label: 'MP4, WebM output formats', included: true },
            { label: 'Instant transcoding', included: true },
            { label: 'Two simultaneous tasks', included: true },
            { label: 'Priority queue', included: true },
            { label: 'Custom presets', included: true },
            { label: 'HLS streaming output', included: false },
        ],
    },
    {
        key: 'enterprise',
        name: 'Enterprise',
        price: 'Ask us',
        features: [
            { label: 'Unlimited video size', included: true },
            { label: 'Unlimited duration', included: true },
            { label: 'Any resolution', included: true },
            { label: 'Custom storage quota', included: true },
            { label: 'Dedicated transcoding workers', included: true },
            { label: 'All output formats', included: true },
            { label: 'Instant transcoding', included: true },
            { label: 'Unlimited simultaneous tasks', included: true },
            { label: 'Highest priority queue', included: true },
            { label: 'Custom presets', included: true },
            { label: 'HLS streaming output', included: true },
            { label: 'SLA & dedicated support', included: true },
        ],
    },
];

export function renderFeature(feature) {
    if (feature.included) {
        return h('li', { class: 'list-group-item d-flex align-items-center gap-2' }, [
            h('span', { class: 'text-success fs-5 lh-1', style: 'flex-shrink:0' }, '✔'),
            h('span', {}, feature.label),
        ]);
    }
    return h('li', { class: 'list-group-item d-flex align-items-center gap-2 text-muted' }, [
        h('span', { class: 'text-danger fs-5 lh-1', style: 'flex-shrink:0' }, '⊘'),
        h('span', { style: 'text-decoration:line-through' }, feature.label),
    ]);
}

/**
 * @param {object} plan  - one entry from PLANS, optionally with isCurrent: true
 * @param {string} [colClass] - Bootstrap column class, default 'col-12 col-md-4'
 * @param {object} [config] - SPA config (needed for Enterprise contact button)
 */
export function renderPlanCard(plan, colClass = 'col-12 col-md-4', config = null) {
    const isEnterprise = plan.key === 'enterprise';

    const headerChildren = [
        h('h2', { class: 'h4 mb-1' }, plan.name),
        plan.price
            ? h('div', { class: 'fs-5 fw-semibold mb-2' }, plan.price)
            : h('div', { class: 'fs-5 fw-semibold mb-2 text-success' }, 'Free'),
    ];

    const isCurrent = plan.name === config.tariff.title;

    const enterpriseBtnAttrs = {
        class: `btn w-100 btn-outline-primary`,
        type: 'button',
    };
    if (isEnterprise && config) {
        enterpriseBtnAttrs.onClick = () => void openContactUsModal(config);
    }

    const upgradePremiumAttrs = {
        class: 'btn w-100 btn-primary',
        type: 'button',
        onClick: () => {
            window.location.href = config?.route?.payment?.paypalCheckout ?? '/payment/paypal/checkout';
        },
    };

    const footerBtn = isCurrent
        ? h('button', { class: 'btn btn-outline-secondary w-100', disabled: true }, 'Current plan')
        : isEnterprise || plan.name === 'Premium'
            ? h('button', isEnterprise ? enterpriseBtnAttrs : upgradePremiumAttrs,
                isEnterprise ? 'Contact us' : 'Upgrade to ' + plan.name)
            : '';

    return h('div', { class: colClass }, [
        h('div', {
            class: `card h-100 shadow-sm ${plan.key === 'premium' ? 'border-primary' : ''}`,
            style: isCurrent ? 'position: relative; overflow: hidden;' : '',
        }, [
            // Corner ribbon for current plan — green triangle in top-right with a ✓ icon
            isCurrent
                ? h('div', {
                    'aria-label': 'Your current plan',
                    style: 'position: absolute; top: 0; right: 0; width: 72px; height: 72px;'
                         + ' background: #198754;'
                         + ' clip-path: polygon(0 0, 100% 0, 100% 100%);'
                         + ' z-index: 2; pointer-events: none;',
                }, [
                    h('span', {
                        'aria-hidden': 'true',
                        style: 'position: absolute; top: 11px; right: 9px;'
                             + ' color: #fff; font-size: 1.25rem; line-height: 1; font-weight: 700;',
                    }, '✓'),
                ])
                : null,
            h('div', {
                class: `card-header text-center py-4 ${plan.key === 'premium' ? 'bg-primary text-white' : 'bg-light'}`,
            }, headerChildren),
            h('ul', { class: 'list-group list-group-flush flex-grow-1' },
                plan.features.map(renderFeature)
            ),
            h('div', { class: 'card-footer bg-transparent py-3' }, [footerBtn]),
        ]),
    ]);
}
