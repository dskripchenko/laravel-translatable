<?php

declare(strict_types=1);

use Dskripchenko\LaravelTranslatable\Models\Language;
use Dskripchenko\LaravelTranslatable\Models\Translation;

it('uses table name from config', function () {
    $translation = new Translation();
    expect($translation->getTable())->toBe('translations');

    config()->set('translatable.tables.translations', 'custom_translations');
    expect($translation->getTable())->toBe('custom_translations');
});

it('has correct fillable fields', function () {
    $translation = new Translation();
    expect($translation->getFillable())->toBe([
        'language_id', 'group', 'key',
        'type', 'entity', 'entity_id', 'content',
    ]);
});

it('belongs to language', function () {
    $langs = $this->createLanguages();

    $translation = Translation::create([
        'language_id' => $langs['en']->id,
        'group' => 'default',
        'key' => 'test',
        'type' => 'default',
        'content' => 'Test content',
    ]);

    expect($translation->language)->toBeInstanceOf(Language::class)
        ->and($translation->language->id)->toBe($langs['en']->id);
});

it('stores entity fields', function () {
    $langs = $this->createLanguages();

    $translation = Translation::create([
        'language_id' => $langs['en']->id,
        'group' => 'field',
        'key' => 'name',
        'type' => 'default',
        'entity' => 'App\\Models\\Product',
        'entity_id' => '42',
        'content' => 'Product Name',
    ]);

    expect($translation->entity)->toBe('App\\Models\\Product')
        ->and($translation->entity_id)->toBe('42')
        ->and($translation->content)->toBe('Product Name');
});

it('enforces unique constraint with empty entity fields', function () {
    $langs = $this->createLanguages();

    Translation::create([
        'language_id' => $langs['en']->id,
        'group' => 'default',
        'key' => 'hello',
        'type' => 'default',
        'entity' => '',
        'entity_id' => '',
        'content' => 'Hello',
    ]);

    Translation::create([
        'language_id' => $langs['en']->id,
        'group' => 'default',
        'key' => 'hello',
        'type' => 'default',
        'entity' => '',
        'entity_id' => '',
        'content' => 'Duplicate',
    ]);
})->throws(\Illuminate\Database\QueryException::class);

it('enforces unique constraint on composite key', function () {
    $langs = $this->createLanguages();

    Translation::create([
        'language_id' => $langs['en']->id,
        'group' => 'default',
        'key' => 'hello',
        'type' => 'default',
        'entity' => 'App\\Models\\Item',
        'entity_id' => 1,
        'content' => 'Hello',
    ]);

    Translation::create([
        'language_id' => $langs['en']->id,
        'group' => 'default',
        'key' => 'hello',
        'type' => 'default',
        'entity' => 'App\\Models\\Item',
        'entity_id' => 1,
        'content' => 'Hello duplicate',
    ]);
})->throws(\Illuminate\Database\QueryException::class);

it('allows same key for different languages', function () {
    $langs = $this->createLanguages();

    $t1 = Translation::create([
        'language_id' => $langs['en']->id,
        'group' => 'default',
        'key' => 'hello',
        'type' => 'default',
        'content' => 'Hello',
    ]);

    $t2 = Translation::create([
        'language_id' => $langs['ru']->id,
        'group' => 'default',
        'key' => 'hello',
        'type' => 'default',
        'content' => 'Привет',
    ]);

    expect($t1->id)->not->toBe($t2->id)
        ->and(Translation::count())->toBe(2);
});
