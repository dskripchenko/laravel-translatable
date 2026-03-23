<?php

declare(strict_types=1);

use Dskripchenko\LaravelTranslatable\Models\ContentBlock;
use Dskripchenko\LaravelTranslatable\Models\Page;

it('uses table name from config', function () {
    $page = new Page();
    expect($page->getTable())->toBe('pages');

    config()->set('translatable.tables.pages', 'custom_pages');
    expect($page->getTable())->toBe('custom_pages');
});

it('has correct fillable fields', function () {
    $page = new Page();
    expect($page->getFillable())->toBe(['name', 'uri']);
});

it('supports soft deletes', function () {
    $page = Page::create(['name' => 'Home', 'uri' => '/']);
    $page->delete();

    expect(Page::count())->toBe(0)
        ->and(Page::withTrashed()->count())->toBe(1);
});

it('has blocks relationship', function () {
    $page = Page::create(['name' => 'Home', 'uri' => '/']);

    expect($page->blocks())->toBeInstanceOf(
        \Illuminate\Database\Eloquent\Relations\BelongsToMany::class
    );
});

it('links content block to page', function () {
    $page = Page::create(['name' => 'Home', 'uri' => '/']);
    $page->setRelation('blocks', $page->blocks()->get());

    $block = ContentBlock::create([
        'key' => 'hero',
        'description' => 'Hero',
        'type' => 'text',
        'content' => 'Hello',
    ]);

    $page->link($block);

    expect($page->blocks)->toHaveCount(1)
        ->and($page->blocks->first()->id)->toBe($block->id);
});

it('does not duplicate linked blocks', function () {
    $page = Page::create(['name' => 'Home', 'uri' => '/']);
    $page->setRelation('blocks', $page->blocks()->get());

    $block = ContentBlock::create([
        'key' => 'hero',
        'description' => 'Hero',
        'type' => 'text',
        'content' => 'Hello',
    ]);

    $page->link($block);
    $page->link($block);

    $pivotCount = \DB::table('page_content_block')
        ->where('page_id', $page->id)
        ->count();

    expect($pivotCount)->toBe(1);
});

it('links multiple different blocks', function () {
    $page = Page::create(['name' => 'Home', 'uri' => '/']);
    $page->setRelation('blocks', $page->blocks()->get());

    $block1 = ContentBlock::create(['key' => 'a', 'description' => 'A', 'type' => 'text', 'content' => 'A']);
    $block2 = ContentBlock::create(['key' => 'b', 'description' => 'B', 'type' => 'text', 'content' => 'B']);

    $page->link($block1);
    $page->link($block2);

    expect($page->blocks)->toHaveCount(2);
});
