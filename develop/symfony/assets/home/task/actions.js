import { ref } from 'vue';
import { parseJsonResponse, extractApiErrorMessage, normalizeErrorMessage } from '../shared.js';
import { authFetch } from '../apiAuth.js';
import { apiTaskCancelUrl, apiVideoTranscodeUrl, taskDownloadUrl as buildTaskDownloadUrl } from '../routes.js';

/**
 * Creates shared task API actions.
 *
 * @param {Object} params
 * @param {Object} params.config         - App config (token etc.)
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
            const url = apiTaskCancelUrl(taskId);
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
            const url = apiVideoTranscodeUrl(videoId, presetId, height);
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
        return buildTaskDownloadUrl(taskId);
    }

    return { activeKey, cancelTask, startTranscode, getDownloadUrl };
}
