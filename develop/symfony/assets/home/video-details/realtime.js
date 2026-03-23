import { parseAppTaskMessage } from '../realtime/appTaskMessage.js';
import { parseAppVideoMessage } from '../realtime/appVideoMessage.js';

export function bindVideoDetailsRealtime(handlers) {
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

    window.addEventListener('app:task', onTaskMessage);
    window.addEventListener('app:video', onVideoMessage);

    return function unbindVideoDetailsRealtime() {
        window.removeEventListener('app:task', onTaskMessage);
        window.removeEventListener('app:video', onVideoMessage);
    };
}

