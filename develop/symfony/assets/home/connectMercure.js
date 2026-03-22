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

    if (!config.mercureHubUrl || !config.mercureTopic || !config.mercureSubscriberToken) {
        return;
    }

    if (rootElement.dataset.mercureConnected === '1') {
        return;
    }

    const eventSource = new EventSource(
        createHubUrl(config.mercureHubUrl, config.mercureTopic, config.mercureSubscriberToken)
    );

    eventSource.onopen = function () {
        console.log('[mercure] connected', {
            topic: config.mercureTopic,
            userId: config.userId || null,
        });
    };

    eventSource.onmessage = function (event) {
        const payload = parseEventData(event.data);
        emitRealtimeMessage(payload);
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
