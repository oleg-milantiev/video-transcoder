import { h } from 'vue';
import { bytesToHuman } from '../shared.js';
import { PLANS, renderPlanCard } from '../tariff/planCard.js';
import {
    formatBytes,
    computeStoragePercent,
    computeStorageFreeBytes,
} from '../tabs/TariffHint.js';

// ─── helpers ──────────────────────────────────────────────────────────────────

function sectionCard(iconLabel, title, bodyContent) {
    return h('div', { class: 'card shadow-sm mb-4' }, [
        h('div', { class: 'card-header bg-light d-flex align-items-center gap-2 py-3' }, [
            h('span', { class: 'fs-4 lh-1' }, iconLabel),
            h('h2', { class: 'h5 mb-0' }, title),
        ]),
        h('div', { class: 'card-body' }, bodyContent),
    ]);
}

function infoRow(label, value) {
    return h('div', { class: 'row mb-2' }, [
        h('div', { class: 'col-sm-5 text-muted' }, label),
        h('div', { class: 'col-sm-7 fw-semibold' }, value),
    ]);
}

// ─── blocks ───────────────────────────────────────────────────────────────────

function renderUserBlock(user, tariff) {
    const tariffTitle = tariff?.title ?? '—';
    const isFree = !tariff?.title || tariff.title.toLowerCase() === 'free';

    const tariffCell = [
        h('span', { class: `badge ${isFree ? 'bg-secondary' : 'bg-primary'} me-2` }, tariffTitle),
        isFree
            ? h('a', { href: '/tariffs', class: 'btn btn-sm btn-outline-primary' }, '⬆ Upgrade')
            : null,
    ];

    return sectionCard('👤', 'Account', [
        infoRow('Email', user?.identifier ?? '—'),
        infoRow('Member since', '—'),
        infoRow('Current plan', tariffCell),
    ]);
}

function renderTariffBlock(tariff) {
    const title = tariff?.title ?? '';
    const currentPlanKey = PLANS.find(
        (p) => p.name.toLowerCase() === title.toLowerCase()
    )?.key ?? 'free';

    // Show current plan + upgrade option (if Free, show Premium too)
    let plansToShow = PLANS.filter(p => p.key === currentPlanKey);

    if (currentPlanKey === 'free') {
        // Show Free (current) and Premium (upgrade option)
        plansToShow = PLANS.filter(p => ['free', 'premium'].includes(p.key));
    }

    const plansWithCurrent = plansToShow.map(p => ({
        ...p,
        isCurrent: p.key === currentPlanKey,
    }));

    return sectionCard('💳', 'Tariff', [
        h('div', { class: 'row g-3' },
            plansWithCurrent.map(plan => renderPlanCard(plan, 'col-12 col-lg-6'))
        ),
    ]);
}

function renderVideosBlock() {
    return sectionCard('🎬', 'Videos & Transcoding', [
        infoRow('Videos uploaded', '—'),
        infoRow('Transcoding sessions total', '—'),
        infoRow('Currently transcoding', '—'),
        infoRow('Tasks in queue', '—'),
        infoRow('Next encoding starts in', '—'),
    ]);
}

function renderStorageBlock(tariff) {
    if (!tariff?.storage) {
        return sectionCard('💾', 'Storage', [
            h('p', { class: 'text-muted mb-0' }, 'No storage information available.'),
        ]);
    }

    const storagePercent = computeStoragePercent(tariff);
    const storageFreeBytes = computeStorageFreeBytes(tariff);
    const storageNow = tariff.storage.now ?? 0;
    const storageMax = tariff.storage.max ?? 0;
    const storageHour = tariff.storage.hour ?? 0;

    const barColor = storagePercent >= 90 ? 'bg-danger' : storagePercent >= 70 ? 'bg-warning' : 'bg-primary';

    const progressBar = h('div', { class: 'mt-1 mb-3' }, [
        h('div', { class: 'd-flex justify-content-between small text-secondary mb-1' }, [
            h('span', `${formatBytes(storageNow)} used of ${formatBytes(storageMax)}`),
            h('span', [
                `${storagePercent}%`,
                storageFreeBytes > 0
                    ? h('span', { class: 'text-success' }, ` · ${formatBytes(storageFreeBytes)} free`)
                    : null,
            ]),
        ]),
        h('div', { class: 'progress', style: 'height: 10px;' }, [
            h('div', {
                class: `progress-bar ${barColor}`,
                style: { width: `${storagePercent}%` },
            }),
        ]),
    ]);

    const retentionNote = storageHour > 0
        ? h('p', { class: 'text-muted small mb-0 mt-3' }, [
            h('span', { class: 'me-1' }, '⏱'),
            `Files older than ${storageHour} hours may be automatically removed.`,
        ])
        : null;

    return sectionCard('💾', 'Storage', [
        progressBar,
        infoRow('Used', bytesToHuman(storageNow)),
        infoRow('Free', bytesToHuman(storageFreeBytes)),
        infoRow('Total quota', bytesToHuman(storageMax)),
        infoRow('Files expiring soon', '—'),
        retentionNote,
    ]);
}

// ─── main render ──────────────────────────────────────────────────────────────

export function renderProfile(vm) {
    const backBtn = h('button', {
        type: 'button',
        class: 'btn btn-outline-secondary btn-sm',
        onClick: vm.goHome,
    }, 'Back');

    if (vm.loading) {
        return h('div', { class: 'py-4' }, [
            h('div', { class: 'd-flex justify-content-between align-items-center mb-4' }, [
                h('h1', { class: 'mb-0' }, 'Profile'),
                backBtn,
            ]),
            h('p', { class: 'text-muted' }, 'Loading...'),
        ]);
    }

    if (vm.error) {
        return h('div', { class: 'py-4' }, [
            h('div', { class: 'd-flex justify-content-between align-items-center mb-4' }, [
                h('h1', { class: 'mb-0' }, 'Profile'),
                backBtn,
            ]),
            h('div', { class: 'alert alert-danger' }, vm.error),
        ]);
    }

    const cfg = vm.config || {};
    const user = cfg.user || null;
    const tariff = cfg.tariff || null;

    return h('div', { class: 'py-4' }, [
        h('div', { class: 'd-flex justify-content-between align-items-center mb-4' }, [
            h('h1', { class: 'mb-0' }, 'Profile'),
            backBtn,
        ]),
        renderUserBlock(user, tariff),
        renderTariffBlock(tariff),
        // Two-column layout for Videos and Storage
        h('div', { class: 'row g-4' }, [
            h('div', { class: 'col-12 col-lg-6' }, [renderVideosBlock()]),
            h('div', { class: 'col-12 col-lg-6' }, [renderStorageBlock(tariff)]),
        ]),
    ]);
}
