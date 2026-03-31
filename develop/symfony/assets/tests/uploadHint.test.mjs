/**
 * Tests for assets/home/tabs/upload/uploadHint.js
 * Run: node assets/tests/uploadHint.test.mjs
 */
import assert from 'node:assert/strict';
import { formatBytes, buildUploadHint } from '../home/tabs/upload/uploadHint.js';

// ── formatBytes ──────────────────────────────────────────────────────────────

assert.equal(formatBytes(0),          '0 MB',    'zero bytes');
assert.equal(formatBytes(1048576),    '1 MB',    '1 MB');
assert.equal(formatBytes(11534336),   '11 MB',   '11 MB');
assert.equal(formatBytes(1073741824), '1 GB',    '1 GB (exact, no decimal)');
assert.equal(formatBytes(1610612736), '1.5 GB',  '1.5 GB');
assert.equal(formatBytes(2147483648), '2 GB',    '2 GB (no trailing .0)');
console.log('✓ formatBytes');

// ── buildUploadHint: null / missing tariff ────────────────────────────────────

assert.equal(buildUploadHint(null),      null, 'null tariff → null');
assert.equal(buildUploadHint(undefined), null, 'undefined tariff → null');
assert.equal(buildUploadHint({}),        null, 'empty object (no storage) → null');
console.log('✓ buildUploadHint: null / missing tariff');

// ── buildUploadHint: normal case (plenty of space) ───────────────────────────

{
    const tariff = {
        videoSize: 100,
        width: 1920,
        height: 1280,
        storage: { now: 11534336, max: 1073741824 }, // 11 MB of 1 GB used
    };
    const result = buildUploadHint(tariff);
    assert.ok(result !== null);
    assert.ok(result.includes('1%'),           `should show 1%: ${result}`);
    assert.ok(result.includes('11 MB of 1 GB'), `should show used/max: ${result}`);
    assert.ok(result.includes('1920\u00d71280'), `should show resolution: ${result}`);
    assert.ok(result.includes('100 MB'),        `should show videoSize: ${result}`);
    assert.ok(!result.includes('running low'),  `should NOT warn about space: ${result}`);
    console.log('✓ buildUploadHint: normal case');
}

// ── buildUploadHint: storage running low ─────────────────────────────────────

{
    // 984 MB used of 1 GB → only ~40 MB remaining, videoSize = 100
    const tariff = {
        videoSize: 100,
        width: 1920,
        height: 1280,
        storage: { now: 1031798784, max: 1073741824 },
    };
    const result = buildUploadHint(tariff);
    assert.ok(result !== null);
    assert.ok(result.includes('running low'),   `should warn about space: ${result}`);
    assert.ok(!result.includes('100 MB'),       `original videoSize should NOT appear: ${result}`);
    console.log('✓ buildUploadHint: storage running low');
}

// ── buildUploadHint: completely empty storage ─────────────────────────────────

{
    const tariff = {
        videoSize: 100,
        width: 1920,
        height: 1280,
        storage: { now: 0, max: 1073741824 },
    };
    const result = buildUploadHint(tariff);
    assert.ok(result !== null);
    assert.ok(result.includes('0%'),      `should show 0% when empty: ${result}`);
    assert.ok(result.includes('100 MB'),  `should show full videoSize when empty: ${result}`);
    assert.ok(!result.includes('running low'));
    console.log('✓ buildUploadHint: empty storage');
}

