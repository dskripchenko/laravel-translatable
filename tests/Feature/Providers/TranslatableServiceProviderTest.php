<?php

declare(strict_types=1);

it('registers translatable config', function () {
    $tables = config('translatable.tables');

    expect($tables)->toBeArray()
        ->and($tables['languages'])->toBe('languages')
        ->and($tables['translations'])->toBe('translations')
        ->and($tables['content_blocks'])->toBe('content_blocks')
        ->and($tables['pages'])->toBe('pages')
        ->and($tables['page_content_block'])->toBe('page_content_block');
});

it('registers auto_create config', function () {
    expect(config('translatable.auto_create'))->toBeTrue();
});

it('merges config deeply with app overrides', function () {
    config()->set('translatable.tables.languages', 'custom_languages');

    expect(config('translatable.tables.languages'))->toBe('custom_languages')
        ->and(config('translatable.tables.translations'))->toBe('translations');
});

it('loads migrations and creates all tables', function () {
    expect(fn () => \DB::table('languages')->count())->not->toThrow(\Exception::class)
        ->and(fn () => \DB::table('translations')->count())->not->toThrow(\Exception::class)
        ->and(fn () => \DB::table('content_blocks')->count())->not->toThrow(\Exception::class)
        ->and(fn () => \DB::table('pages')->count())->not->toThrow(\Exception::class)
        ->and(fn () => \DB::table('page_content_block')->count())->not->toThrow(\Exception::class);
});
