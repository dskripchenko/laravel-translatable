<?php

declare(strict_types=1);

use Dskripchenko\LaravelTranslatable\Models\Translation;
use Dskripchenko\LaravelTranslatable\Services\TranslationService;

it('returns fallback translation when auto_create is off', function () {
    $langs = $this->createLanguages();

    config()->set('translatable.auto_create', false);
    config()->set('translatable.fallback_locale', 'en');

    // Create English translation manually
    Translation::create([
        'language_id' => $langs['en']->id,
        'group' => 'default',
        'key' => 'welcome',
        'entity' => '',
        'entity_id' => '',
        'type' => 'default',
        'content' => 'Welcome',
    ]);

    TranslationService::$cache = null;

    $result = TranslationService::getTranslation(
        'welcome', 'default', null, null, 'Fallback default', $langs['ru']
    );

    expect($result->content)->toBe('Welcome');
});

it('returns default when no fallback available', function () {
    $langs = $this->createLanguages();

    config()->set('translatable.auto_create', false);
    config()->set('translatable.fallback_locale', 'en');

    $result = TranslationService::getTranslation(
        'missing', 'default', null, null, 'Default text', $langs['ru']
    );

    expect($result->content)->toBe('Default text')
        ->and($result->exists)->toBeFalse();
});

it('creates translation in requested language when auto_create is on', function () {
    $langs = $this->createLanguages();

    config()->set('translatable.fallback_locale', 'en');

    // Create English translation
    Translation::create([
        'language_id' => $langs['en']->id,
        'group' => 'default',
        'key' => 'hello',
        'entity' => '',
        'entity_id' => '',
        'type' => 'default',
        'content' => 'Hello',
    ]);

    TranslationService::$cache = null;

    // With auto_create=true, should create ru translation, not return fallback
    $result = TranslationService::getTranslation(
        'hello', 'default', null, null, 'Привет', $langs['ru']
    );

    expect($result->content)->toBe('Привет')
        ->and($result->language_id)->toBe($langs['ru']->id)
        ->and(Translation::count())->toBe(2);
});

it('does not fallback when same locale', function () {
    $langs = $this->createLanguages();

    config()->set('translatable.auto_create', false);
    config()->set('translatable.fallback_locale', 'en');

    $result = TranslationService::getTranslation(
        'test', 'default', null, null, 'Default', $langs['en']
    );

    expect($result->content)->toBe('Default');
});
