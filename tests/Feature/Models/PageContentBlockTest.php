<?php

declare(strict_types=1);

use Dskripchenko\LaravelTranslatable\Models\PageContentBlock;

it('uses table name from config', function () {
    $pivot = new PageContentBlock();
    expect($pivot->getTable())->toBe('page_content_block');

    config()->set('translatable.tables.page_content_block', 'custom_pivot');
    expect($pivot->getTable())->toBe('custom_pivot');
});

it('has timestamps disabled', function () {
    $pivot = new PageContentBlock();
    expect($pivot->timestamps)->toBeFalse();
});

it('has correct fillable fields', function () {
    $pivot = new PageContentBlock();
    expect($pivot->getFillable())->toBe(['page_id', 'content_block_id']);
});
