/**
 * Tests for assets/home/tabs/tasks/actions.js
 * Covers: isTaskActive, getTaskDownloadUrl, applyTaskRealtimeUpdate.
 * (loadTasks / cancelTask require network and are not tested here.)
 * Run: node assets/tests/tasks-actions.test.mjs
 */
import assert from 'node:assert/strict';
import { isTaskActive, createTasksTabActions } from '../home/tabs/tasks/actions.js';

// ── isTaskActive ──────────────────────────────────────────────────────────────

assert.equal(isTaskActive('PENDING'),    true,  'PENDING is active');
assert.equal(isTaskActive('PROCESSING'), true,  'PROCESSING is active');
assert.equal(isTaskActive('COMPLETED'),  false, 'COMPLETED is not active');
assert.equal(isTaskActive('CANCELLED'),  false, 'CANCELLED is not active');
assert.equal(isTaskActive('FAILED'),     false, 'FAILED is not active');
assert.equal(isTaskActive(''),           false, 'empty string is not active');
assert.equal(isTaskActive(null),         false, 'null is not active');
console.log('✓ isTaskActive');

// ── helpers ───────────────────────────────────────────────────────────────────

function makeState(tasks = []) {
    return {
        tasks:         { value: tasks },
        tasksMeta:     { value: { page: 1, limit: 10, total: 0, totalPages: 1 } },
        tasksLoading:  { value: false },
        tasksError:    { value: '' },
        tasksLoaded:   { value: false },
        taskActionKey: { value: '' },
    };
}

const config = {
    route: {
        task: {
            list:     '/api/tasks',
            cancel:   '/api/tasks/__TASK_ID__/cancel',
            download: '/api/tasks/__TASK_ID__/download',
        },
    },
};

// ── getTaskDownloadUrl ────────────────────────────────────────────────────────

{
    const state = makeState();
    const { getTaskDownloadUrl } = createTasksTabActions({ config, tasksState: state, pageLimit: 10 });

    assert.equal(getTaskDownloadUrl(42),      '/api/tasks/42/download',   'numeric id');
    assert.equal(getTaskDownloadUrl('99'),     '/api/tasks/99/download',   'string id');
    assert.equal(getTaskDownloadUrl('abc-id'), '/api/tasks/abc-id/download', 'string UUID');
    console.log('✓ getTaskDownloadUrl');
}

// ── applyTaskRealtimeUpdate: updates matching task ────────────────────────────

{
    const state = makeState([
        { id: '10', videoTitle: 'Video A', presetTitle: 'Preset 1', status: 'PENDING',    progress: 0  },
        { id: '20', videoTitle: 'Video B', presetTitle: 'Preset 2', status: 'PROCESSING', progress: 50 },
    ]);
    const { applyTaskRealtimeUpdate } = createTasksTabActions({ config, tasksState: state, pageLimit: 10 });

    applyTaskRealtimeUpdate({ taskId: '10', status: 'PROCESSING', progress: 25 });

    assert.equal(state.tasks.value[0].status,   'PROCESSING', 'status updated');
    assert.equal(state.tasks.value[0].progress, 25,           'progress updated');
    assert.equal(state.tasks.value[1].status,   'PROCESSING', 'other task unchanged (still PROCESSING)');
    assert.equal(state.tasks.value[1].progress, 50,           'other task progress unchanged');
    console.log('✓ applyTaskRealtimeUpdate: updates matching task');
}

// ── applyTaskRealtimeUpdate: ignores missing / empty taskId ──────────────────

{
    const state = makeState([
        { id: '10', videoTitle: 'V', presetTitle: 'P', status: 'PENDING', progress: 0 },
    ]);
    const { applyTaskRealtimeUpdate } = createTasksTabActions({ config, tasksState: state, pageLimit: 10 });

    applyTaskRealtimeUpdate({ taskId: '',    status: 'COMPLETED' });
    applyTaskRealtimeUpdate({ taskId: null,  status: 'COMPLETED' });
    applyTaskRealtimeUpdate({               status: 'COMPLETED' }); // no taskId key

    assert.equal(state.tasks.value[0].status, 'PENDING', 'task not touched without taskId');
    console.log('✓ applyTaskRealtimeUpdate: ignores empty/missing taskId');
}

// ── applyTaskRealtimeUpdate: downloadFilename assembled from titles ────────────

{
    const state = makeState([
        { id: '5', videoTitle: 'TestVid', presetTitle: 'HD', status: 'COMPLETED', progress: 100 },
    ]);
    const { applyTaskRealtimeUpdate } = createTasksTabActions({ config, tasksState: state, pageLimit: 10 });

    applyTaskRealtimeUpdate({ taskId: '5', videoTitle: 'NewVid', presetTitle: 'SD', status: 'COMPLETED' });

    assert.equal(state.tasks.value[0].downloadFilename, 'NewVid - SD', 'downloadFilename assembled');
    assert.equal(state.tasks.value[0].videoTitle,  'NewVid', 'videoTitle updated');
    assert.equal(state.tasks.value[0].presetTitle, 'SD',     'presetTitle updated');
    console.log('✓ applyTaskRealtimeUpdate: downloadFilename');
}

// ── applyTaskRealtimeUpdate: unknown taskId leaves list unchanged ─────────────

{
    const state = makeState([
        { id: '10', videoTitle: 'V', presetTitle: 'P', status: 'PENDING', progress: 0 },
    ]);
    const { applyTaskRealtimeUpdate } = createTasksTabActions({ config, tasksState: state, pageLimit: 10 });

    applyTaskRealtimeUpdate({ taskId: '999', status: 'COMPLETED' });

    assert.equal(state.tasks.value[0].status, 'PENDING', 'unknown taskId: no change');
    console.log('✓ applyTaskRealtimeUpdate: unknown taskId');
}
