import { h } from 'vue';
function secondsToHuman(sec) {
    if (typeof sec !== 'number' || !Number.isFinite(sec)) return '-';
    if (sec < 60) return `${sec} s`;
    const minutes = Math.floor(sec / 60);
    if (minutes < 60) return `${minutes} m`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} h`;
    const days = Math.floor(hours / 24);
    return `${days} d`;
}

function bytesToHuman(bytes) {
    if (typeof bytes !== 'number' || !Number.isFinite(bytes)) return '-';
    if (bytes < 1024) return `${bytes} B`;
    const units = ['KB', 'MB', 'GB', 'TB'];
    let value = bytes / 1024;
    let i = 0;
    while (value >= 1024 && i < units.length - 1) {
        value = value / 1024;
        i += 1;
    }
    return `${Math.round(value * 10) / 10} ${units[i]}`;
}

function megabytesToHuman(mb) {
    if (typeof mb !== 'number' || !Number.isFinite(mb)) return '-';
    if (mb < 1024) return `${mb} MB`;
    const gb = Math.round((mb / 1024) * 10) / 10;
    return `${gb} GB`;
}

export function renderProfile(vm) {
    if (vm.loading) {
        return h('div', { class: 'py-4' }, [
            h('h1', { class: 'mb-3' }, 'Profile'),
            h('p', { class: 'text-muted' }, 'Loading...'),
        ]);
    }

    if (vm.error) {
        return h('div', { class: 'py-4' }, [
            h('h1', { class: 'mb-3' }, 'Profile'),
            h('div', { class: 'alert alert-danger' }, vm.error),
            h(
                'button',
                {
                    type: 'button',
                    class: 'btn btn-outline-secondary',
                    onClick: vm.goHome,
                },
                'Back to home'
            ),
        ]);
    }

    const cfg = vm.config || {};
    const tariff = cfg.tariff || null;

    if (!tariff) {
        return h('div', { class: 'py-4' }, [
            h('h1', { class: 'mb-3' }, 'Profile'),
            h('p', { class: 'text-muted' }, 'No tariff information available.'),
        ]);
    }

    return h('div', { class: 'py-4' }, [
        h('h1', { class: 'mb-3' }, 'Profile'),
        h('h2', { class: 'h5 mt-3' }, 'Tariff'),
        h('ul', { class: 'list-group' }, [
            h('li', { class: 'list-group-item' }, `Title: ${tariff.title ?? '-'}`),
            h('li', { class: 'list-group-item' }, `Delay: ${secondsToHuman(tariff.delay ?? NaN)}`),
            h('li', { class: 'list-group-item' }, `Instances: ${tariff.instance ?? '-'}`),
            h('li', { class: 'list-group-item' }, `Max video duration: ${secondsToHuman(tariff.videoDuration ?? NaN)}`),
            h('li', { class: 'list-group-item' }, `Max video size: ${megabytesToHuman(tariff.videoSize ?? NaN)}`),
            h('li', { class: 'list-group-item' }, `Max resolution: ${tariff.width ?? '-'} x ${tariff.height ?? '-'}`),
            h('li', { class: 'list-group-item' }, `Storage used: ${bytesToHuman(tariff.storage?.now ?? NaN)} / ${bytesToHuman(tariff.storage?.max ?? NaN)}`),
            h('li', { class: 'list-group-item' }, `Storage policy: files older than ${secondsToHuman((tariff.storage?.hour ?? NaN) * 3600)} will be removed`),
        ]),
    ]);
}
