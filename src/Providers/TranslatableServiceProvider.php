<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelTranslatable\Providers;

use Dskripchenko\LaravelTranslatable\Console\ExportCommand;
use Dskripchenko\LaravelTranslatable\Console\ImportCommand;
use Dskripchenko\LaravelTranslatable\Console\ScanCommand;
use Dskripchenko\LaravelTranslatable\Http\Middleware\DetectLanguage;
use Dskripchenko\LaravelTranslatable\Loaders\DatabaseTranslationLoader;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class TranslatableServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 2) . '/databases/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ExportCommand::class,
                ImportCommand::class,
                ScanCommand::class,
            ]);
        }

        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('translatable.detect', DetectLanguage::class);
    }

    /**
     * @throws BindingResolutionException
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__, 2) . '/config/translatable.php',
            'translatable'
        );

        $this->app->booted(function () {
            if (config('translatable.database_loader')) {
                $this->app->extend('translation.loader', function ($fileLoader) {
                    return new DatabaseTranslationLoader($fileLoader);
                });
            }
        });
    }

    /**
     * @throws BindingResolutionException
     */
    protected function mergeConfigFrom($path, $key): void
    {
        if (
            ! ($this->app instanceof CachesConfiguration
                && $this->app->configurationIsCached())
        ) {
            $config = $this->app->make('config');
            $config->set($key, array_merge_deep(require $path, $config->get($key, [])));
        }
    }
}
