/**
 * Custom ESM loader for frontend unit tests run with plain Node.js.
 *
 * Remaps browser bare-specifiers (vue, vue-router, sweetalert2, …) to the
 * local vendor files under assets/vendor/ so tests can import application
 * modules that depend on those packages without a browser or bundler.
 *
 * Usage (handled automatically by tests.sh):
 *   node --experimental-loader ./tests/loader.mjs <test-file>
 */

import { fileURLToPath } from 'node:url';
import { dirname, resolve as resolvePath } from 'node:path';

const ASSETS_DIR = resolvePath(dirname(fileURLToPath(import.meta.url)), '..', 'vendor');

const SPECIFIER_MAP = {
    'vue':                  `${ASSETS_DIR}/vue/vue.index.js`,
    'vue-router':           `${ASSETS_DIR}/vue-router/vue-router.index.js`,
    '@vue/runtime-dom':     `${ASSETS_DIR}/@vue/runtime-dom/runtime-dom.index.js`,
    '@vue/runtime-core':    `${ASSETS_DIR}/@vue/runtime-core/runtime-core.index.js`,
    '@vue/reactivity':      `${ASSETS_DIR}/@vue/reactivity/reactivity.index.js`,
    '@vue/shared':          `${ASSETS_DIR}/@vue/shared/shared.index.js`,
    '@vue/devtools-api':    `${ASSETS_DIR}/@vue/devtools-api/devtools-api.index.js`,
    '@vue/devtools-kit':    `${ASSETS_DIR}/@vue/devtools-kit/devtools-kit.index.js`,
    '@vue/devtools-shared': `${ASSETS_DIR}/@vue/devtools-shared/devtools-shared.index.js`,
    'sweetalert2':          `${ASSETS_DIR}/sweetalert2/sweetalert2.index.js`,
    'perfect-debounce':     `${ASSETS_DIR}/perfect-debounce/perfect-debounce.index.js`,
    'hookable':             `${ASSETS_DIR}/hookable/hookable.index.js`,
    'birpc':                `${ASSETS_DIR}/birpc/birpc.index.js`,
};

export async function resolve(specifier, context, nextResolve) {
    const mapped = SPECIFIER_MAP[specifier];
    if (mapped) {
        return { url: `file://${mapped}`, shortCircuit: true };
    }
    return nextResolve(specifier, context);
}
