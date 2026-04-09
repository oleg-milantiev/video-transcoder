/**
 * Tests for assets/home/shared.js
 * Run: node assets/tests/shared.test.mjs
 */
import assert from 'node:assert/strict';
import {
    replaceTemplateValue,
    normalizeErrorMessage,
    extractApiErrorMessage,
    secondsToHuman,
    bytesToHuman,
    megabytesToHuman,
    humanReadableDateTime,
} from '../home/shared.js';

// ── replaceTemplateValue ──────────────────────────────────────────────────────

assert.equal(replaceTemplateValue('/tasks/__ID__/cancel', '__ID__', '42'), '/tasks/42/cancel');
assert.equal(replaceTemplateValue('hello __X__', '__X__', 'world'), 'hello world');
assert.equal(replaceTemplateValue('no match', '__X__', 'value'), 'no match');
assert.equal(replaceTemplateValue('__A__', '__A__', 0), '0', 'numeric value is stringified');
console.log('✓ replaceTemplateValue');

// ── normalizeErrorMessage ─────────────────────────────────────────────────────

assert.equal(normalizeErrorMessage(new Error('oops'), 'fallback'), 'oops');
assert.equal(normalizeErrorMessage(new Error(''), 'fallback'), 'fallback', 'empty message uses fallback');
assert.equal(normalizeErrorMessage('plain string', 'fallback'), 'fallback', 'non-Error uses fallback');
assert.equal(normalizeErrorMessage(null, 'fallback'), 'fallback', 'null uses fallback');
assert.equal(normalizeErrorMessage(undefined, 'fallback'), 'fallback', 'undefined uses fallback');
console.log('✓ normalizeErrorMessage');

// ── extractApiErrorMessage ────────────────────────────────────────────────────

assert.equal(extractApiErrorMessage({ error: { message: 'Not found' } }, 'fb'), 'Not found');
assert.equal(extractApiErrorMessage({ error: { message: 123 } }, 'fb'), 'fb', 'non-string message uses fallback');
assert.equal(extractApiErrorMessage({ error: 'simple error' }, 'fb'), 'simple error', 'string error');
assert.equal(extractApiErrorMessage({}, 'fb'), 'fb', 'no error field');
assert.equal(extractApiErrorMessage(null, 'fb'), 'fb', 'null payload');
assert.equal(extractApiErrorMessage(undefined, 'fb'), 'fb', 'undefined payload');
console.log('✓ extractApiErrorMessage');

// ── secondsToHuman ────────────────────────────────────────────────────────────

assert.equal(secondsToHuman('not a number'), '-', 'non-number → -');
assert.equal(secondsToHuman(NaN), '-', 'NaN → -');
assert.equal(secondsToHuman(Infinity), '-', 'Infinity → -');
assert.equal(secondsToHuman(0), '0 s', '0 seconds');
assert.equal(secondsToHuman(59), '59 s', '59 seconds');
assert.equal(secondsToHuman(60), '1 m', '60 seconds = 1 minute');
assert.equal(secondsToHuman(3599), '59 m', '3599 seconds = 59 minutes');
assert.equal(secondsToHuman(3600), '1 h', '3600 seconds = 1 hour');
assert.equal(secondsToHuman(86399), '23 h', '86399 seconds = 23 hours');
assert.equal(secondsToHuman(86400), '1 d', '86400 seconds = 1 day');
assert.equal(secondsToHuman(172800), '2 d', '172800 seconds = 2 days');
console.log('✓ secondsToHuman');

// ── bytesToHuman ──────────────────────────────────────────────────────────────

assert.equal(bytesToHuman('x'), '-', 'non-number → -');
assert.equal(bytesToHuman(NaN), '-', 'NaN → -');
assert.equal(bytesToHuman(0), '0 B', '0 bytes');
assert.equal(bytesToHuman(1023), '1023 B', 'just under 1 KB');
assert.equal(bytesToHuman(1024), '1 KB', '1 KB');
assert.equal(bytesToHuman(1024 * 1024), '1 MB', '1 MB');
assert.equal(bytesToHuman(1024 * 1024 * 1024), '1 GB', '1 GB');
assert.equal(bytesToHuman(1024 * 1024 * 1024 * 1024), '1 TB', '1 TB');
console.log('✓ bytesToHuman');

// ── megabytesToHuman ──────────────────────────────────────────────────────────

assert.equal(megabytesToHuman('x'), '-', 'non-number → -');
assert.equal(megabytesToHuman(NaN), '-', 'NaN → -');
assert.equal(megabytesToHuman(0), '0 MB', '0 MB');
assert.equal(megabytesToHuman(100), '100 MB', '100 MB stays as MB');
assert.equal(megabytesToHuman(1023), '1023 MB', '1023 MB stays as MB');
assert.equal(megabytesToHuman(1024), '1 GB', '1024 MB = 1 GB');
assert.equal(megabytesToHuman(1536), '1.5 GB', '1536 MB = 1.5 GB');
assert.equal(megabytesToHuman(2048), '2 GB', '2048 MB = 2 GB');
console.log('✓ megabytesToHuman');

// ── humanReadableDateTime ─────────────────────────────────────────────────────

assert.equal(humanReadableDateTime(''), '-', 'empty string → fallback');
assert.equal(humanReadableDateTime(null), '-', 'null → fallback');
assert.equal(humanReadableDateTime(undefined), '-', 'undefined → fallback');
assert.equal(humanReadableDateTime('not-a-date'), '-', 'invalid date string → fallback');
assert.equal(humanReadableDateTime('', 'N/A'), 'N/A', 'custom fallback');

// Valid ISO date – check structural properties, not exact locale-formatted string
const dt = humanReadableDateTime('2024-06-15T10:30:00Z');
assert.notEqual(dt, '-', 'valid date produces a result');
assert.ok(dt.includes('2024'), `year present: ${dt}`);
assert.ok(dt.includes('Jun'), `month present: ${dt}`);
assert.ok(dt.includes('15'), `day present: ${dt}`);
assert.ok(/\d{2}:\d{2}/.test(dt), `time part HH:MM present: ${dt}`);
console.log('✓ humanReadableDateTime');
