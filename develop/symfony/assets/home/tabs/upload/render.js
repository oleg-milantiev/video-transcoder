import { h } from 'vue';

export function renderUploadPane(paneClass) {
    return h('div', { class: paneClass }, [h('div', { id: 'drag-drop-area' })]);
}

