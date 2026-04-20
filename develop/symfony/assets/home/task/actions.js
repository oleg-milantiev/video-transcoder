import { ref } from 'vue';
import { parseJsonResponse, extractApiErrorMessage, normalizeErrorMessage, replaceTemplateValue } from '../shared.js';
import { authFetch } from '../apiAuth.js';

/**
 * Creates shared task API actions.
 *
 * @param {Object} params
 * @param {Object} params.config         - App config with route templates
 * @param {Function} [params.onSuccess]  - Called after a successful action (key, payload)
 * @param {Function} [params.onError]    - Called with error message string on failure
 */
export function createTaskActions({ config, onSuccess, onError }) {
    const activeKey = ref('');

    function setError(msg) {
        onError?.(msg);
    }

    async function cancelTask(taskId) {
        const key = 'cancel-' + String(taskId);
        activeKey.value = key;
        setError('');

        try {
            const url = replaceTemplateValue(config.route.task.cancel, '__TASK_ID__', String(taskId));
            const response = await authFetch(url, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const payload = await parseJsonResponse(response);

            if (!response.ok) {
                setError(extractApiErrorMessage(payload, 'Failed to cancel task'));
                return;
            }

            onSuccess?.(key, payload);
        } catch (e) {
            setError(normalizeErrorMessage(e, 'Failed to cancel task'));
        } finally {
            activeKey.value = '';
        }
    }

    async function startTranscode(videoId, presetId, height) {
        const key = 'transcode-' + String(presetId) + '-' + String(height);
        activeKey.value = key;
        setError('');

        try {
            const url = replaceTemplateValue(
                replaceTemplateValue(
                    replaceTemplateValue(config.route.video.transcode, '__UUID__', videoId),
                    '__PRESET_ID__', presetId,
                ),
                '__HEIGHT__', height,
            );
            const response = await authFetch(url, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const payload = await parseJsonResponse(response);

            if (!response.ok) {
                setError(extractApiErrorMessage(payload, 'Failed to start transcode'));
                return;
            }

            onSuccess?.(key, payload);
        } catch (e) {
            setError(normalizeErrorMessage(e, 'Failed to start transcode'));
        } finally {
            activeKey.value = '';
        }
    }

    function getDownloadUrl(taskId) {
        return replaceTemplateValue(config.route.task.download, '__TASK_ID__', String(taskId));
    }

    return { activeKey, cancelTask, startTranscode, getDownloadUrl };
}
