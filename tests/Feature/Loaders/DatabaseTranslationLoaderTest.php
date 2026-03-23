<?php

declare(strict_types=1);

use Dskripchenko\LaravelTranslatable\Loaders\DatabaseTranslationLoader;
use Dskripchenko\LaravelTranslatable\Models\Translation;
use Illuminate\Contracts\Translation\Loader as LoaderInterface;

it('loads translations from database', function () {
    $langs = $this->createLanguages();

    Translation::create([
        'language_id' => $langs['en']->id,
        'group' => 'messages',
        'key' => 'welcome',
        'entity' => '',
        'entity_id' => '',
        'type' => 'default',
        'content' => 'Welcome from DB',
    ]);

    $fileLoader = Mockery::mock(LoaderInterface::class);
    $fileLoader->shouldReceive('load')
        ->with('en', 'messages', null)
        ->andReturn(['greeting' => 'Hello from file']);

    $loader = new DatabaseTranslationLoader($fileLoader);
    $result = $loader->load('en', 'messages');

    expect($result)->toBe([
        'greeting' => 'Hello from file',
        'welcome' => 'Welcome from DB',
    ]);
});

it('db translations override file translations', function () {
    $langs = $this->createLanguages();

    Translation::create([
        'language_id' => $langs['en']->id,
        'group' => 'messages',
        'key' => 'hello',
        'entity' => '',
        'entity_id' => '',
        'type' => 'default',
        'content' => 'DB Hello',
    ]);

    $fileLoader = Mockery::mock(LoaderInterface::class);
    $fileLoader->shouldReceive('load')
        ->with('en', 'messages', null)
        ->andReturn(['hello' => 'File Hello']);

    $loader = new DatabaseTranslationLoader($fileLoader);
    $result = $loader->load('en', 'messages');

    expect($result['hello'])->toBe('DB Hello');
});

it('falls back to file loader for unknown language', function () {
    $fileLoader = Mockery::mock(LoaderInterface::class);
    $fileLoader->shouldReceive('load')
        ->with('xx', 'messages', null)
        ->andReturn(['key' => 'value']);

    $loader = new DatabaseTranslationLoader($fileLoader);
    $result = $loader->load('xx', 'messages');

    expect($result)->toBe(['key' => 'value']);
});

it('skips db for vendor namespaces', function () {
    $fileLoader = Mockery::mock(LoaderInterface::class);
    $fileLoader->shouldReceive('load')
        ->with('en', 'messages', 'vendor-package')
        ->andReturn(['key' => 'vendor value']);

    $loader = new DatabaseTranslationLoader($fileLoader);
    $result = $loader->load('en', 'messages', 'vendor-package');

    expect($result)->toBe(['key' => 'vendor value']);
});

it('delegates addNamespace to file loader', function () {
    $fileLoader = Mockery::mock(LoaderInterface::class);
    $fileLoader->shouldReceive('addNamespace')
        ->with('test', '/path')
        ->once();

    $loader = new DatabaseTranslationLoader($fileLoader);
    $loader->addNamespace('test', '/path');
});

it('delegates namespaces to file loader', function () {
    $fileLoader = Mockery::mock(LoaderInterface::class);
    $fileLoader->shouldReceive('namespaces')
        ->andReturn(['ns' => '/path']);

    $loader = new DatabaseTranslationLoader($fileLoader);

    expect($loader->namespaces())->toBe(['ns' => '/path']);
});

it('delegates addJsonPath to file loader', function () {
    $fileLoader = Mockery::mock(LoaderInterface::class);
    $fileLoader->shouldReceive('addJsonPath')
        ->with('/json/path')
        ->once();

    $loader = new DatabaseTranslationLoader($fileLoader);
    $loader->addJsonPath('/json/path');
});
