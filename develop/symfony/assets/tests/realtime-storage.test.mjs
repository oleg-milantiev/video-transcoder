/**
 * Tests for assets/home/realtime/appStorageMessage.js
 * Run: node assets/tests/realtime-storage.test.mjs
 */
import assert from 'node:assert/strict';
import { parseAppStorageMessage } from '../home/realtime/appStorageMessage.js';

// ── parseAppStorageMessage: invalid inputs ────────────────────────────────────

assert.equal(parseAppStorageMessage(null),      null, 'null → null');
assert.equal(parseAppStorageMessage(undefined), null, 'undefined → null');
assert.equal(parseAppStorageMessage('string'),  null, 'string → null');
assert.equal(parseAppStorageMessage(42),        null, 'number → null');
console.log('✓ parseAppStorageMessage: invalid inputs');

// ── parseAppStorageMessage: wrong entity ──────────────────────────────────────

assert.equal(parseAppStorageMessage({ entity: 'video',   payload: {} }), null, 'wrong entity video → null');
assert.equal(parseAppStorageMessage({ entity: 'task',    payload: {} }), null, 'wrong entity task → null');
assert.equal(parseAppStorageMessage({ entity: 'storage' }),               null, 'missing payload → null');
assert.equal(parseAppStorageMessage({ entity: 'storage', payload: null }), null, 'null payload → null');
assert.equal(parseAppStorageMessage({ entity: 'storage', payload: 'str' }), null, 'string payload → null');
console.log('✓ parseAppStorageMessage: wrong entity / missing payload');

// ── parseAppStorageMessage: valid message ─────────────────────────────────────

const storagePayload = { storageNow: 1048576, storageMax: 10737418240 };
assert.deepEqual(
    parseAppStorageMessage({ entity: 'storage', payload: storagePayload }),
    storagePayload,
    'valid storage message returns payload'
);
console.log('✓ parseAppStorageMessage: valid message returns payload');
