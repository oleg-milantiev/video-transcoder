import { h } from 'vue';
import { secondsToHuman, bytesToHuman, megabytesToHuman } from '../shared.js';

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
