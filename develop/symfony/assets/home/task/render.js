import { h } from 'vue';
import { bytesToHuman } from '../shared.js';

/**
 * Renders the action button(s) for a single task row.
 *
 * @param {Object} task        - Task DTO (id, status, presetId, height, videoId, videoTitle, presetTitle, size)
 * @param {Object} taskActions - Result of createTaskActions(): { activeKey, cancelTask, startTranscode, getDownloadUrl }
 */
export function renderTaskAction(task, taskActions) {
    if (!task || !task.id || task.deleted) {
        return h('span', { class: 'text-muted' }, '-');
    }

    const { activeKey, cancelTask, startTranscode, getDownloadUrl } = taskActions;
    const currentKey = activeKey.value;

    // COMPLETED — Download
    if (task.status === 'COMPLETED') {
        return h('div', {}, [
            h(
                'a',
                {
                    href: getDownloadUrl(task.id),
                    class: 'btn btn-outline-primary btn-sm',
                    download: `${task.videoTitle}-${task.presetAudioCodec}-${task.presetVideoCodec}-${task.height}p.${task.presetFormat}`,
                },
                'Download',
            ),
            typeof task.size === 'number' && task.size > 0
                ? h('div', { class: 'small text-center text-muted mt-1', style: 'font-size: 0.7rem;' }, `${bytesToHuman(task.size)}`)
                : null,
        ]);
    }

    // PENDING / STARTING / PROCESSING — Cancel
    if (task.status === 'PENDING' || task.status === 'STARTING' || task.status === 'PROCESSING') {
        const key = 'cancel-' + String(task.id);
        const isActive = currentKey === key;
        return h(
            'button',
            {
                type: 'button',
                class: 'btn btn-outline-primary btn-sm',
                disabled: isActive,
                onClick: () => cancelTask(task.id),
            },
            isActive ? 'Cancelling...' : 'Cancel',
        );
    }

    // CANCELLED — Transcode (restart); requires task.videoId
    if (task.status === 'CANCELLED' && task.presetId && task.height && task.videoId) {
        const key = 'transcode-' + String(task.presetId) + '-' + String(task.height);
        const isActive = currentKey === key;
        return h(
            'button',
            {
                type: 'button',
                class: 'btn btn-outline-primary btn-sm',
                disabled: isActive,
                onClick: () => startTranscode(task.videoId, task.presetId, task.height),
            },
            isActive ? 'Processing...' : 'Transcode',
        );
    }

    return h('span', { class: 'text-muted' }, '-');
}
