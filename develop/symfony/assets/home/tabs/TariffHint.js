import { h } from 'vue';

const GB = 1024 * 1024 * 1024;
const MB = 1024 * 1024;

function formatBytes(bytes) {
    if (bytes >= GB) {
        const val = bytes / GB;
        return (Number.isInteger(val) ? val.toString() : val.toFixed(1)) + ' GB';
    }
    return Math.round(bytes / MB) + ' MB';
}

function computeStoragePercent(tariff) {
    if (!tariff || !tariff.storage) return 0;
    const max = tariff.storage.max;
    return max > 0 ? Math.round((tariff.storage.now / max) * 100) : 0;
}

function computeStorageFreeBytes(tariff) {
    if (!tariff || !tariff.storage) return 0;
    return Math.max(0, tariff.storage.max - tariff.storage.now);
}

function computeEffectiveVideoSize(tariff) {
    if (!tariff) return 0;
    const remainingBytes = computeStorageFreeBytes(tariff);
    const remainingMB = remainingBytes / MB;
    const videoSizeMB = tariff.videoSize;

    if (remainingMB < videoSizeMB) {
        return Math.max(0, Math.floor(remainingMB));
    }
    return videoSizeMB;
}

function isStorageLow(tariff) {
    if (!tariff) return false;
    const remainingBytes = computeStorageFreeBytes(tariff);
    const remainingMB = remainingBytes / MB;
    return remainingMB < tariff.videoSize;
}

export function renderTariffHint(tariff) {
    if (!tariff || !tariff.storage) {
        return null;
    }

    const storagePercent = computeStoragePercent(tariff);
    const storageFreeBytes = computeStorageFreeBytes(tariff);
    const effectiveVideoSize = computeEffectiveVideoSize(tariff);
    const isLow = isStorageLow(tariff);

    return h('div', { class: 'bg-light bg-opacity-10 rounded-4 p-3 mt-3' }, [
        // Main grid container
        h('div', { class: 'row g-3 align-items-center' }, [
            // Left column with stats and progress
            h('div', { class: 'col' }, [
                // Stats Grid
                h('div', { class: 'row g-2 text-center text-sm-start' }, [
                    // Storage
                    h('div', { class: 'col-sm-3' }, [
                        h('span', { class: 'text-secondary small' }, 'Storage'),
                        h('div', { class: 'fw-semibold' }, `${formatBytes(tariff.storage.now)} / ${formatBytes(tariff.storage.max)}`),
                    ]),
                    // Max file size
                    h('div', { class: 'col-sm-3' }, [
                        h('span', { class: 'text-secondary small' }, 'Max file size'),
                        h('div', { class: 'fw-semibold' }, `${effectiveVideoSize} MB`),
                    ]),
                    // Max resolution
                    h('div', { class: 'col-sm-3' }, [
                        h('span', { class: 'text-secondary small' }, 'Max resolution'),
                        h('div', { class: 'fw-semibold' }, `${tariff.width}\u00d7${tariff.height}`),
                    ]),
                    // Concurrent tasks
                    h('div', { class: 'col-sm-3' }, [
                        h('span', { class: 'text-secondary small' }, 'Concurrent tasks'),
                        h('div', { class: 'fw-semibold' }, '1'),
                    ]),
                ]),
                // Storage Progress Bar
                h('div', { class: 'mt-3' }, [
                    h('div', { class: 'd-flex justify-content-between small text-secondary mb-1' }, [
                        h('span', 'Storage used'),
                        h('span', [
                            `${storagePercent}% used`,
                            storageFreeBytes > 0
                                ? h('span', { class: 'text-success' }, ` · ${formatBytes(storageFreeBytes)} free`)
                                : null,
                        ]),
                    ]),
                    h('div', { class: 'progress', style: 'height: 8px;' }, [
                        h('div', {
                            class: 'progress-bar bg-primary',
                            style: { width: `${storagePercent}%` },
                        }),
                    ]),
                ]),
            ]),
            // Right column with upgrade button
            h('div', { class: 'col-auto' }, [
                h('a', { href: '#', class: 'btn btn-sm btn-outline-primary rounded-pill' }, 'Upgrade'),
            ]),
        ]),
        // Warning when storage is running low
        isLow
            ? h('div', { class: 'alert alert-warning mt-3 mb-0 small', role: 'alert' }, `Storage is running low. Max file size is limited to ${effectiveVideoSize} MB.`)
            : null,
    ]);
}

