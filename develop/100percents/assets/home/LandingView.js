import { defineComponent, h } from 'vue';
import { openContactUsModal } from './contactUs.js';

const SERVICES = [
    {
        emoji: '🎬',
        title: 'Video Transcoding',
        description: 'Convert your videos to any format and resolution. Fast cloud-based transcoding with preset profiles and real-time progress tracking.',
        link: 'https://video.100percents.com',
        linkLabel: '→ Open App',
    },
    {
        emoji: '✂️',
        title: 'Video Cut & Edit',
        description: 'Trim, cut, merge and edit your videos right in the browser. No software installation required.',
        status: 'under-construction',
    },
    {
        emoji: '🎤',
        title: 'Audio & Video Transcriber',
        description: 'Automatic speech-to-text transcription for audio and video files. Powered by AI for accurate results.',
        status: 'under-construction',
    },
    {
        emoji: '🤖',
        title: 'AI Video Editor',
        description: 'Let AI handle your video editing — smart cuts, auto captions, highlight reels and more.',
        status: 'under-construction',
    },
];

function renderServiceCard(service) {
    const action = service.status === 'under-construction'
        ? h('span', { class: 'badge bg-warning text-dark mt-auto py-2 px-3 fs-6' }, '🚧 Under Construction')
        : h(
            'a',
            { href: service.link, target: '_blank', rel: 'noopener noreferrer', class: 'btn btn-primary btn-sm mt-auto align-self-start' },
            service.linkLabel
        );

    return h('div', { class: 'col-12 col-sm-6 col-xl-3' }, [
        h('div', { class: 'feature-card h-100 d-flex flex-column' }, [
            h('div', { class: 'feature-icon mb-3' }, service.emoji),
            h('h5', { class: 'fw-semibold mb-2' }, service.title),
            h('p', { class: 'text-secondary small flex-grow-1 mb-3' }, service.description),
            action,
        ]),
    ]);
}

export function createLandingView() {
    return defineComponent({
        name: 'LandingView',
        render() {
            return h('div', {}, [
                h('div', { class: 'row g-4 my-2' },
                    SERVICES.map(renderServiceCard)
                ),
                h('div', { class: 'text-center mt-5 mb-3' }, [
                    h(
                        'button',
                        {
                            type: 'button',
                            class: 'btn btn-outline-primary btn-lg px-5',
                            onClick: () => void openContactUsModal(),
                        },
                        '✉️ Contact Us'
                    ),
                ]),
            ]);
        },
    });
}
