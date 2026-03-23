<?php

declare(strict_types=1);

use Dskripchenko\LaravelTranslatable\Events\TranslationCreated;
use Dskripchenko\LaravelTranslatable\Events\TranslationUpdated;
use Dskripchenko\LaravelTranslatable\Models\ContentBlock;
use Dskripchenko\LaravelTranslatable\Services\TranslationService;
use Illuminate\Support\Facades\Event;

it('dispatches TranslationCreated on first access', function () {
    Event::fake([TranslationCreated::class]);
    $langs = $this->createLanguages();

    TranslationService::getTranslation('greeting', 'default', null, null, 'Hello', $langs['en']);

    Event::assertDispatched(TranslationCreated::class, function ($event) {
        return $event->translation->content === 'Hello';
    });
});

it('does not dispatch TranslationCreated on cache hit', function () {
    $langs = $this->createLanguages();
    TranslationService::getTranslation('greeting', 'default', null, null, 'Hello', $langs['en']);

    Event::fake([TranslationCreated::class]);
    TranslationService::getTranslation('greeting', 'default', null, null, 'Hello', $langs['en']);

    Event::assertNotDispatched(TranslationCreated::class);
});

it('dispatches TranslationUpdated on saveTranslation with changed value', function () {
    Event::fake([TranslationUpdated::class]);
    $langs = $this->createLanguages();

    $block = ContentBlock::create([
        'key' => 'ev_test',
        'description' => 'Test',
        'type' => 'text',
        'content' => 'Original',
    ]);

    $block->t('content', null, $langs['en']);

    Event::fake([TranslationUpdated::class]);
    $block->saveTranslation('content', 'Updated', $langs['en']);

    Event::assertDispatched(TranslationUpdated::class, function ($event) {
        return $event->translation->content === 'Updated'
            && $event->oldContent === 'Original';
    });
});

it('does not dispatch TranslationUpdated when value unchanged', function () {
    Event::fake([TranslationUpdated::class]);
    $langs = $this->createLanguages();

    $block = ContentBlock::create([
        'key' => 'ev_same',
        'description' => 'Test',
        'type' => 'text',
        'content' => 'Same',
    ]);

    $block->t('content', null, $langs['en']);
    $block->saveTranslation('content', 'Same', $langs['en']);

    Event::assertNotDispatched(TranslationUpdated::class);
});
