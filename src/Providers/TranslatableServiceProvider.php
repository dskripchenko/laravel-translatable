<?php

namespace Dskripchenko\LaravelTranslatable\Providers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Support\ServiceProvider;

class TranslatableServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 2) . '/databases/migrations');
    }

    /**
     * @return void
     * @throws BindingResolutionException
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__, 2) . '/config/translatable.php',
            'translatable'
        );
    }

    /**
     * @param $path
     * @param $key
     *
     * @return void
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