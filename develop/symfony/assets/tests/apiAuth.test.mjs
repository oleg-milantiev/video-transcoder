/**
 * Tests for assets/home/apiAuth.js
 * Covers: initAuth, getAccessToken, getAuthHeader, getJsonAuthHeaders.
 * (authFetch requires a network / window.location and is not tested here.)
 * Run: node assets/tests/apiAuth.test.mjs
 */
import assert from 'node:assert/strict';
import {
    initAuth,
    getAccessToken,
    getAuthHeader,
    getJsonAuthHeaders,
} from '../home/apiAuth.js';

// ── initial state: no token ───────────────────────────────────────────────────

initAuth({ accessToken: null, refreshToken: null, refreshUrl: '/api/refresh' });
assert.equal(getAccessToken(), null, 'no access token initially');
assert.deepEqual(getAuthHeader(), {}, 'empty auth header when no token');

const noTokenHeaders = getJsonAuthHeaders();
assert.equal(noTokenHeaders['Content-Type'], 'application/json', 'Content-Type set');
assert.equal(noTokenHeaders['X-Requested-With'], 'XMLHttpRequest', 'X-Requested-With set');
assert.ok(!('Authorization' in noTokenHeaders), 'no Authorization when no token');
console.log('✓ no-token state');

// ── after initAuth with a token ───────────────────────────────────────────────

initAuth({ accessToken: 'mytoken123', refreshToken: 'refresh456', refreshUrl: '/api/refresh' });
assert.equal(getAccessToken(), 'mytoken123', 'access token stored');
assert.deepEqual(getAuthHeader(), { Authorization: 'Bearer mytoken123' }, 'bearer header');

const tokenHeaders = getJsonAuthHeaders();
assert.equal(tokenHeaders['Content-Type'], 'application/json', 'Content-Type set');
assert.equal(tokenHeaders['X-Requested-With'], 'XMLHttpRequest', 'X-Requested-With set');
assert.equal(tokenHeaders['Authorization'], 'Bearer mytoken123', 'Authorization header present');
console.log('✓ token state');

// ── re-initialising clears the token ─────────────────────────────────────────

initAuth({ accessToken: null });
assert.equal(getAccessToken(), null, 'token cleared on re-init with null');
assert.deepEqual(getAuthHeader(), {}, 'empty auth header after clear');
console.log('✓ token reset');

// ── initAuth treats missing accessToken as null ───────────────────────────────

initAuth({ refreshUrl: '/api/refresh' });          // no accessToken key at all
assert.equal(getAccessToken(), null, 'undefined accessToken treated as null');
assert.deepEqual(getAuthHeader(), {}, 'no header for undefined accessToken');
console.log('✓ missing accessToken key');
