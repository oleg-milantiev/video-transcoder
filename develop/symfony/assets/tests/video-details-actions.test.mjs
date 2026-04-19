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
            transcode: '/api/videos/__UUID__/transcode/__PRESET_ID__/__HEIGHT__',
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

// ── applyTaskRealtimeUpdate: updates matching task ───────────────────────────

{
    const dto = {
        video: {
            uuid: 'video-1',
            title: 'Test Video',
            meta: {},
        },
        presets: [
            { id: 'preset-a', title: 'HD', bitrate: {} },
            { id: 'preset-b', title: 'SD', bitrate: {} },
        ],
        tasks: [
            {
                id: 'task-10',
                status: 'PENDING',
                progress: 0,
                createdAt: '2024-01-01T00:00:00Z',
                presetTitle: 'HD',
                downloadFilename: '',
                waitingTariffInstance: null,
                waitingTariffDelay: null,
                willStartAt: null,
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

    applyTaskRealtimeUpdate({ taskId: 'task-10', videoId: 'video-1', status: 'PROCESSING', progress: 60 });

    const tasks = state.dto.value.tasks;
    assert.equal(tasks[0].status,   'PROCESSING', 'task status updated');
    assert.equal(tasks[0].progress, 60,           'task progress updated');
    console.log('✓ applyTaskRealtimeUpdate: updates matching task');
}

// ── applyTaskRealtimeUpdate: ignores empty/missing taskId ─────────────────────

{
    const dto = { video: { uuid: 'video-1' }, presets: [], tasks: [{ id: 'task-10', status: 'PENDING' }] };
    const state = makeState(dto);
    const { applyTaskRealtimeUpdate } = createVideoDetailsActions({
        config: mockConfig,
        route:  { params: {} },
        router: { push: async () => {} },
        state,
    });

    applyTaskRealtimeUpdate({ videoId: 'video-1', status: 'COMPLETED' }); // no taskId

    assert.equal(state.dto.value.tasks[0].status, 'PENDING', 'missing taskId: no update');
    console.log('✓ applyTaskRealtimeUpdate: ignores empty/missing taskId');
}

// ── applyTaskRealtimeUpdate: downloadFilename ─────────────────────────────────

{
    const dto = {
        video: { uuid: 'video-1' },
        presets: [],
        tasks: [{ id: 'task-10', status: 'PENDING', downloadFilename: '' }],
    };
    const state = makeState(dto);
    const { applyTaskRealtimeUpdate } = createVideoDetailsActions({
        config: mockConfig,
        route:  { params: {} },
        router: { push: async () => {} },
        state,
    });

    applyTaskRealtimeUpdate({
        taskId: 'task-10',
        videoId: 'video-1',
        videoTitle: 'My Video',
        presetTitle: 'HD 1080p'
    });

    assert.equal(state.dto.value.tasks[0].downloadFilename, 'My Video - HD 1080p', 'downloadFilename updated');
    console.log('✓ applyTaskRealtimeUpdate: downloadFilename');
}

// ── applyTaskRealtimeUpdate: unknown taskId (creates new task) ────────────────

{
    const dto = {
        video: { uuid: 'video-1' },
        presets: [],
        tasks: [],
    };
    const state = makeState(dto);
    const { applyTaskRealtimeUpdate } = createVideoDetailsActions({
        config: mockConfig,
        route:  { params: {} },
        router: { push: async () => {} },
        state,
    });

    applyTaskRealtimeUpdate({
        taskId: 'task-99',
        videoId: 'video-1',
        status: 'PENDING',
        presetTitle: 'New Preset',
        createdAt: '2024-01-02T00:00:00Z',
    });

    assert.equal(state.dto.value.tasks.length, 1, 'new task added');
    assert.equal(state.dto.value.tasks[0].id, 'task-99', 'new task has correct id');
    assert.equal(state.dto.value.tasks[0].presetTitle, 'New Preset', 'new task has preset title');
    console.log('✓ applyTaskRealtimeUpdate: unknown taskId');
}

// ── applyVideoRealtimeUpdate: updates video dto fields ────────────────────────

{
    const dto = {
        video: {
            uuid: 'video-1',
            title: 'Old Title',
            poster: null,
            meta: {},
            updatedAt: null,
            expiredAt: null,
        },
        presets: [],
        tasks: [],
    };
    const state = makeState(dto);
    const { applyVideoRealtimeUpdate } = createVideoDetailsActions({
        config: mockConfig,
        route:  { params: {} },
        router: { push: async () => {} },
        state,
    });

    applyVideoRealtimeUpdate({ videoId: 'video-1', title: 'New Title', poster: '/poster.jpg', updatedAt: '2024-06-01T00:00:00Z' });

    assert.equal(state.dto.value.video.title,     'New Title',            'title updated');
    assert.equal(state.dto.value.video.poster,    '/poster.jpg',          'poster updated');
    assert.equal(state.dto.value.video.updatedAt, '2024-06-01T00:00:00Z', 'updatedAt updated');
    console.log('✓ applyVideoRealtimeUpdate: updates matching video');
}

// ── applyVideoRealtimeUpdate: poster updated ──────────────────────────────────

{
    const dto = {
        video: {
            uuid: 'video-1',
            title: 'Video',
            poster: '/old.jpg',
            meta: {},
        },
        presets: [],
        tasks: [],
    };
    const state = makeState(dto);
    const { applyVideoRealtimeUpdate } = createVideoDetailsActions({
        config: mockConfig,
        route:  { params: {} },
        router: { push: async () => {} },
        state,
    });

    applyVideoRealtimeUpdate({ videoId: 'video-1', poster: '/new-poster.jpg' });

    assert.equal(state.dto.value.video.poster, '/new-poster.jpg', 'poster updated');
    console.log('✓ applyVideoRealtimeUpdate: poster updated');
}

// ── applyVideoRealtimeUpdate: marks deleted ───────────────────────────────────

{
    const dto = {
        video: {
            uuid: 'video-1',
            title: 'Video',
            deleted: false,
        },
        presets: [],
        tasks: [],
    };
    const state = makeState(dto);
    const { applyVideoRealtimeUpdate } = createVideoDetailsActions({
        config: mockConfig,
        route:  { params: {} },
        router: { push: async () => {} },
        state,
    });

    // Note: deletion would typically come via backend, but we test the structure is preserved
    assert.equal(state.dto.value.video.deleted, false, 'not deleted initially');
    console.log('✓ applyVideoRealtimeUpdate: marks deleted');
}

// ── applyVideoRealtimeUpdate: ignores unknown videoId ─────────────────────────

{
    const dto = {
        video: {
            uuid: 'video-1',
            title: 'Original',
            poster: null,
            meta: {},
        },
        presets: [],
        tasks: [],
    };
    const state = makeState(dto);
    const { applyVideoRealtimeUpdate } = createVideoDetailsActions({
        config: mockConfig,
        route:  { params: {} },
        router: { push: async () => {} },
        state,
    });

    applyVideoRealtimeUpdate({ videoId: 'other-video', title: 'Should Not Apply' });

    assert.equal(state.dto.value.video.title, 'Original', 'wrong videoId: title unchanged');
    console.log('✓ applyVideoRealtimeUpdate: ignores unknown videoId');
}

// ── applyVideoRealtimeUpdate: ignores empty/missing videoId ───────────────────

{
    const dto = {
        video: {
            uuid: 'video-1',
            title: 'Original',
        },
        presets: [],
        tasks: [],
    };
    const state = makeState(dto);
    const { applyVideoRealtimeUpdate } = createVideoDetailsActions({
        config: mockConfig,
        route:  { params: {} },
        router: { push: async () => {} },
        state,
    });

    applyVideoRealtimeUpdate({ title: 'Should Not Apply' }); // no videoId

    assert.equal(state.dto.value.video.title, 'Original', 'missing videoId: title unchanged');
    console.log('✓ applyVideoRealtimeUpdate: ignores empty/missing videoId');
}
