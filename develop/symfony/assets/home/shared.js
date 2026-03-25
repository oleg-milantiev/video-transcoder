export function createAuthHeader(apiBearerToken) {
    return apiBearerToken ? { Authorization: 'Bearer ' + apiBearerToken } : {};
}

export function createJsonAuthHeaders(apiBearerToken) {
    return {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...createAuthHeader(apiBearerToken),
    };
}

export function toInt(value, fallback) {
    const parsed = Number.parseInt(String(value), 10);
    return Number.isFinite(parsed) ? parsed : fallback;
}

export function replaceTemplateValue(template, placeholder, value) {
    return template.replace(placeholder, String(value));
}

export function normalizeErrorMessage(error, fallback) {
    if (error instanceof Error && error.message) {
        return error.message;
    }

    return fallback;
}

export function extractApiErrorMessage(payload, fallback) {
    if (payload && payload.error && typeof payload.error === 'object' && typeof payload.error.message === 'string') {
        return payload.error.message;
    }

    if (payload && typeof payload.error === 'string') {
        return payload.error;
    }

    return fallback;
}

export async function parseJsonResponse(response) {
    if (response && response.status === 401) {
        try {
            const current = window.location.pathname + window.location.search + window.location.hash;
            const loginUrl = new URL('/login', window.location.origin);
            loginUrl.searchParams.set('_target_path', current);
            window.location.href = loginUrl.toString();
            return null;
        } catch (e) {
            window.location.href = '/login';
            return null;
        }
    }

    try {
        return await response.json();
    } catch (e) {
        return null;
    }
}

