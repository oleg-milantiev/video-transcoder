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

// ─── persistent references on window ────────────────────────────────────────
// Turbo Drive re-evaluates <script type="module"> tags on every navigation,
// which resets all module-level `let` / `const` variables to their initial
// values.  Storing these on `window` keeps them alive across re-evaluations.
const ES_KEY       = '__mercureEventSource'; // EventSource instance
const HANDLER_KEY  = '__mercureCleanupFn';   // registered listener reference

function closeMercure() {
    const es = window[ES_KEY];
    if (es) {
        es.close();
        window[ES_KEY] = null;
    }
}

// Re-register turbo:before-render / beforeunload listeners every time this
// module is evaluated, but first remove the previous instance so we never
// accumulate duplicates (each re-eval gives `closeMercure` a new identity).
if (typeof window[HANDLER_KEY] === 'function') {
    document.removeEventListener('turbo:before-render', window[HANDLER_KEY]);
    window.removeEventListener('beforeunload',          window[HANDLER_KEY]);
}
window[HANDLER_KEY] = closeMercure;
document.addEventListener('turbo:before-render', closeMercure);
window.addEventListener('beforeunload',          closeMercure);

export function connectMercure(config, rootElement) {
    if (!('EventSource' in window)) {
        return;
    }

    if (!config.mercure.hub || !config.mercure.topic || !config.mercure.token) {
        return;
    }

    // DOM guard: DOMContentLoaded and turbo:load both fire on the initial page
    // load for the same rootElement — the second call must be a no-op.
    if (rootElement.dataset.mercureConnected === '1') {
        return;
    }
    rootElement.dataset.mercureConnected = '1';

    // Primary guard: always close whatever is currently stored on window before
    // opening a new connection.  turbo:before-render may have already done this,
    // but calling it here too handles cases where that event never fired.
    closeMercure();

    const eventSource = new EventSource(
        createHubUrl(config.mercure.hub, config.mercure.topic, config.mercure.token)
    );
    window[ES_KEY] = eventSource;

    eventSource.onopen = function () {
        console.log('[mercure] connected', {
            topic:  config.mercure.topic,
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

                if (payload.entity === 'storage') {
                    window.dispatchEvent(new CustomEvent('app:storage', { detail: payload }));
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
}
