<?php

declare(strict_types=1);

use Dskripchenko\LaravelTranslatable\Models\Language;
use Dskripchenko\LaravelTranslatable\Models\Translation;
use Dskripchenko\LaravelTranslatable\Services\TranslationService;

it('initializes empty cache on boot', function () {
    expect(TranslationService::$cache)->toBeNull();

    TranslationService::boot();

    expect(TranslationService::$cache)->toBe([]);
});

it('boot is idempotent', function () {
    TranslationService::boot();
    TranslationService::$cache['test'] = 'value';
    TranslationService::boot();

    expect(TranslationService::$cache)->toHaveKey('test');
});

it('creates translation on cache miss', function () {
    $langs = $this->createLanguages();

    $translation = TranslationService::getTranslation(
        'greeting', 'default', null, null, 'Hello', $langs['en']
    );

    expect($translation->content)->toBe('Hello')
        ->and($translation->exists)->toBeTrue()
        ->and(Translation::count())->toBe(1);
});

it('returns cached translation on hit', function () {
    $langs = $this->createLanguages();

    $first = TranslationService::getTranslation(
        'greeting', 'default', null, null, 'Hello', $langs['en']
    );

    $second = TranslationService::getTranslation(
        'greeting', 'default', null, null, 'Different', $langs['en']
    );

    expect($second->id)->toBe($first->id)
        ->and($second->content)->toBe('Hello')
        ->and(Translation::count())->toBe(1);
});

it('separates translations by language', function () {
    $langs = $this->createLanguages();

    $en = TranslationService::getTranslation(
        'greeting', 'default', null, null, 'Hello', $langs['en']
    );

    $ru = TranslationService::getTranslation(
        'greeting', 'default', null, null, 'Привет', $langs['ru']
    );

    expect($en->content)->toBe('Hello')
        ->and($ru->content)->toBe('Привет')
        ->and($en->id)->not->toBe($ru->id)
        ->and(Translation::count())->toBe(2);
});

it('separates translations by group', function () {
    $langs = $this->createLanguages();

    $t1 = TranslationService::getTranslation(
        'name', 'ui', null, null, 'Name', $langs['en']
    );

    $t2 = TranslationService::getTranslation(
        'name', 'validation', null, null, 'Name field', $langs['en']
    );

    expect($t1->id)->not->toBe($t2->id)
        ->and(Translation::count())->toBe(2);
});

it('separates translations by entity', function () {
    $langs = $this->createLanguages();

    $t1 = TranslationService::getTranslation(
        'name', 'field', 'App\\Models\\Product', '1', 'Product 1', $langs['en']
    );

    $t2 = TranslationService::getTranslation(
        'name', 'field', 'App\\Models\\Product', '2', 'Product 2', $langs['en']
    );

    expect($t1->id)->not->toBe($t2->id)
        ->and($t1->content)->toBe('Product 1')
        ->and($t2->content)->toBe('Product 2');
});

it('does not persist when auto_create is false', function () {
    $langs = $this->createLanguages();
    config()->set('translatable.auto_create', false);

    $translation = TranslationService::getTranslation(
        'test', 'default', null, null, 'Default text', $langs['en']
    );

    expect($translation->content)->toBe('Default text')
        ->and($translation->exists)->toBeFalse()
        ->and(Translation::count())->toBe(0);
});

it('refresh updates in-memory cache', function () {
    $langs = $this->createLanguages();

    $translation = TranslationService::getTranslation(
        'greeting', 'default', null, null, 'Hello', $langs['en']
    );

    $translation->content = 'Hi there';
    $translation->save();
    TranslationService::refresh($translation, $langs['en']);

    $cached = TranslationService::getTranslation(
        'greeting', 'default', null, null, 'Fallback', $langs['en']
    );

    expect($cached->content)->toBe('Hi there');
});

it('loads translations from persistent cache on boot', function () {
    $langs = $this->createLanguages();

    // First: create and populate
    TranslationService::getTranslation(
        'cached_key', 'default', null, null, 'Cached value', $langs['en']
    );

    // Reset in-memory cache
    TranslationService::$cache = null;

    // Should load from Cache::tags->remember
    $result = TranslationService::getTranslation(
        'cached_key', 'default', null, null, 'New default', $langs['en']
    );

    expect($result->content)->toBe('Cached value');
});

it('getKey produces deterministic results', function () {
    $method = new ReflectionMethod(TranslationService::class, 'getKey');

    $key1 = $method->invoke(null, 'name', 'field', 'App\\Models\\User', '1');
    $key2 = $method->invoke(null, 'name', 'field', 'App\\Models\\User', '1');

    expect($key1)->toBe($key2);
});

it('getKey produces different results for different inputs', function () {
    $method = new ReflectionMethod(TranslationService::class, 'getKey');

    $key1 = $method->invoke(null, 'name', 'field', 'App\\Models\\User', '1');
    $key2 = $method->invoke(null, 'name', 'field', 'App\\Models\\User', '2');
    $key3 = $method->invoke(null, 'title', 'field', 'App\\Models\\User', '1');
    $key4 = $method->invoke(null, 'name', 'field', 'App\\Models\\Product', '1');

    expect($key1)->not->toBe($key2)
        ->and($key1)->not->toBe($key3)
        ->and($key1)->not->toBe($key4);
});

it('getKey handles null entity fields', function () {
    $method = new ReflectionMethod(TranslationService::class, 'getKey');

    $key1 = $method->invoke(null, 'test', 'default', null, null);
    $key2 = $method->invoke(null, 'test', 'default', null, null);

    expect($key1)->toBe($key2)->toBeString();
});
