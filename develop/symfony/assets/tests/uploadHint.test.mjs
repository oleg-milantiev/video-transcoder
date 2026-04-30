/**
 * Tests for assets/home/tabs/TariffHint.js and tabs/upload/render.js
 * Run: node assets/tests/uploadHint.test.mjs
 */
import assert from 'node:assert/strict';
import {
    formatBytes,
    computeStoragePercent,
    computeStorageFreeBytes,
    computeEffectiveVideoSize,
    isStorageLow,
    renderTariffHint,
} from '../home/tabs/TariffHint.js';
import { renderUploadPane } from '../home/tabs/upload/render.js';

// ── formatBytes ──────────────────────────────────────────────────────────────

assert.equal(formatBytes(0),          '0 MB',    'zero bytes');
assert.equal(formatBytes(1048576),    '1 MB',    '1 MB');
assert.equal(formatBytes(11534336),   '11 MB',   '11 MB');
assert.equal(formatBytes(1073741824), '1 GB',    '1 GB (exact, no decimal)');
assert.equal(formatBytes(1610612736), '1.5 GB',  '1.5 GB');
assert.equal(formatBytes(2147483648), '2 GB',    '2 GB (no trailing .0)');
assert.equal(formatBytes(3221225472), '3 GB',    '3 GB');
assert.equal(formatBytes(536870912),  '512 MB',  '512 MB');
console.log('✓ formatBytes');

// ── computeStoragePercent ────────────────────────────────────────────────────────

assert.equal(computeStoragePercent(null),      0, 'null tariff → 0%');
assert.equal(computeStoragePercent(undefined), 0, 'undefined tariff → 0%');
assert.equal(computeStoragePercent({}),        0, 'empty object (no storage) → 0%');
assert.equal(computeStoragePercent({ storage: null }),    0, 'null storage → 0%');
assert.equal(computeStoragePercent({ storage: { now: 0, max: 0 } }), 0, 'max=0 → 0%');
assert.equal(computeStoragePercent({ storage: { now: 0, max: 1073741824 } }), 0, '0 of 1GB → 0%');
assert.equal(computeStoragePercent({ storage: { now: 1073741824, max: 1073741824 } }), 100, '1GB of 1GB → 100%');
assert.equal(computeStoragePercent({ storage: { now: 536870912, max: 1073741824 } }), 50, '512MB of 1GB → 50%');
assert.equal(computeStoragePercent({ storage: { now: 268435456, max: 1073741824 } }), 25, '256MB of 1GB → 25%');
console.log('✓ computeStoragePercent');

// ── computeStorageFreeBytes ──────────────────────────────────────────────────────

assert.equal(computeStorageFreeBytes(null), 0, 'null tariff → 0 bytes');
assert.equal(computeStorageFreeBytes(undefined), 0, 'undefined tariff → 0 bytes');
assert.equal(computeStorageFreeBytes({}), 0, 'empty object → 0 bytes');
assert.equal(computeStorageFreeBytes({ storage: null }), 0, 'null storage → 0 bytes');
assert.equal(
    computeStorageFreeBytes({ storage: { now: 0, max: 1073741824 } }),
    1073741824,
    'empty storage (0/1GB) → 1GB free'
);
assert.equal(
    computeStorageFreeBytes({ storage: { now: 1073741824, max: 1073741824 } }),
    0,
    'full storage (1GB/1GB) → 0 free'
);
assert.equal(
    computeStorageFreeBytes({ storage: { now: 536870912, max: 1073741824 } }),
    536870912,
    '512MB of 1GB → 512MB free'
);
assert.equal(
    computeStorageFreeBytes({ storage: { now: 1610612736, max: 1073741824 } }),
    0,
    'overfull storage → clamped to 0'
);
console.log('✓ computeStorageFreeBytes');

// ── computeEffectiveVideoSize ────────────────────────────────────────────────────

assert.equal(computeEffectiveVideoSize(null), 0, 'null tariff → 0');
assert.equal(computeEffectiveVideoSize({}), 0, 'no storage → 0');
assert.equal(
    computeEffectiveVideoSize({ videoSize: 100, storage: { now: 0, max: 1073741824 } }),
    100,
    'plenty of space → full videoSize'
);
assert.equal(
    computeEffectiveVideoSize({ videoSize: 100, storage: { now: 1031798784, max: 1073741824 } }),
    40,
    '~40MB free, 100MB requested → capped at 40MB'
);
assert.equal(
    computeEffectiveVideoSize({ videoSize: 50, storage: { now: 1073741824, max: 1073741824 } }),
    0,
    'no space left → 0'
);
assert.equal(
    computeEffectiveVideoSize({ videoSize: 100, storage: { now: 536870912, max: 1073741824 } }),
    100,
    'exact 512MB free, 100MB requested → 100'
);
console.log('✓ computeEffectiveVideoSize');

// ── isStorageLow ─────────────────────────────────────────────────────────────────

assert.equal(isStorageLow(null), false, 'null tariff → not low');
assert.equal(isStorageLow(undefined), false, 'undefined tariff → not low');
assert.equal(isStorageLow({}), false, 'no storage → not low');
assert.equal(
    isStorageLow({ videoSize: 100, storage: { now: 0, max: 1073741824 } }),
    false,
    'plenty of space → not low'
);
assert.equal(
    isStorageLow({ videoSize: 100, storage: { now: 1031798784, max: 1073741824 } }),
    true,
    '~40MB free, 100MB requested → low'
);
assert.equal(
    isStorageLow({ videoSize: 100, storage: { now: 1010827776, max: 1073741824 } }),
    true,
    '~60MB free, 100MB requested → low'
);
assert.equal(
    isStorageLow({ videoSize: 100, storage: { now: 973078528, max: 1073741824 } }),
    true,
    '~97MB free, 100MB requested → low (< 100MB)'
);
assert.equal(
    isStorageLow({ videoSize: 100, storage: { now: 973078528 - 4194304, max: 1073741824 } }),
    false,
    '~101MB free, 100MB requested → NOT low (≥ 100MB)'
);
console.log('✓ isStorageLow');

// ── renderTariffHint: null / missing tariff ────────────────────────────────────

assert.equal(renderTariffHint(null),      null, 'null tariff → null');
assert.equal(renderTariffHint(undefined), null, 'undefined tariff → null');
assert.equal(renderTariffHint({}),        null, 'empty object (no storage) → null');
console.log('✓ renderTariffHint: null / missing tariff');

// ── renderTariffHint: normal case (plenty of space) ───────────────────────────────

{
    const tariff = {
        videoSize: 100,
        width: 1920,
        height: 1280,
        storage: { now: 11534336, max: 1073741824 }, // 11 MB of 1 GB used
    };
    const result = renderTariffHint(tariff);
    assert.ok(result !== null);
    const resultStr = JSON.stringify(result);
    assert.ok(resultStr.includes('1%'),           `should show 1%: ${resultStr}`);
    assert.ok(resultStr.includes('11 MB') && resultStr.includes('1 GB'), `should show used/max: ${resultStr}`);
    assert.ok(resultStr.includes('1920\u00d71280'), `should show resolution: ${resultStr}`);
    assert.ok(resultStr.includes('100 MB'),        `should show videoSize: ${resultStr}`);
    assert.ok(!resultStr.includes('running low'),  `should NOT warn about space: ${resultStr}`);
    console.log('✓ renderTariffHint: normal case');
}

// ── renderTariffHint: storage running low ─────────────────────────────────────

{
    // 984 MB used of 1 GB → only ~40 MB remaining, videoSize = 100
    const tariff = {
        videoSize: 100,
        width: 1920,
        height: 1280,
        storage: { now: 1031798784, max: 1073741824 },
    };
    const result = renderTariffHint(tariff);
    assert.ok(result !== null);
    const resultStr = JSON.stringify(result);
    assert.ok(resultStr.includes('running low'),   `should warn about space: ${resultStr}`);
    assert.ok(!resultStr.includes('100 MB'),       `original videoSize should NOT appear when full: ${resultStr}`);
    console.log('✓ renderTariffHint: storage running low');
}

// ── renderTariffHint: completely empty storage ─────────────────────────────────

{
    const tariff = {
        videoSize: 100,
        width: 1920,
        height: 1280,
        storage: { now: 0, max: 1073741824 },
    };
    const result = renderTariffHint(tariff);
    assert.ok(result !== null);
    const resultStr = JSON.stringify(result);
    assert.ok(resultStr.includes('0%'),      `should show 0% when empty: ${resultStr}`);
    assert.ok(resultStr.includes('100 MB'),  `should show full videoSize when empty: ${resultStr}`);
    assert.ok(!resultStr.includes('running low'));
    console.log('✓ renderTariffHint: empty storage');
}

// ── renderTariffHint: large storage (multi-GB) ──────────────────────────────────

{
    const tariff = {
        videoSize: 500,
        width: 3840,
        height: 2160,
        storage: { now: 1610612736, max: 10737418240 }, // 1.5 GB of 10 GB
    };
    const result = renderTariffHint(tariff);
    assert.ok(result !== null);
    const resultStr = JSON.stringify(result);
    assert.ok(resultStr.includes('15%'), `should show 15%: ${resultStr}`);
    assert.ok(resultStr.includes('1.5 GB'), `should show 1.5 GB used: ${resultStr}`);
    assert.ok(resultStr.includes('10 GB'), `should show 10 GB max: ${resultStr}`);
    assert.ok(resultStr.includes('500 MB'), `should show 500 MB videoSize: ${resultStr}`);
    console.log('✓ renderTariffHint: large storage');
}

// ── renderUploadPane: storage full → overlay ─────────────────────────────────

{
    const fullTariff = {
        videoSize: 100,
        width: 1920,
        height: 1080,
        storage: { now: 1073741824, max: 1073741824 }, // 1 GB / 1 GB (full)
    };
    const result = renderUploadPane('tab-pane', true, fullTariff);
    const resultStr = JSON.stringify(result);
    assert.ok(resultStr.includes('storage-full-overlay'), 'storage full overlay key must be present');
    assert.ok(resultStr.includes('Storage Full'), 'must show "Storage Full" heading');
    assert.ok(resultStr.includes('/tariffs'), 'must link to tariffs page');
    assert.ok(resultStr.includes('upgrade your plan'), 'must mention upgrading plan');
    console.log('✓ renderUploadPane: storage full shows overlay');
}

{
    const normalTariff = {
        videoSize: 100,
        width: 1920,
        height: 1080,
        storage: { now: 0, max: 1073741824 }, // empty storage
    };
    const result = renderUploadPane('tab-pane', true, normalTariff);
    const resultStr = JSON.stringify(result);
    assert.ok(!resultStr.includes('storage-full-overlay'), 'no overlay when storage has space');
    console.log('✓ renderUploadPane: normal storage → no overlay');
}

{
    const result = renderUploadPane('tab-pane', true, null);
    const resultStr = JSON.stringify(result);
    assert.ok(!resultStr.includes('storage-full-overlay'), 'no overlay when tariff is null');
    console.log('✓ renderUploadPane: null tariff → no overlay');
}

