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

// ── applyVideoUploaded: ignores when not yet loaded ───────────────────────────

{
    const state = makeState([]);
    state.videosLoaded.value = false;
    const { applyVideoUploaded } = createVideosTabActions({ config, router, videosState: state, pageLimit: 10 });

    await applyVideoUploaded({ uuid: 'new-1', title: 'New' });

    assert.equal(state.videos.value.length, 0, 'not loaded: list unchanged');
    console.log('✓ applyVideoUploaded: ignores when not yet loaded');
}

// ── applyVideoUploaded: prepends on page 1 ────────────────────────────────────

{
    const state = makeState([
        { uuid: 'old-1', title: 'Old Video' },
    ]);
    state.videosLoaded.value = true;
    state.videosMeta.value = { page: 1, limit: 10, total: 1, totalPages: 1 };
    const { applyVideoUploaded } = createVideosTabActions({ config, router, videosState: state, pageLimit: 10 });

    await applyVideoUploaded({ uuid: 'new-1', title: 'Uploaded Video' });

    assert.equal(state.videos.value.length, 2,              'list grows by one');
    assert.equal(state.videos.value[0].uuid,  'new-1',      'new video is first');
    assert.equal(state.videos.value[1].uuid,  'old-1',      'old video is second');
    assert.equal(state.videosMeta.value.total, 2,           'total incremented');
    console.log('✓ applyVideoUploaded: prepends on page 1');
}

// ── applyVideoUploaded: does not prepend on non-first page ───────────────────

{
    const state = makeState([
        { uuid: 'old-1', title: 'Old Video' },
    ]);
    state.videosLoaded.value = true;
    state.videosMeta.value = { page: 3, limit: 10, total: 25, totalPages: 3 };
    const { applyVideoUploaded } = createVideosTabActions({ config, router, videosState: state, pageLimit: 10 });

    // On page 3:  should NOT prepend — triggers a re-fetch instead (may fail in test env, that's fine)
    await applyVideoUploaded({ uuid: 'new-1', title: 'Uploaded Video' }).catch(() => {});

    const firstUuid = state.videos.value.length > 0 ? state.videos.value[0].uuid : null;
    assert.notEqual(firstUuid, 'new-1', 'not page 1: new video not prepended');
    console.log('✓ applyVideoUploaded: does not prepend on non-first page');
}

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

// ── applyVideoRealtimeUpdate: updates via uuid (Mercure DTO payload) ──────────

{
    const state = makeState([
        { id: 'uuid-1', uuid: 'uuid-1', title: 'Old', poster: null, deleted: false, updatedAt: null, canBeDeleted: false },
        { id: 'uuid-2', uuid: 'uuid-2', title: 'Other', poster: null, deleted: false, updatedAt: null, canBeDeleted: false },
    ]);
    const { applyVideoRealtimeUpdate } = createVideosTabActions({ config, router, videosState: state, pageLimit: 10 });

    // Mercure meta/preview events use { uuid, poster, ... } — not videoId
    applyVideoRealtimeUpdate({ uuid: 'uuid-1', poster: '/preview.jpg', title: 'Old', updatedAt: '2024-01-01T00:00:00Z' });

    assert.equal(state.videos.value[0].poster, '/preview.jpg', 'poster updated via uuid');
    assert.equal(state.videos.value[1].poster, null,           'other video unchanged');
    console.log('✓ applyVideoRealtimeUpdate: updates via uuid (Mercure DTO payload)');
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
    applyVideoRealtimeUpdate({ uuid: '',     title: 'Nope' });
    applyVideoRealtimeUpdate({ uuid: null,   title: 'Nope' });

    assert.equal(state.videos.value[0].title, 'V1', 'empty/missing videoId: no change');
    console.log('✓ applyVideoRealtimeUpdate: ignores empty/missing videoId');
}
