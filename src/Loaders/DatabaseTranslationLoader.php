<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelTranslatable\Loaders;

use Dskripchenko\LaravelTranslatable\Models\Language;
use Dskripchenko\LaravelTranslatable\Models\Translation;
use Illuminate\Contracts\Translation\Loader as LoaderInterface;

class DatabaseTranslationLoader implements LoaderInterface
{
    public function __construct(
        protected LoaderInterface $fileLoader,
    ) {}

    public function load($locale, $group, $namespace = null): array
    {
        $fileTranslations = $this->fileLoader->load($locale, $group, $namespace);

        if ($namespace && $namespace !== '*') {
            return $fileTranslations;
        }

        try {
            $language = Language::byCode($locale);
        } catch (\Throwable) {
            return $fileTranslations;
        }

        $dbTranslations = Translation::query()
            ->where('language_id', $language->id)
            ->where('group', $group)
            ->where('entity', '')
            ->where('entity_id', '')
            ->pluck('content', 'key')
            ->toArray();

        return array_merge($fileTranslations, $dbTranslations);
    }

    public function addNamespace($namespace, $hint): void
    {
        $this->fileLoader->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path): void
    {
        $this->fileLoader->addJsonPath($path);
    }

    public function namespaces(): array
    {
        return $this->fileLoader->namespaces();
    }
}
