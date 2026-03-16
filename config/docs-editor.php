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

    'route' => [
        'prefix' => env('DOCS_EDITOR_ROUTE_PREFIX', 'admin/docs'),
    ],
];
