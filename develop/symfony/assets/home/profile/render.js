import { h } from 'vue';

export function renderProfile(vm) {
    if (vm.loading) {
        return h('div', { class: 'py-4' }, [
            h('h1', { class: 'mb-3' }, 'Profile'),
            h('p', { class: 'text-muted' }, 'Loading...'),
        ]);
    }

    if (vm.error) {
        return h('div', { class: 'py-4' }, [
            h('h1', { class: 'mb-3' }, 'Profile'),
            h('div', { class: 'alert alert-danger' }, vm.error),
            h(
                'button',
                {
                    type: 'button',
                    class: 'btn btn-outline-secondary',
                    onClick: vm.goHome,
                },
                'Back to home'
            ),
        ]);
    }

    if (!vm.dto) {
        return h('div', { class: 'py-4' }, [h('h1', { class: 'mb-3' }, 'Profile')]);
    }
}
