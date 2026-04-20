import { h } from 'vue';

/**
 * Renders the action button(s) for a single task row.
 *
 * @param {Object} task        - Task DTO (id, status, presetId, height, videoId, videoTitle, presetTitle, downloadFilename)
 * @param {Object} taskActions - Result of createTaskActions(): { activeKey, cancelTask, startTranscode, getDownloadUrl }
 */
export function renderTaskAction(task, taskActions) {
    if (!task || !task.id) {
        return h('span', { class: 'text-muted' }, '-');
    }

    const { activeKey, cancelTask, startTranscode, getDownloadUrl } = taskActions;
    const currentKey = activeKey.value;

    // COMPLETED — Download
    if (task.status === 'COMPLETED') {
        let filename = task.downloadFilename;
        if (!filename && task.videoTitle && task.presetTitle && task.height) {
            const parts = task.presetTitle.split('/');
            const ext = parts.pop();
            filename = `${task.videoTitle}-${parts.join('-')}-${task.height}p.${ext}`;
        }
        return h(
            'a',
            {
                href: getDownloadUrl(task.id),
                class: 'btn btn-outline-primary btn-sm',
                download: filename || undefined,
            },
            'Download',
        );
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
