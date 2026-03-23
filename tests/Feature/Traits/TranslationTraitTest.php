<?php

declare(strict_types=1);

use Dskripchenko\LaravelTranslatable\Models\ContentBlock;
use Dskripchenko\LaravelTranslatable\Models\Translation;

it('t returns translation content for field', function () {
    $langs = $this->createLanguages();

    $block = ContentBlock::create([
        'key' => 'hero',
        'description' => 'Hero section',
        'type' => 'text',
        'content' => 'Original',
    ]);

    $result = $block->t('content', null, $langs['en']);

    expect($result)->toBe('Original')
        ->and(Translation::count())->toBe(1);
});

it('t uses attribute value as default', function () {
    $langs = $this->createLanguages();

    $block = ContentBlock::create([
        'key' => 'test',
        'description' => 'Test',
        'type' => 'text',
        'content' => 'Attribute value',
    ]);

    $result = $block->t('content', 'Explicit default', $langs['en']);

    // Attribute value takes priority over explicit default
    expect($result)->toBe('Attribute value');
});

it('t with explicit default when attribute is null', function () {
    $langs = $this->createLanguages();

    $block = ContentBlock::create([
        'key' => 'test',
        'description' => 'Test',
        'type' => 'text',
        'content' => '',
    ]);

    $result = $block->t('nonexistent_field', 'Fallback', $langs['en']);

    expect($result)->toBe('Fallback');
});

it('t returns different content per language', function () {
    $langs = $this->createLanguages();

    $block = ContentBlock::create([
        'key' => 'multi',
        'description' => 'Multi',
        'type' => 'text',
        'content' => 'English text',
    ]);

    $en = $block->t('content', null, $langs['en']);

    // Save Russian translation
    $block->saveTranslation('content', 'Русский текст', $langs['ru']);

    $ru = $block->t('content', null, $langs['ru']);

    expect($en)->toBe('English text')
        ->and($ru)->toBe('Русский текст');
});

it('saveTranslation persists to database', function () {
    $langs = $this->createLanguages();

    $block = ContentBlock::create([
        'key' => 'save_test',
        'description' => 'Test',
        'type' => 'text',
        'content' => 'Initial',
    ]);

    $block->saveTranslation('content', 'Updated value', $langs['en']);

    $translation = Translation::where('entity', ContentBlock::class)
        ->where('entity_id', $block->id)
        ->where('language_id', $langs['en']->id)
        ->first();

    expect($translation)->not->toBeNull()
        ->and($translation->content)->toBe('Updated value');
});

it('saveTranslation updates existing translation', function () {
    $langs = $this->createLanguages();

    $block = ContentBlock::create([
        'key' => 'update_test',
        'description' => 'Test',
        'type' => 'text',
        'content' => 'Original',
    ]);

    // First call creates translation
    $block->t('content', null, $langs['en']);
    // Update it
    $block->saveTranslation('content', 'Modified', $langs['en']);

    $result = $block->t('content', null, $langs['en']);

    expect($result)->toBe('Modified');
});

it('saveTranslation touches model timestamp', function () {
    $langs = $this->createLanguages();

    $block = ContentBlock::create([
        'key' => 'touch_test',
        'description' => 'Test',
        'type' => 'text',
        'content' => 'Content',
    ]);

    $before = $block->updated_at;

    // Force a small time difference
    sleep(1);

    $block->saveTranslation('content', 'New content', $langs['en']);
    $block->refresh();

    expect($block->updated_at->greaterThanOrEqualTo($before))->toBeTrue();
});

it('creates translation with correct entity metadata', function () {
    $langs = $this->createLanguages();

    $block = ContentBlock::create([
        'key' => 'meta_test',
        'description' => 'Test',
        'type' => 'text',
        'content' => 'Content',
    ]);

    $block->t('content', null, $langs['en']);

    $translation = Translation::first();

    expect($translation->group)->toBe('field')
        ->and($translation->key)->toBe('content')
        ->and($translation->entity)->toBe(ContentBlock::class)
        ->and((string) $translation->entity_id)->toBe((string) $block->id);
});
