import { h } from 'vue';
import { computeEffectiveVideoSize } from '../../shared/StorageBadge.js';

export function renderUploadPane(paneClass, uppyReady, tariff) {
    const children = [h('div', { key: 'uppy-target', id: 'drag-drop-area' })];

    if (!uppyReady) {
        children.push(
            h('div', {
                key: 'uppy-loading',
                class: 'd-flex justify-content-center align-items-center',
                style: 'position:absolute;inset:0;z-index:10;background:rgba(248,249,250,0.85)',
            }, [
                h('div', { class: 'spinner-border text-secondary', role: 'status' }, [
                    h('span', { class: 'visually-hidden' }, 'Loading…'),
                ]),
            ])
        );
    }

    const isStorageFull = tariff && tariff.storage && computeEffectiveVideoSize(tariff) === 0;

    if (isStorageFull) {
        children.push(
            h('div', {
                key: 'storage-full-overlay',
                style: 'position:absolute;inset:0;z-index:20;background:rgba(248,249,250,0.96);display:flex;align-items:center;justify-content:center;',
            }, [
                h('div', { class: 'text-center px-4' }, [
                    h('div', { style: 'font-size:3rem;line-height:1;margin-bottom:1rem' }, '🗄️'),
                    h('h5', { class: 'fw-bold text-danger mb-2' }, 'Storage Full'),
                    h('p', { class: 'text-secondary mb-3' }, [
                        'Your storage is full. Delete one or more existing videos to free up space, or ',
                        h('a', { href: '/tariffs', class: 'fw-semibold' }, 'upgrade your plan'),
                        '.',
                    ]),
                ]),
            ])
        );
    }

    return h('div', { class: paneClass, style: 'position:relative' }, children);
}
