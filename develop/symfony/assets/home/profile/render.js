import { h } from 'vue';
import {bytesToHuman, humanReadableDateTime} from '../shared.js';
import { PLANS, renderFeature } from '../tariff/planCard.js';
import { formatBytes } from '../shared/StorageBadge.js';

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
        h('div', { class: 'col-sm-7 text-muted' }, label),
        h('div', { class: 'col-sm-5 fw-semibold' }, value),
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
        infoRow('Email', user.identifier),
        infoRow('Member since', humanReadableDateTime(user.createdAt)),
        infoRow('Current plan', tariffCell),
    ]);
}

function renderPaymentsBlock() {
    return h('div', { class: 'card shadow-sm mb-4 h-100' }, [
        h('div', { class: 'card-header bg-light d-flex align-items-center gap-2 py-3' }, [
            h('span', { class: 'fs-4 lh-1' }, '💳'),
            h('h2', { class: 'h5 mb-0' }, 'Subscription'),
        ]),
        h('div', { class: 'card-body' }, [
            infoRow('Valid until', '—'),
            infoRow('Days remaining', '—'),
            h('hr', { class: 'my-3' }),
            h('h6', { class: 'text-muted small mb-3' }, 'Payment History'),
            h('p', { class: 'text-muted small mb-0' }, 'No payments yet.'),
        ]),
    ]);
}

function renderTariffBlock(tariff) {
    const title = tariff?.title ?? '';
    const currentPlanKey = PLANS.find(
        (p) => p.name.toLowerCase() === title.toLowerCase()
    )?.key ?? 'free';

    const isFree = currentPlanKey === 'free';

    if (isFree) {
        // Show two tariff cards: Free (current) and Premium (upgrade option)
        const plansWithCurrent = [
            { ...PLANS[0], isCurrent: true },  // Free
            { ...PLANS[1], isCurrent: false }, // Premium
        ];

        return h('div', { class: 'row g-4 mb-4' }, [
            h('div', { class: 'col-12 col-lg-6' }, [
                h('div', { class: 'card shadow-sm h-100' }, [
                    h('div', { class: 'card-header bg-light d-flex align-items-center gap-2 py-3' }, [
                        h('span', { class: 'fs-4 lh-1' }, '💳'),
                        h('h2', { class: 'h5 mb-0' }, 'Tariff'),
                    ]),
                    h('ul', { class: 'list-group list-group-flush flex-grow-1' },
                        plansWithCurrent[0].features.map(f => renderFeature(f))
                    ),
                    h('div', { class: 'card-footer bg-transparent py-3' }, [
                        h('button', { class: 'btn btn-outline-secondary w-100', disabled: true }, 'Current plan'),
                    ]),
                ]),
            ]),
            h('div', { class: 'col-12 col-lg-6' }, [
                h('div', { class: 'card shadow-sm h-100 border-primary' }, [
                    h('div', { class: 'card-header bg-primary text-white d-flex align-items-center gap-2 py-3' }, [
                        h('span', { class: 'fs-4 lh-1' }, '⭐'),
                        h('h2', { class: 'h5 mb-0' }, 'Premium'),
                    ]),
                    h('ul', { class: 'list-group list-group-flush flex-grow-1' },
                        plansWithCurrent[1].features.map(f => renderFeature(f))
                    ),
                    h('div', { class: 'card-footer bg-transparent py-3' }, [
                        h('button', {
                            class: 'btn btn-primary w-100',
                            type: 'button',
                        }, 'Upgrade to Premium'),
                    ]),
                ]),
            ]),
        ]);
    } else {
        // Not Free: show current tariff (left) + payments block (right)
        const currentPlan = PLANS.find(p => p.key === currentPlanKey);
        if (!currentPlan) {
            return h('div', { class: 'text-muted mb-4' }, 'Tariff information unavailable.');
        }

        return h('div', { class: 'row g-4 mb-4' }, [
            h('div', { class: 'col-12 col-lg-6' }, [
                h('div', { class: 'card shadow-sm h-100' }, [
                    h('div', { class: 'card-header bg-light d-flex align-items-center gap-2 py-3' }, [
                        h('span', { class: 'fs-4 lh-1' }, '💳'),
                        h('h2', { class: 'h5 mb-0' }, 'Your Plan'),
                    ]),
                    h('ul', { class: 'list-group list-group-flush flex-grow-1' },
                        currentPlan.features.map(f => renderFeature(f))
                    ),
                    h('div', { class: 'card-footer bg-transparent py-3' }, [
                        h('button', { class: 'btn btn-outline-secondary w-100', disabled: true }, 'Current plan'),
                    ]),
                ]),
            ]),
            h('div', { class: 'col-12 col-lg-6' }, [
                renderPaymentsBlock(),
            ]),
        ]);
    }
}

function renderVideosBlock(dto) {
    return sectionCard('🎬', 'Videos & Transcoding', [
        infoRow('Videos active / total', (dto?.statistics?.video?.active ?? '—') +' / '+ (dto?.statistics?.video?.total ?? '—')),
        infoRow('Transcoding active / total', (dto?.statistics?.task?.active ?? '—') +' / '+ (dto?.statistics?.task?.total ?? '—')),
        infoRow('Currently transcoding', dto?.statistics?.task?.processing ?? '—'),
        infoRow('Tasks in queue', dto?.statistics?.task?.queue ?? '—'),
        infoRow('Completed transcoding', dto?.statistics?.task?.completed ?? '—'),
        infoRow('Next encoding starts at', humanReadableDateTime(dto?.statistics?.task?.willStartAt)),
    ]);
}

function renderStorageBlock(tariff, dto) {
    // Build storage object from dto if available, otherwise use tariff config
    let storage = null;
    if (dto && (dto.storageUsedBytes !== undefined || dto.storageMaxBytes !== undefined)) {
        storage = {
            now: dto.storageUsedBytes ?? 0,
            max: dto.storageMaxBytes ?? 0,
            hour: tariff?.storage?.hour ?? 0,
        };
    } else if (tariff?.storage) {
        storage = tariff.storage;
    }

    if (!storage?.max) {
        return sectionCard('💾', 'Storage', [
            h('p', { class: 'text-muted mb-0' }, 'No storage information available.'),
        ]);
    }

    const storagePercent = Math.round((storage.now / storage.max) * 100) || 0;
    const storageFreeBytes = Math.max(0, storage.max - storage.now);

    const barColor = storagePercent >= 90 ? 'bg-danger' : storagePercent >= 70 ? 'bg-warning' : 'bg-primary';

    const progressBar = h('div', { class: 'mt-1 mb-3' }, [
        h('div', { class: 'd-flex justify-content-between small text-secondary mb-1' }, [
            h('span', `${formatBytes(storage.now)} used of ${formatBytes(storage.max)}`),
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

    const retentionNote = storage.hour > 0
        ? h('p', { class: 'text-muted small mb-0 mt-3' }, [
            h('span', { class: 'me-1' }, '⏱'),
            `Videos older than ${storage.hour} hours will be automatically removed.`,
        ])
        : null;

    return sectionCard('💾', 'Storage', [
        progressBar,
        infoRow('Used', bytesToHuman(storage.now)),
        infoRow('Free', bytesToHuman(storageFreeBytes)),
        infoRow('Total quota', bytesToHuman(storage.max)),
        infoRow('Expiring in 24h', bytesToHuman(dto?.storage?.delete24 ?? 0)),
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
        return h('div', { class: 'py-2' }, [
            h('div', { class: 'd-flex justify-content-between align-items-start mb-3' }, [
                h('div', { class: 'text-center flex-grow-1' }, [
                    h('h1', { class: 'display-5 fw-bold mb-2' }, 'My Profile'),
                    h('p', { class: 'text-muted fs-5' }, 'Account settings and subscription overview.'),
                ]),
                backBtn,
            ]),
            h('p', { class: 'text-muted' }, 'Loading...'),
        ]);
    }

    if (vm.error) {
        return h('div', { class: 'py-2' }, [
            h('div', { class: 'd-flex justify-content-between align-items-start mb-3' }, [
                h('div', { class: 'text-center flex-grow-1' }, [
                    h('h1', { class: 'display-5 fw-bold mb-2' }, 'My Profile'),
                    h('p', { class: 'text-muted fs-5' }, 'Account settings and subscription overview.'),
                ]),
                backBtn,
            ]),
            h('div', { class: 'alert alert-danger' }, vm.error),
        ]);
    }

    const cfg = vm.config || {};
    const user = cfg.user || null;
    const tariff = cfg.tariff || null;
    const dto = vm.dto || null;

    return h('div', { class: 'py-2' }, [
        h('div', { class: 'd-flex justify-content-between align-items-start mb-3' }, [
            h('div', { class: 'text-center flex-grow-1' }, [
                h('h1', { class: 'display-5 fw-bold mb-2' }, 'My Profile'),
                h('p', { class: 'text-muted fs-5' }, 'Account settings and subscription overview.'),
            ]),
            backBtn,
        ]),
        renderUserBlock(user, tariff),
        renderTariffBlock(tariff),
        // Two-column layout for Videos and Storage
        h('div', { class: 'row g-4' }, [
            h('div', { class: 'col-12 col-lg-6' }, [renderVideosBlock(dto)]),
            h('div', { class: 'col-12 col-lg-6' }, [renderStorageBlock(tariff, dto)]),
        ]),
    ]);
}
