<?php

declare(strict_types=1);

use Dskripchenko\LaravelTranslatable\Models\ContentBlock;
use Dskripchenko\LaravelTranslatable\Models\Translation;
use Dskripchenko\LaravelTranslatable\Services\TranslationService;

// --- Plural forms ---

it('selects correct plural form', function () {
    $langs = $this->createLanguages();

    Translation::create([
        'language_id' => $langs['en']->id,
        'group' => 'default',
        'key' => 'items',
        'entity' => '',
        'entity_id' => '',
        'type' => 'default',
        'content' => '{0} No items|{1} One item|[2,*] :count items',
    ]);

    TranslationService::$cache = null;

    $zero = TranslationService::getTranslationChoice('items', 0, [], 'default', null, null, null, $langs['en']);
    $one = TranslationService::getTranslationChoice('items', 1, [], 'default', null, null, null, $langs['en']);
    $many = TranslationService::getTranslationChoice('items', 5, ['count' => '5'], 'default', null, null, null, $langs['en']);

    expect($zero)->toBe('No items')
        ->and($one)->toBe('One item')
        ->and($many)->toBe('5 items');
});

it('tc method on model with plural forms', function () {
    $langs = $this->createLanguages();

    $block = ContentBlock::create([
        'key' => 'plural_test',
        'description' => 'Test',
        'type' => 'text',
        'content' => 'One thing|Many things',
    ]);

    $one = $block->tc('content', 1, [], $langs['en']);
    $many = $block->tc('content', 5, [], $langs['en']);

    expect($one)->toBe('One thing')
        ->and($many)->toBe('Many things');
});

// --- Batch operations ---

it('setTranslations creates multiple translations', function () {
    $langs = $this->createLanguages();

    $count = TranslationService::setTranslations([
        ['key' => 'hello', 'content' => 'Hello'],
        ['key' => 'bye', 'content' => 'Goodbye'],
        ['key' => 'thanks', 'group' => 'ui', 'content' => 'Thanks'],
    ], $langs['en']);

    expect($count)->toBe(3)
        ->and(Translation::count())->toBe(3);
});

it('setTranslations updates existing translations', function () {
    $langs = $this->createLanguages();

    TranslationService::setTranslations([
        ['key' => 'hello', 'content' => 'Hello'],
    ], $langs['en']);

    $count = TranslationService::setTranslations([
        ['key' => 'hello', 'content' => 'Hi there'],
    ], $langs['en']);

    expect($count)->toBe(1)
        ->and(Translation::first()->content)->toBe('Hi there')
        ->and(Translation::count())->toBe(1);
});

it('setTranslations skips unchanged values', function () {
    $langs = $this->createLanguages();

    TranslationService::setTranslations([
        ['key' => 'static', 'content' => 'Same value'],
    ], $langs['en']);

    $count = TranslationService::setTranslations([
        ['key' => 'static', 'content' => 'Same value'],
    ], $langs['en']);

    expect($count)->toBe(0);
});

it('saveTranslations batch on model', function () {
    $langs = $this->createLanguages();

    $block = ContentBlock::create([
        'key' => 'batch_model',
        'description' => 'Test',
        'type' => 'text',
        'content' => 'Default',
    ]);

    $block->saveTranslations([
        'content' => 'Translated content',
        'description' => 'Translated desc',
    ], $langs['en']);

    expect(Translation::where('entity', ContentBlock::class)->count())->toBe(2);
});
