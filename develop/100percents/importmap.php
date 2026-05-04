<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@hotwired/turbo' => [
        'version' => '8.0.23',
    ],
    'vue' => [
        'version' => '3.5.30',
    ],
    'vue-router' => [
        'version' => '5.0.4',
    ],
    '@vue/runtime-dom' => [
        'version' => '3.5.30',
    ],
    '@vue/devtools-api' => [
        'version' => '8.1.0',
    ],
    '@vue/runtime-core' => [
        'version' => '3.5.30',
    ],
    '@vue/shared' => [
        'version' => '3.5.30',
    ],
    '@vue/devtools-kit' => [
        'version' => '8.1.0',
    ],
    '@vue/reactivity' => [
        'version' => '3.5.30',
    ],
    '@vue/devtools-shared' => [
        'version' => '8.1.0',
    ],
    'perfect-debounce' => [
        'version' => '2.1.0',
    ],
    'hookable' => [
        'version' => '6.1.0',
    ],
    'birpc' => [
        'version' => '4.0.0',
    ],
    'sweetalert2' => [
        'version' => '11.26.3',
    ],
];
