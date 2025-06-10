<?php

return [
    'tables' => [
        'languages' => env('TRANSLATABLE_LANGUAGES_TABLE', 'languages'),
        'translations' => env('TRANSLATABLE_TRANSLATIONS_TABLE', 'translations'),
        'content_blocks' => env('TRANSLATABLE_CONTENT_BLOCKS_TABLE', 'content_blocks'),
        'pages' => env('TRANSLATABLE_PAGES_TABLE', 'pages'),
        'page_content_block' => env('TRANSLATABLE_PAGE_CONTENT_BLOCK_TABLE', 'page_content_block'),
    ],
];
