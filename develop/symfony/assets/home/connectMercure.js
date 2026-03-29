function createHubUrl(hubUrl, topic, subscriberToken) {
    const url = new URL(hubUrl, window.location.origin);
    url.searchParams.append('topic', topic);
    url.searchParams.set('authorization', subscriberToken);

    return url.toString();
}

function parseEventData(rawData) {
    if (!rawData) {
        return null;
    }

    try {
        return JSON.parse(rawData);
    } catch (e) {
        return rawData;
    }
}

function emitRealtimeMessage(payload) {
    if (!payload || typeof payload !== 'object') {
        return;
    }

    window.dispatchEvent(new CustomEvent('mercure:message', { detail: payload }));
}

export function connectMercure(config, rootElement) {
    if (!('EventSource' in window)) {
        return;
    }

    if (!config.mercure.hub || !config.mercure.topic || !config.mercure.token) {
        return;
    }

    if (rootElement.dataset.mercureConnected === '1') {
        return;
    }

    const eventSource = new EventSource(
        createHubUrl(config.mercure.hub, config.mercure.topic, config.mercure.token)
    );

    eventSource.onopen = function () {
        console.log('[mercure] connected', {
            topic: config.mercure.topic,
            userId: config.user ? config.user.id : null,
        });
    };

    eventSource.onmessage = function (event) {
        const payload = parseEventData(event.data);
        emitRealtimeMessage(payload);

        // additional app-level events
        try {
            if (payload && typeof payload === 'object') {
                if (payload.entity === 'video') {
                    window.dispatchEvent(new CustomEvent('app:video', { detail: payload }));
                }

                if (payload.entity === 'task') {
                    window.dispatchEvent(new CustomEvent('app:task', { detail: payload }));
                }

                if (payload.payload && payload.payload.notification) {
                    window.dispatchEvent(new CustomEvent('app:flash', { detail: payload.payload.notification }));
                }
            }
        } catch (e) {
            // ignore
        }

        console.log('[mercure] message', payload);
    };

    eventSource.onerror = function () {
        console.log('[mercure] connection error');
    };

    rootElement.dataset.mercureConnected = '1';
    window.addEventListener('beforeunload', function () {
        eventSource.close();
    }, { once: true });
}
