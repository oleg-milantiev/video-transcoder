/**
 * Tests for assets/home/tabs/videos/actions.js
 * Covers: applyVideoRealtimeUpdate.
 * (loadVideos / deleteVideo require network/window and are not tested here.)
 * Run: node assets/tests/videos-actions.test.mjs
 */
import assert from 'node:assert/strict';
import { createVideosTabActions } from '../home/tabs/videos/actions.js';

// ── helpers ───────────────────────────────────────────────────────────────────

function makeState(videos = []) {
    return {
        videos:             { value: videos },
        videosMeta:         { value: { page: 1, limit: 10, total: 0, totalPages: 1 } },
        videosLoading:      { value: false },
        videosError:        { value: '' },
        videosLoaded:       { value: false },
        videoDeletePending: { value: {} },
    };
}

const config = {
    route: {
        video: {
            list:    '/api/videos',
            delete:  '/api/videos/__UUID__',
            details: '/api/videos/__UUID__',
        },
        videoDetails: '/video/__UUID__',
    },
};

const router = { push: async () => {} };

// ── applyVideoRealtimeUpdate: updates matching video ──────────────────────────

{
    const state = makeState([
        { id: 'uuid-1', uuid: 'uuid-1', title: 'Old Title', poster: null,     deleted: false, updatedAt: null, canBeDeleted: false },
        { id: 'uuid-2', uuid: 'uuid-2', title: 'Another',   poster: '/a.jpg', deleted: false, updatedAt: null, canBeDeleted: false },
    ]);
    const { applyVideoRealtimeUpdate } = createVideosTabActions({ config, router, videosState: state, pageLimit: 10 });

    applyVideoRealtimeUpdate({ videoId: 'uuid-1', title: 'New Title', updatedAt: '2024-06-01T00:00:00Z' });

    assert.equal(state.videos.value[0].title,     'New Title',          'title updated');
    assert.equal(state.videos.value[0].updatedAt, '2024-06-01T00:00:00Z', 'updatedAt updated');
    assert.equal(state.videos.value[1].title,     'Another',            'other video unchanged');
    console.log('✓ applyVideoRealtimeUpdate: updates matching video');
}

// ── applyVideoRealtimeUpdate: poster updated ──────────────────────────────────

{
    const state = makeState([
        { id: 'uuid-1', uuid: 'uuid-1', title: 'Video', poster: null, deleted: false, updatedAt: null, canBeDeleted: false },
    ]);
    const { applyVideoRealtimeUpdate } = createVideosTabActions({ config, router, videosState: state, pageLimit: 10 });

    applyVideoRealtimeUpdate({ videoId: 'uuid-1', poster: '/new-poster.jpg' });

    assert.equal(state.videos.value[0].poster, '/new-poster.jpg', 'poster updated');
    console.log('✓ applyVideoRealtimeUpdate: poster updated');
}

// ── applyVideoRealtimeUpdate: marks deleted ───────────────────────────────────

{
    const state = makeState([
        { id: 'uuid-1', uuid: 'uuid-1', title: 'V1', poster: null, deleted: false, updatedAt: null, canBeDeleted: true },
        { id: 'uuid-2', uuid: 'uuid-2', title: 'V2', poster: null, deleted: false, updatedAt: null, canBeDeleted: true },
    ]);
    const { applyVideoRealtimeUpdate } = createVideosTabActions({ config, router, videosState: state, pageLimit: 10 });

    applyVideoRealtimeUpdate({ videoId: 'uuid-1', deleted: true });

    assert.equal(state.videos.value[0].deleted, true,  'video 1 marked deleted');
    assert.equal(state.videos.value[1].deleted, false, 'video 2 not affected');
    console.log('✓ applyVideoRealtimeUpdate: marks deleted');
}

// ── applyVideoRealtimeUpdate: ignores unknown videoId ─────────────────────────

{
    const state = makeState([
        { id: 'uuid-1', uuid: 'uuid-1', title: 'V1', poster: null, deleted: false, updatedAt: null, canBeDeleted: false },
    ]);
    const { applyVideoRealtimeUpdate } = createVideosTabActions({ config, router, videosState: state, pageLimit: 10 });

    applyVideoRealtimeUpdate({ videoId: 'no-such-id', title: 'Ghost' });

    assert.equal(state.videos.value[0].title, 'V1', 'unknown videoId: no change');
    console.log('✓ applyVideoRealtimeUpdate: ignores unknown videoId');
}

// ── applyVideoRealtimeUpdate: ignores empty / missing videoId ─────────────────

{
    const state = makeState([
        { id: 'uuid-1', uuid: 'uuid-1', title: 'V1', poster: null, deleted: false, updatedAt: null, canBeDeleted: false },
    ]);
    const { applyVideoRealtimeUpdate } = createVideosTabActions({ config, router, videosState: state, pageLimit: 10 });

    applyVideoRealtimeUpdate({ videoId: '',   title: 'Nope' });
    applyVideoRealtimeUpdate({ videoId: null, title: 'Nope' });
    applyVideoRealtimeUpdate({               title: 'Nope' }); // no key

    assert.equal(state.videos.value[0].title, 'V1', 'empty/missing videoId: no change');
    console.log('✓ applyVideoRealtimeUpdate: ignores empty/missing videoId');
}
