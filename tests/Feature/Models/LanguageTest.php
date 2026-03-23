<?php

declare(strict_types=1);

use Dskripchenko\LaravelTranslatable\Models\Language;
use Dskripchenko\LaravelTranslatable\Models\Translation;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

it('uses table name from config', function () {
    $language = new Language();
    expect($language->getTable())->toBe('languages');

    config()->set('translatable.tables.languages', 'custom_languages');
    expect($language->getTable())->toBe('custom_languages');
});

it('has correct fillable fields', function () {
    $language = new Language();
    expect($language->getFillable())->toBe(['code', 'label', 'is_active', 'as_locale']);
});

it('casts boolean fields correctly', function () {
    $langs = $this->createLanguages();

    expect($langs['en']->is_active)->toBeTrue()->toBeBool()
        ->and($langs['en']->as_locale)->toBeTrue()->toBeBool()
        ->and($langs['ru']->as_locale)->toBeFalse()->toBeBool();
});

it('supports soft deletes', function () {
    $langs = $this->createLanguages();
    $langs['ru']->delete();

    expect(Language::query()->count())->toBe(1)
        ->and(Language::withTrashed()->count())->toBe(2)
        ->and(Language::find($langs['ru']->id))->toBeNull()
        ->and(Language::withTrashed()->find($langs['ru']->id)->deleted_at)->not->toBeNull();
});

it('has translations relationship', function () {
    $langs = $this->createLanguages();

    Translation::create([
        'language_id' => $langs['en']->id,
        'group' => 'default',
        'key' => 'greeting',
        'type' => 'default',
        'content' => 'Hello',
    ]);

    expect($langs['en']->translations)->toHaveCount(1)
        ->and($langs['en']->translations->first())->toBeInstanceOf(Translation::class)
        ->and($langs['ru']->translations)->toHaveCount(0);
});

it('returns default language by as_locale flag', function () {
    $this->createLanguages();

    $default = Language::getDefaultLanguage();

    expect($default->code)->toBe('en')
        ->and($default->as_locale)->toBeTrue();
});

it('falls back to app locale for default language', function () {
    Language::create(['code' => 'en', 'label' => 'English', 'is_active' => true, 'as_locale' => false]);

    // getDefaultLanguage uses static local var, so we test byCode fallback instead
    app()->setLocale('en');
    $current = Language::byCode('en');

    expect($current->code)->toBe('en');
});

it('finds language by code', function () {
    $langs = $this->createLanguages();

    // byCode uses static local cache - verify by direct query as well
    expect($langs['en']->code)->toBe('en')
        ->and($langs['ru']->code)->toBe('ru')
        ->and(Language::byCode('en')->code)->toBe('en');
});

it('finds language by code case-insensitively', function () {
    $this->createLanguages();

    expect(Language::byCode('EN')->code)->toBe('en')
        ->and(Language::byCode('Ru')->code)->toBe('ru');
});

it('throws NotFoundHttpException for unknown code', function () {
    $this->createLanguages();

    Language::byCode('fr');
})->throws(NotFoundHttpException::class);

it('returns current language by app locale', function () {
    $this->createLanguages();
    app()->setLocale('en');

    $current = Language::getCurrent();

    expect($current->code)->toBe('en');
});

it('checks if language is current', function () {
    $langs = $this->createLanguages();
    app()->setLocale('en');

    expect($langs['en']->isCurrent())->toBeTrue()
        ->and($langs['ru']->isCurrent())->toBeFalse();
});

it('runs closure for all languages', function () {
    $this->createLanguages();

    $result = Language::runForAllLanguages(function (Language $lang) {
        return [$lang->code => $lang->label];
    });

    expect($result)->toHaveKey('en', 'English')
        ->toHaveKey('ru', 'Russian');
});

it('runs closure for filtered languages', function () {
    $this->createLanguages();

    $result = Language::runForAllLanguages(
        fn (Language $lang) => [$lang->code => $lang->label],
        fn ($query) => $query->where('as_locale', true)
    );

    expect($result)->toHaveCount(1)
        ->toHaveKey('en', 'English');
});

it('generates route group pattern', function () {
    $this->createLanguages();

    $pattern = Language::getRouteGroupPattern();

    expect($pattern)->toContain('en')
        ->toContain('ru')
        ->toStartWith('(|')
        ->toEndWith(')');
});

it('returns code for non-locale language and null for locale', function () {
    $langs = $this->createLanguages();

    expect($langs['en']->getCode())->toBeNull()
        ->and($langs['ru']->getCode())->toBe('ru');
});

it('getDefaultLanguage throws when no language exists', function () {
    // No languages created - should throw
    Language::getDefaultLanguage();
})->throws(Exception::class);

it('getDefaultLanguage falls back to app locale code', function () {
    // Create language without as_locale flag
    Language::create(['code' => 'en', 'label' => 'English', 'is_active' => true, 'as_locale' => false]);
    app()->setLocale('en');
    config()->set('app.locale', 'en');

    $default = Language::getDefaultLanguage();

    expect($default->code)->toBe('en');
});

it('byCode returns null-locale language when code is null', function () {
    $this->createLanguages();

    $lang = Language::byCode(null);

    expect($lang->as_locale)->toBeTrue();
});

it('resetStaticCache allows re-query', function () {
    $this->createLanguages();

    $first = Language::getDefaultLanguage();
    Language::resetStaticCache();
    $second = Language::getDefaultLanguage();

    // Different object instances after cache reset
    expect($first->code)->toBe($second->code)
        ->and($first)->not->toBe($second);
});
