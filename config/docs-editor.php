<?php

return [
    'github' => [
        'token' => env('DOCS_EDITOR_GITHUB_TOKEN'),
        'owner' => env('DOCS_EDITOR_GITHUB_OWNER'),
        'repo' => env('DOCS_EDITOR_GITHUB_REPO'),
        'base_branch' => env('DOCS_EDITOR_GITHUB_BRANCH', 'main'),
    ],

    'docs_path' => env('DOCS_EDITOR_DOCS_PATH', 'docs'),

    'media_path' => env('DOCS_EDITOR_MEDIA_PATH', 'public/docs-media'),

    'live_url' => env('DOCS_EDITOR_LIVE_URL', ''),

    'route' => [
        'prefix' => env('DOCS_EDITOR_ROUTE_PREFIX', 'admin/docs'),
        'middleware' => ['web'],
    ],

    'nova_menu' => env('DOCS_EDITOR_NOVA_MENU', false),
];
