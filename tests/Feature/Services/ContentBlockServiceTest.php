<?php

declare(strict_types=1);

use Dskripchenko\LaravelTranslatable\Models\ContentBlock;
use Dskripchenko\LaravelTranslatable\Models\Page;
use Dskripchenko\LaravelTranslatable\Services\ContentBlockService;

it('preloads existing blocks into cache on construction', function () {
    ContentBlock::create(['key' => 'a', 'description' => 'A', 'type' => 'text', 'content' => 'Alpha']);
    ContentBlock::create(['key' => 'b', 'description' => 'B', 'type' => 'text', 'content' => 'Beta']);

    new ContentBlockService();

    expect(ContentBlockService::$cache)->toHaveCount(2)
        ->toHaveKeys(['a', 'b']);
});

it('get returns existing block from cache', function () {
    ContentBlock::create(['key' => 'existing', 'description' => 'Existing', 'type' => 'text', 'content' => 'Value']);

    $service = new ContentBlockService();
    $block = $service->get('existing', 'Desc');

    expect($block->key)->toBe('existing')
        ->and($block->content)->toBe('Value')
        ->and(ContentBlock::count())->toBe(1);
});

it('get creates new block via firstOrCreate', function () {
    $service = new ContentBlockService();
    $block = $service->get('new.block', 'New description', 'Default content', 'html');

    expect($block->key)->toBe('new.block')
        ->and($block->description)->toBe('New description')
        ->and($block->type)->toBe('html')
        ->and($block->content)->toBe('Default content')
        ->and($block->exists)->toBeTrue()
        ->and(ContentBlock::count())->toBe(1);
});

it('get caches newly created block', function () {
    $service = new ContentBlockService();

    $first = $service->get('dynamic', 'Desc', 'Content');
    $second = $service->get('dynamic', 'Different', 'Other');

    expect($first->id)->toBe($second->id)
        ->and(ContentBlock::count())->toBe(1);
});

it('global returns translated content', function () {
    $this->createLanguages();

    $service = new ContentBlockService();
    $result = $service->global('site.name', 'Site name', 'My Site');

    expect($result)->toBe('My Site');
});

it('global uses description as default when default is null', function () {
    $this->createLanguages();

    $service = new ContentBlockService();
    $result = $service->global('site.tagline', 'Welcome to our site');

    expect($result)->toBe('Welcome to our site');
});

it('inline returns translated content with page link', function () {
    $this->createLanguages();

    $service = new ContentBlockService();
    $result = $service->inline('hero.title', 'Hero title', 'Welcome');

    expect($result)->toBe('Welcome')
        ->and(Page::count())->toBe(1)
        ->and(\DB::table('page_content_block')->count())->toBe(1);
});

it('inline substitutes parameters', function () {
    $this->createLanguages();

    $service = new ContentBlockService();
    $result = $service->inline(
        'greeting',
        'Greeting message',
        'Hello, {name}! You have {count} items.',
        ['name' => 'John', 'count' => '5']
    );

    expect($result)->toBe('Hello, John! You have 5 items.');
});

it('inline uses description as default when default is null', function () {
    $this->createLanguages();

    $service = new ContentBlockService();
    $result = $service->inline('fallback.test', 'Fallback text here');

    expect($result)->toBe('Fallback text here');
});

it('page sets name on current page', function () {
    $this->createLanguages();

    $service = new ContentBlockService();
    // Trigger page creation first
    $service->inline('trigger', 'trigger', 'content');
    $service->page('Home Page');

    $page = Page::first();
    expect($page->name)->toBe('Home Page');
});

it('begin and end capture and translate output buffer', function () {
    $this->createLanguages();

    $service = new ContentBlockService();

    ob_start();
    $service->begin('html.block', 'HTML block');
    echo '<div>Default HTML content</div>';
    $service->end();
    $output = ob_get_clean();

    expect($output)->toBe('<div>Default HTML content</div>');
});

it('constructor is idempotent for static cache', function () {
    ContentBlock::create(['key' => 'x', 'description' => 'X', 'type' => 'text', 'content' => 'X']);

    $s1 = new ContentBlockService();
    ContentBlockService::$cache['extra'] = 'injected';

    $s2 = new ContentBlockService();

    expect(ContentBlockService::$cache)->toHaveKey('extra');
});
