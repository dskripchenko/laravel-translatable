<?php

declare(strict_types=1);

use Dskripchenko\LaravelTranslatable\Models\ContentBlock;

it('whereTranslation filters by exact match', function () {
    $langs = $this->createLanguages();

    $b1 = ContentBlock::create(['key' => 'a', 'description' => 'A', 'type' => 'text', 'content' => 'Alpha']);
    $b2 = ContentBlock::create(['key' => 'b', 'description' => 'B', 'type' => 'text', 'content' => 'Beta']);

    $b1->t('content', null, $langs['en']);
    $b2->t('content', null, $langs['en']);

    $results = ContentBlock::whereTranslation('content', 'Alpha', null, 'en')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->key)->toBe('a');
});

it('whereTranslation filters with operator', function () {
    $langs = $this->createLanguages();

    $b1 = ContentBlock::create(['key' => 'x', 'description' => 'X', 'type' => 'text', 'content' => 'Hello World']);
    $b2 = ContentBlock::create(['key' => 'y', 'description' => 'Y', 'type' => 'text', 'content' => 'Goodbye']);

    $b1->t('content', null, $langs['en']);
    $b2->t('content', null, $langs['en']);

    $results = ContentBlock::whereTranslation('content', 'like', '%Hello%', 'en')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->key)->toBe('x');
});

it('orderByTranslation sorts results', function () {
    $langs = $this->createLanguages();

    $b1 = ContentBlock::create(['key' => 'c', 'description' => 'C', 'type' => 'text', 'content' => 'Charlie']);
    $b2 = ContentBlock::create(['key' => 'a', 'description' => 'A', 'type' => 'text', 'content' => 'Alpha']);
    $b3 = ContentBlock::create(['key' => 'b', 'description' => 'B', 'type' => 'text', 'content' => 'Bravo']);

    $b1->t('content', null, $langs['en']);
    $b2->t('content', null, $langs['en']);
    $b3->t('content', null, $langs['en']);

    $results = ContentBlock::orderByTranslation('content', 'asc', 'en')
        ->pluck('key')
        ->toArray();

    expect($results)->toBe(['a', 'b', 'c']);
});

it('orderByTranslation desc', function () {
    $langs = $this->createLanguages();

    $b1 = ContentBlock::create(['key' => 'first', 'description' => 'A', 'type' => 'text', 'content' => 'AAA']);
    $b2 = ContentBlock::create(['key' => 'second', 'description' => 'B', 'type' => 'text', 'content' => 'ZZZ']);

    $b1->t('content', null, $langs['en']);
    $b2->t('content', null, $langs['en']);

    $results = ContentBlock::orderByTranslation('content', 'desc', 'en')
        ->pluck('key')
        ->toArray();

    expect($results)->toBe(['second', 'first']);
});
