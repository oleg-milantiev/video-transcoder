import { h } from 'vue';
import { humanReadableDateTime } from '../../shared.js';

function renderPoster(video) {
    if (video.poster) {
        const isDeleted = video.deleted === true;
        const style =
            'width:120px;max-width:100%;height:auto;object-fit:cover;border-radius:6px;' +
            (isDeleted ? 'filter:saturate(0);opacity:0.5;' : '');

        return h('img', {
            src: video.poster,
            alt: 'poster',
            style,
        });
    }

    return h(
        'div',
        {
            style: 'width:120px;height:68px;background:#eee;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:12px;border-radius:6px;',
        },
        'No poster'
    );
}

function renderDeleteButton(vm, video) {
    if (video.deleted || !video.canBeDeleted) {
        return;
    }

    return h(
        'button',
        {
            type: 'button',
            class: 'btn btn-sm btn-outline-danger',
            onClick: (event) => {
                event.stopPropagation();
                vm.deleteVideo(video);
            },
        },
        'Delete'
    );
}

export function renderVideosPane(vm, paneClass) {
    return h('div', { class: paneClass }, [
        vm.videosError ? h('div', { class: 'alert alert-danger' }, vm.videosError) : null,
        vm.videosLoading && vm.videos.length === 0 ? h('p', { class: 'mb-2 text-muted' }, 'Loading videos...') : null,
        h('table', { id: 'videosTable', class: 'table table-striped w-100 align-middle' }, [
            h('thead', [
                h('tr', [h('th', 'Poster'), h('th', 'Name'), h('th', 'Created'), h('th', 'Actions')]),
            ]),
            h(
                'tbody',
                vm.videos.length > 0
                    ? vm.videos.map((video) =>
                          h(
                              'tr',
                              {
                                  class: video.deleted === true ? 'video-row-deleted' : '',
                                  style: 'cursor:pointer;',
                                  onClick: () => vm.openVideoDetails(video.uuid),
                              },
                              [
                                  h('td', [renderPoster(video)]),
                                  h('td', { class: video.deleted === true ? 'video-title-deleted' : '' }, video.title || '-'),
                                   h('td', humanReadableDateTime(video.createdAt)),
                                  h('td', [renderDeleteButton(vm, video)]),
                              ]
                          )
                      )
                    : [h('tr', [h('td', { colspan: '4', class: 'text-muted text-center' }, 'No videos')])]
            ),
        ]),
        h('div', { class: 'd-flex justify-content-between align-items-center' }, [
            h(
                'button',
                {
                    type: 'button',
                    class: 'btn btn-outline-secondary btn-sm',
                    disabled: vm.videosMeta.page <= 1 || vm.videosLoading,
                    onClick: () => vm.loadVideos(vm.videosMeta.page - 1),
                },
                'Prev'
            ),
            h(
                'span',
                { class: 'text-muted small' },
                'Page ' + vm.videosMeta.page + ' / ' + vm.videosMeta.totalPages + ' (total ' + vm.videosMeta.total + ')'
            ),
            h(
                'button',
                {
                    type: 'button',
                    class: 'btn btn-outline-secondary btn-sm',
                    disabled: vm.videosMeta.page >= vm.videosMeta.totalPages || vm.videosLoading,
                    onClick: () => vm.loadVideos(vm.videosMeta.page + 1),
                },
                'Next'
            ),
        ]),
    ]);
}
