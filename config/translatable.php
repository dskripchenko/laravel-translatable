<?php

use Dskripchenko\LaravelDelayedLog\Components\DelayedLogger;
use Monolog\Formatter\NormalizerFormatter;

return [
    'tables' => [
        'languages' => env('TRANSLATABLE_LANGUAGES_TABLE', 'languages'),
        'translations' => env('TRANSLATABLE_TRANSLATIONS_TABLE', 'translations'),
    ],
];
