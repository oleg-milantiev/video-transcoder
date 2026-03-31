import { h } from 'vue';
import { buildUploadHint } from './uploadHint.js';

export function renderUploadPane(paneClass, uppyReady, tariff) {
    // #drag-drop-area must be the FIRST child (stable position) with a stable key
    // so Vue's reconciliation preserves it — and the Uppy content inside it — when
    // the loading overlay is removed.  The overlay is pushed after (not unshifted
    // before) so position-0 is always the same element regardless of uppyReady.
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

    const hint = buildUploadHint(tariff);
    if (hint) {
        children.push(
            h('p', {
                key: 'upload-hint',
                class: 'text-muted small mt-2 mb-0',
            }, hint)
        );
    }

    return h('div', { class: paneClass, style: 'position:relative' }, children);
}
