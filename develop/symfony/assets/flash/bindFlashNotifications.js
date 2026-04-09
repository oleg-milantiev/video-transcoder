import Swal from 'sweetalert2';

const EVENT_NAME = 'app:flash';
const BOUND_FLAG = '__appFlashListenerBound';

const ICON_BY_LEVEL = {
    success: 'success',
    info: 'info',
    warning: 'warning',
    error: 'error',
    danger: 'error',
};

export function normalizeNotification(detail) {
    if (typeof detail === 'string') {
        return {
            html: detail,
        };
    }

    if (!detail || typeof detail !== 'object') {
        return null;
    }

    return {
        title: detail.title || '',
        html: detail.html || detail.message || detail.text || '',
        level: detail.level || detail.type || 'info',
        timer: Number.isFinite(detail.timer) ? detail.timer : 5000,
        imageUrl: detail.imageUrl || detail.image || '',
        imageAlt: detail.imageAlt || detail.title || 'notification image',
        position: detail.position || 'top-end',
    };
}

export function toSwalOptions(notification) {
    const icon = ICON_BY_LEVEL[String(notification.level || '').toLowerCase()] || 'info';
    const options = {
        toast: true,
        icon,
        position: notification.position,
        timer: notification.timer,
        timerProgressBar: true,
        showConfirmButton: false,
        customClass: {
            popup: 'app-flash-toast',
            title: 'app-flash-title',
            htmlContainer: 'app-flash-html',
        },
    };

    if (notification.title) {
        options.title = notification.title;
    }

    if (notification.html) {
        options.html = notification.html;
    }

    if (notification.imageUrl) {
        options.imageUrl = notification.imageUrl;
        options.imageAlt = notification.imageAlt;
    }

    return options;
}

export function bindFlashNotifications() {
    if (window[BOUND_FLAG]) {
        return;
    }

    const onFlash = function (event) {
        const notification = normalizeNotification(event.detail);
        if (!notification) {
            return;
        }

        void Swal.fire(toSwalOptions(notification));
    };

    window.addEventListener(EVENT_NAME, onFlash);
    window[BOUND_FLAG] = true;
}
