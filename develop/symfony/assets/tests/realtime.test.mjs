/**
 * Tests for:
 *   assets/home/realtime/appTaskMessage.js
 *   assets/home/realtime/appVideoMessage.js
 * Run: node assets/tests/realtime.test.mjs
 */
import assert from 'node:assert/strict';
import { parseAppTaskMessage } from '../home/realtime/appTaskMessage.js';
import { parseAppVideoMessage } from '../home/realtime/appVideoMessage.js';

// ── parseAppTaskMessage ───────────────────────────────────────────────────────

assert.equal(parseAppTaskMessage(null),      null, 'null → null');
assert.equal(parseAppTaskMessage(undefined), null, 'undefined → null');
assert.equal(parseAppTaskMessage('string'),  null, 'string → null');
assert.equal(parseAppTaskMessage(42),        null, 'number → null');

assert.equal(parseAppTaskMessage({ entity: 'video', payload: {} }), null, 'wrong entity → null');
assert.equal(parseAppTaskMessage({ entity: 'task' }),                null, 'missing payload → null');
assert.equal(parseAppTaskMessage({ entity: 'task', payload: null }), null, 'null payload → null');
assert.equal(parseAppTaskMessage({ entity: 'task', payload: 'str' }), null, 'string payload → null');

const taskPayload = { taskId: '42', status: 'PROCESSING', progress: 50 };
assert.deepEqual(
    parseAppTaskMessage({ entity: 'task', payload: taskPayload }),
    taskPayload,
    'valid task message returns payload'
);
console.log('✓ parseAppTaskMessage');

// ── parseAppVideoMessage ──────────────────────────────────────────────────────

assert.equal(parseAppVideoMessage(null),      null, 'null → null');
assert.equal(parseAppVideoMessage(undefined), null, 'undefined → null');
assert.equal(parseAppVideoMessage('string'),  null, 'string → null');

assert.equal(parseAppVideoMessage({ entity: 'task', payload: {} }), null, 'wrong entity → null');
assert.equal(parseAppVideoMessage({ entity: 'video' }),               null, 'missing payload → null');
assert.equal(parseAppVideoMessage({ entity: 'video', payload: null }), null, 'null payload → null');
assert.equal(parseAppVideoMessage({ entity: 'video', payload: 'str' }), null, 'string payload → null');

const videoPayload = { videoId: 'uuid-123', title: 'My Video', poster: '/img.jpg' };
assert.deepEqual(
    parseAppVideoMessage({ entity: 'video', payload: videoPayload }),
    videoPayload,
    'valid video message returns payload'
);
console.log('✓ parseAppVideoMessage');
