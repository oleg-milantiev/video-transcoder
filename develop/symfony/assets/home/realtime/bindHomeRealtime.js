import { parseAppTaskMessage } from './appTaskMessage.js';
import { parseAppVideoMessage } from './appVideoMessage.js';
import { parseAppStorageMessage } from './appStorageMessage.js';

export function bindHomeRealtime(handlers) {
    const onTaskMessage = function (event) {
        const payload = parseAppTaskMessage(event.detail);
        if (payload) {
            handlers.onTask(payload);
        }
    };

    const onVideoMessage = function (event) {
        const payload = parseAppVideoMessage(event.detail);
        if (payload) {
            handlers.onVideo(payload);
        }
    };

    const onStorageMessage = function (event) {
        const payload = parseAppStorageMessage(event.detail);
        if (payload && typeof handlers.onStorage === 'function') {
            handlers.onStorage(payload);
        }
    };

    window.addEventListener('app:task', onTaskMessage);
    window.addEventListener('app:video', onVideoMessage);
    window.addEventListener('app:storage', onStorageMessage);

    return function unbindHomeRealtime() {
        window.removeEventListener('app:task', onTaskMessage);
        window.removeEventListener('app:video', onVideoMessage);
        window.removeEventListener('app:storage', onStorageMessage);
    };
}
