/**
 * Singleton auth manager.
 * Holds the current access + refresh tokens and provides authFetch(),
 * which transparently refreshes the access token on 401 and retries.
 */

let _accessToken = null;
let _refreshToken = null;
let _refreshUrl = null;

/** @type {Promise<void>|null} */
let _refreshInFlight = null;

/**
 * Initialize the auth manager. Call once at app startup.
 *
 * @param {{ accessToken: string|null, refreshToken: string|null, refreshUrl: string }} config
 */
export function initAuth(config) {
    _accessToken = config.accessToken || null;
    _refreshToken = config.refreshToken || null;
    _refreshUrl = config.refreshUrl || null;
}

/** @returns {string|null} */
export function getAccessToken() {
    return _accessToken;
}

/** @returns {Record<string, string>} */
export function getAuthHeader() {
    return _accessToken ? { Authorization: 'Bearer ' + _accessToken } : {};
}

/** @returns {Record<string, string>} */
export function getJsonAuthHeaders() {
    return {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...getAuthHeader(),
    };
}

/**
 * Attempt to refresh the access token using the stored refresh token.
 * Concurrent calls share the same in-flight request.
 *
 * @returns {Promise<void>}
 */
async function doRefresh() {
    if (!_refreshToken || !_refreshUrl) {
        throw new Error('No refresh token available');
    }

    const response = await fetch(_refreshUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ refreshToken: _refreshToken }),
    });

    if (!response.ok) {
        throw new Error('Token refresh failed');
    }

    const data = await response.json();
    if (!data || typeof data.accessToken !== 'string' || typeof data.refreshToken !== 'string') {
        throw new Error('Invalid refresh response');
    }

    _accessToken = data.accessToken;
    _refreshToken = data.refreshToken;
}

function ensureFreshToken() {
    if (!_refreshInFlight) {
        _refreshInFlight = doRefresh().finally(() => {
            _refreshInFlight = null;
        });
    }

    return _refreshInFlight;
}

/**
 * Drop-in replacement for fetch() that automatically:
 *  1. Injects the current Authorization header.
 *  2. On 401, refreshes the token and retries once.
 *  3. If refresh fails, reloads the page (same behavior as the old parseJsonResponse).
 *
 * All other options (method, body, headers, credentials, …) are forwarded as-is.
 * The Authorization header is always overridden with the current token.
 *
 * @param {string|URL} url
 * @param {RequestInit} [options]
 * @returns {Promise<Response>}
 */
export async function authFetch(url, options = {}) {
    const headers = {
        ...(options.headers || {}),
        ...getAuthHeader(),
    };

    const response = await fetch(url, { ...options, headers });

    if (response.status !== 401 || !_refreshToken) {
        return response;
    }

    try {
        await ensureFreshToken();
    } catch {
        window.location.reload();
        return response;
    }

    // Retry with the freshly obtained token.
    const retryHeaders = {
        ...(options.headers || {}),
        ...getAuthHeader(),
    };

    return fetch(url, { ...options, headers: retryHeaders });
}
