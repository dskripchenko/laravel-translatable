<?php

declare(strict_types=1);

namespace Tests;

use Dskripchenko\LaravelTranslatable\Models\Language;
use Dskripchenko\LaravelTranslatable\Providers\TranslatableServiceProvider;
use Dskripchenko\LaravelTranslatable\Services\ContentBlockService;
use Dskripchenko\LaravelTranslatable\Services\TranslationService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        TranslationService::$cache = null;
        ContentBlockService::$cache = null;
        Language::resetStaticCache();
    }

    protected function getPackageProviders($app): array
    {
        return [TranslatableServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.translation_hash', 'test');
        $app['config']->set('cache.translation_ttl', 3600);

        $app['config']->set('app.locale', 'en');
    }

    protected function createLanguages(): array
    {
        $en = Language::create([
            'code' => 'en',
            'label' => 'English',
            'is_active' => true,
            'as_locale' => true,
        ]);

        $ru = Language::create([
            'code' => 'ru',
            'label' => 'Russian',
            'is_active' => true,
            'as_locale' => false,
        ]);

        return ['en' => $en, 'ru' => $ru];
    }
}
