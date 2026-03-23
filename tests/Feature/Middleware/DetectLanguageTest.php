<?php

declare(strict_types=1);

use Dskripchenko\LaravelTranslatable\Http\Middleware\DetectLanguage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

it('detects language from cookie', function () {
    $this->createLanguages();

    $request = Request::create('/some-page');
    $request->cookies->set('locale', 'ru');

    $middleware = new DetectLanguage();
    $middleware->handle($request, fn () => new Response());

    expect(app()->getLocale())->toBe('ru');
});

it('ignores invalid language in cookie', function () {
    $this->createLanguages();
    app()->setLocale('en');

    $request = Request::create('/some-page');
    $request->cookies->set('locale', 'xx');

    $middleware = new DetectLanguage();
    $middleware->handle($request, fn () => new Response());

    expect(app()->getLocale())->toBe('en');
});

it('detects language from Accept-Language header', function () {
    $this->createLanguages();

    $request = Request::create('/some-page', 'GET', [], [], [], [
        'HTTP_ACCEPT_LANGUAGE' => 'ru-RU,ru;q=0.9,en;q=0.8',
    ]);

    $middleware = new DetectLanguage();
    $middleware->handle($request, fn () => new Response());

    expect(app()->getLocale())->toBe('ru');
});

it('detects language from URL segment', function () {
    $this->createLanguages();

    $request = Request::create('/ru/some-page');

    $middleware = new DetectLanguage();
    $middleware->handle($request, fn () => new Response());

    expect(app()->getLocale())->toBe('ru');
});

it('does not change locale when no language detected', function () {
    $this->createLanguages();
    app()->setLocale('en');

    $request = Request::create('/some-page');

    $middleware = new DetectLanguage();
    $middleware->handle($request, fn () => new Response());

    expect(app()->getLocale())->toBe('en');
});
