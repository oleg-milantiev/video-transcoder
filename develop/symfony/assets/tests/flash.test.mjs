/**
 * Tests for assets/flash/bindFlashNotifications.js
 * Covers: normalizeNotification, toSwalOptions.
 * (bindFlashNotifications itself binds to window and is not tested here.)
 *
 * Requires the ESM loader (tests/loader.mjs) to resolve `sweetalert2`.
 * Run: node --experimental-loader assets/tests/loader.mjs assets/tests/flash.test.mjs
 */
import assert from 'node:assert/strict';
import { normalizeNotification, toSwalOptions } from '../flash/bindFlashNotifications.js';

// ── normalizeNotification ─────────────────────────────────────────────────────

assert.equal(normalizeNotification(null),      null, 'null → null');
assert.equal(normalizeNotification(undefined), null, 'undefined → null');
assert.equal(normalizeNotification(42),        null, 'number → null');

// String shorthand
{
    const result = normalizeNotification('plain text');
    assert.deepEqual(result, { html: 'plain text' }, 'string → { html }');
}
console.log('✓ normalizeNotification: null / string / primitives');

// Full object
{
    const result = normalizeNotification({
        title: 'Done',
        html: '<b>OK</b>',
        level: 'success',
        timer: 3000,
        imageUrl: '/img.jpg',
        imageAlt: 'img',
        position: 'top-start',
    });
    assert.equal(result.title,    'Done');
    assert.equal(result.html,     '<b>OK</b>');
    assert.equal(result.level,    'success');
    assert.equal(result.timer,    3000);
    assert.equal(result.imageUrl, '/img.jpg');
    assert.equal(result.imageAlt, 'img');
    assert.equal(result.position, 'top-start');
    console.log('✓ normalizeNotification: full object');
}

// Fallbacks
{
    const result = normalizeNotification({});
    assert.equal(result.title,    '');
    assert.equal(result.html,     '');
    assert.equal(result.level,    'info',    'default level');
    assert.equal(result.timer,    5000,      'default timer');
    assert.equal(result.imageUrl, '');
    assert.equal(result.position, 'top-end', 'default position');
    console.log('✓ normalizeNotification: defaults');
}

// Alternative field aliases
{
    const result = normalizeNotification({ message: 'hi', type: 'warning', image: '/x.png' });
    assert.equal(result.html,     'hi',      'message alias for html');
    assert.equal(result.level,    'warning', 'type alias for level');
    assert.equal(result.imageUrl, '/x.png',  'image alias for imageUrl');
    console.log('✓ normalizeNotification: field aliases');
}

// Non-finite timer falls back to 5000
{
    const result = normalizeNotification({ timer: 'not-a-number' });
    assert.equal(result.timer, 5000, 'non-finite timer → default 5000');
    console.log('✓ normalizeNotification: invalid timer fallback');
}

// ── toSwalOptions ─────────────────────────────────────────────────────────────

{
    const notification = normalizeNotification({ title: 'Alert', html: '<p>Hi</p>', level: 'error', timer: 4000, position: 'top-end' });
    const opts = toSwalOptions(notification);

    assert.equal(opts.toast,           true,      'toast mode');
    assert.equal(opts.icon,            'error',   'error level → error icon');
    assert.equal(opts.title,           'Alert');
    assert.equal(opts.html,            '<p>Hi</p>');
    assert.equal(opts.timer,           4000);
    assert.equal(opts.position,        'top-end');
    assert.equal(opts.timerProgressBar, true,     'timer progress bar on');
    assert.equal(opts.showConfirmButton, false,   'no confirm button');
    console.log('✓ toSwalOptions: basic');
}

// icon mapping
{
    const levelToIcon = { success: 'success', info: 'info', warning: 'warning', error: 'error', danger: 'error' };
    for (const [level, expectedIcon] of Object.entries(levelToIcon)) {
        const n = normalizeNotification({ level });
        const opts = toSwalOptions(n);
        assert.equal(opts.icon, expectedIcon, `level "${level}" → icon "${expectedIcon}"`);
    }
    console.log('✓ toSwalOptions: level → icon mapping');
}

// unknown level falls back to 'info'
{
    const n = normalizeNotification({ level: 'unknown-level' });
    const opts = toSwalOptions(n);
    assert.equal(opts.icon, 'info', 'unknown level → info icon');
    console.log('✓ toSwalOptions: unknown level defaults to info');
}

// imageUrl only included when present
{
    const withImage = toSwalOptions(normalizeNotification({ imageUrl: '/img.jpg', imageAlt: 'alt text' }));
    assert.equal(withImage.imageUrl, '/img.jpg', 'imageUrl passed to options');
    assert.equal(withImage.imageAlt, 'alt text', 'imageAlt passed to options');

    const withoutImage = toSwalOptions(normalizeNotification({}));
    assert.ok(!('imageUrl' in withoutImage), 'no imageUrl when not provided');
    console.log('✓ toSwalOptions: imageUrl optional');
}
