/**
 * Tests for assets/home/video-details/actions.js
 * Covers: formatMetaValue, applyTaskRealtimeUpdate, applyVideoRealtimeUpdate,
 *         taskDownloadUrl.
 *
 * Requires the ESM loader (tests/loader.mjs) to resolve the `vue` specifier.
 * Run: node --experimental-loader assets/tests/loader.mjs assets/tests/video-details-actions.test.mjs
 */
import assert from 'node:assert/strict';
import { createVideoDetailsActions } from '../home/video-details/actions.js';

// ── helpers ───────────────────────────────────────────────────────────────────

const mockConfig = {
    route: {
        video: {
            details:   '/api/videos/__UUID__',
            patch:     '/api/videos/__UUID__',
            transcode: '/api/videos/__UUID__/transcode/__PRESET_ID__',
        },
        task: {
            cancel:   '/api/tasks/__TASK_ID__/cancel',
            download: '/api/tasks/__TASK_ID__/download',
        },
        home: '/',
    },
    videoUuid: 'test-uuid',
};

function makeState(dto = null) {
    return {
        dto:             { value: dto },
        loading:         { value: false },
        error:           { value: '' },
        actionError:     { value: '' },
        activeActionKey: { value: '' },
    };
}

// ── formatMetaValue ───────────────────────────────────────────────────────────

{
    const state = makeState();
    const { formatMetaValue } = createVideoDetailsActions({
        config: mockConfig,
        route:  { params: {} },
        router: { push: async () => {} },
        state,
    });

    assert.equal(formatMetaValue(null),      '-',        'null → -');
    assert.equal(formatMetaValue(undefined), '-',        'undefined → -');
    assert.equal(formatMetaValue('hello'),   'hello',    'string → string');
    assert.equal(formatMetaValue(42),        '42',       'number → string');
    assert.equal(formatMetaValue(0),         '0',        'zero → "0"');
    assert.equal(formatMetaValue({ a: 1 }), '{"a":1}',  'object → JSON');
    assert.equal(formatMetaValue([1, 2]),   '[1,2]',    'array → JSON');
    console.log('✓ formatMetaValue');
}

// ── taskDownloadUrl ───────────────────────────────────────────────────────────

{
    const state = makeState();
    const { taskDownloadUrl } = createVideoDetailsActions({
        config: mockConfig,
        route:  { params: {} },
        router: { push: async () => {} },
        state,
    });

    assert.equal(taskDownloadUrl('task-42'), '/api/tasks/task-42/download', 'task download URL');
    assert.equal(taskDownloadUrl('99'),      '/api/tasks/99/download',      'numeric string id');
    console.log('✓ taskDownloadUrl');
}

// ── applyTaskRealtimeUpdate: updates matching preset's task ───────────────────

{
    const dto = {
        id: 'video-1',
        presetsWithTasks: [
            {
                id: 'preset-a',
                title: 'HD',
                task: {
                    id: 'task-10',
                    status: 'PENDING',
                    progress: 0,
                    createdAt: '2024-01-01T00:00:00Z',
                    downloadFilename: '',
                    waitingTariffInstance: null,
                    waitingTariffDelay: null,
                    willStartAt: null,
                },
            },
            {
                id: 'preset-b',
                title: 'SD',
                task: null,
            },
        ],
    };
    const state = makeState(dto);
    const { applyTaskRealtimeUpdate } = createVideoDetailsActions({
        config: mockConfig,
        route:  { params: {} },
        router: { push: async () => {} },
        state,
    });

    applyTaskRealtimeUpdate({ taskId: 'task-10', presetId: 'preset-a', status: 'PROCESSING', progress: 60 });

    const presets = state.dto.value.presetsWithTasks;
    assert.equal(presets[0].task.status,   'PROCESSING', 'task status updated');
    assert.equal(presets[0].task.progress, 60,           'task progress updated');
    assert.equal(presets[1].task,          null,         'other preset task unchanged');
    console.log('✓ applyTaskRealtimeUpdate: updates matching preset');
}

// ── applyTaskRealtimeUpdate: ignores wrong videoId ────────────────────────────

{
    const dto = { id: 'video-1', presetsWithTasks: [{ id: 'preset-a', title: 'HD', task: { id: 'task-10', status: 'PENDING', progress: 0 } }] };
    const state = makeState(dto);
    const { applyTaskRealtimeUpdate } = createVideoDetailsActions({
        config: mockConfig,
        route:  { params: {} },
        router: { push: async () => {} },
        state,
    });

    applyTaskRealtimeUpdate({ taskId: 'task-10', videoId: 'wrong-video', status: 'COMPLETED' });

    assert.equal(state.dto.value.presetsWithTasks[0].task.status, 'PENDING', 'wrong videoId: no update');
    console.log('✓ applyTaskRealtimeUpdate: ignores wrong videoId');
}

// ── applyVideoRealtimeUpdate: updates video dto fields ────────────────────────

{
    const dto = { id: 'video-1', title: 'Old Title', poster: null, meta: {}, updatedAt: null, expiredAt: null };
    const state = makeState(dto);
    const { applyVideoRealtimeUpdate } = createVideoDetailsActions({
        config: mockConfig,
        route:  { params: {} },
        router: { push: async () => {} },
        state,
    });

    applyVideoRealtimeUpdate({ videoId: 'video-1', title: 'New Title', poster: '/poster.jpg', updatedAt: '2024-06-01T00:00:00Z' });

    assert.equal(state.dto.value.title,     'New Title',            'title updated');
    assert.equal(state.dto.value.poster,    '/poster.jpg',          'poster updated');
    assert.equal(state.dto.value.updatedAt, '2024-06-01T00:00:00Z', 'updatedAt updated');
    console.log('✓ applyVideoRealtimeUpdate: updates video dto');
}

// ── applyVideoRealtimeUpdate: ignores wrong videoId ───────────────────────────

{
    const dto = { id: 'video-1', title: 'Original', poster: null, meta: {}, updatedAt: null, expiredAt: null };
    const state = makeState(dto);
    const { applyVideoRealtimeUpdate } = createVideoDetailsActions({
        config: mockConfig,
        route:  { params: {} },
        router: { push: async () => {} },
        state,
    });

    applyVideoRealtimeUpdate({ videoId: 'other-video', title: 'Should Not Apply' });

    assert.equal(state.dto.value.title, 'Original', 'wrong videoId: title unchanged');
    console.log('✓ applyVideoRealtimeUpdate: ignores wrong videoId');
}

// ── applyVideoRealtimeUpdate: skips when dto is null ─────────────────────────

{
    const state = makeState(null);
    const { applyVideoRealtimeUpdate } = createVideoDetailsActions({
        config: mockConfig,
        route:  { params: {} },
        router: { push: async () => {} },
        state,
    });

    // Should not throw
    applyVideoRealtimeUpdate({ videoId: 'video-1', title: 'Title' });
    assert.equal(state.dto.value, null, 'dto stays null when no dto loaded');
    console.log('✓ applyVideoRealtimeUpdate: no-op when dto is null');
}
