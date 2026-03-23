<?php

declare(strict_types=1);

use Dskripchenko\LaravelTranslatable\Models\ContentBlock;

it('uses table name from config', function () {
    $block = new ContentBlock();
    expect($block->getTable())->toBe('content_blocks');

    config()->set('translatable.tables.content_blocks', 'custom_blocks');
    expect($block->getTable())->toBe('custom_blocks');
});

it('has correct fillable fields', function () {
    $block = new ContentBlock();
    expect($block->getFillable())->toBe(['key', 'description', 'type', 'content']);
});

it('creates content block with all fields', function () {
    $block = ContentBlock::create([
        'key' => 'hero.title',
        'description' => 'Hero section title',
        'type' => 'text',
        'content' => 'Welcome',
    ]);

    expect($block->key)->toBe('hero.title')
        ->and($block->description)->toBe('Hero section title')
        ->and($block->type)->toBe('text')
        ->and($block->content)->toBe('Welcome')
        ->and($block->id)->toBeInt();
});

it('enforces unique key constraint', function () {
    ContentBlock::create([
        'key' => 'unique.block',
        'description' => 'First',
        'type' => 'text',
        'content' => 'A',
    ]);

    ContentBlock::create([
        'key' => 'unique.block',
        'description' => 'Duplicate',
        'type' => 'text',
        'content' => 'B',
    ]);
})->throws(\Illuminate\Database\QueryException::class);

it('has TranslationTrait methods', function () {
    $block = new ContentBlock();

    expect(method_exists($block, 't'))->toBeTrue()
        ->and(method_exists($block, 'saveTranslation'))->toBeTrue();
});
