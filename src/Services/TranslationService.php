<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelTranslatable\Services;

use Dskripchenko\LaravelTranslatable\Events\TranslationCreated;
use Dskripchenko\LaravelTranslatable\Events\TranslationUpdated;
use Dskripchenko\LaravelTranslatable\Models\Language;
use Dskripchenko\LaravelTranslatable\Models\Translation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Translation\MessageSelector;

class TranslationService
{
    public static ?array $cache = null;

    public static function boot(): void
    {
        if (!is_null(static::$cache)) {
            return;
        }
        static::$cache = [];
    }

    protected static function bootLanguage(Language $language): void
    {
        if (isset(static::$cache[$language->code])) {
            return;
        }

        $hash = config('cache.translation_hash');
        $cacheKey = "translation_static_cache_{$language->code}_{$hash}";
        $ttl = config('cache.translation_ttl');

        static::$cache[$language->code] = Cache::tags(['translation_static_cache'])
            ->remember($cacheKey, $ttl, static function () use ($language) {
                return Translation::query()
                    ->where('language_id', $language->id)
                    ->get()
                    ->mapWithKeys(static function (Translation $translation) {
                        $key = static::getKey(
                            $translation->key,
                            $translation->group,
                            $translation->entity,
                            $translation->entity_id
                        );
                        return [$key => $translation];
                    });
            });
    }

    public static function getTranslation(
        string $key,
        string $group = 'default',
        ?string $entity = null,
        ?string $entityId = null,
        ?string $default = null,
        ?Language $language = null
    ): Translation {
        $entity = $entity ?? '';
        $entityId = $entityId ?? '';

        static::boot();
        $language = $language ?? Language::getCurrent();
        static::bootLanguage($language);

        $cacheKey = static::getKey($key, $group, $entity, $entityId);

        if (isset(static::$cache[$language->code][$cacheKey])) {
            return static::$cache[$language->code][$cacheKey];
        }

        if (!config('translatable.auto_create', true)) {
            $fallback = static::findFallback($cacheKey, $language->code);
            if ($fallback) {
                return $fallback;
            }

            $translation = new Translation();
            $translation->group = $group;
            $translation->key = $key;
            $translation->entity = $entity;
            $translation->entity_id = $entityId;
            $translation->language_id = $language->id;
            $translation->content = (string) $default;
            return $translation;
        }

        $translation = Translation::query()->firstOrCreate([
            'language_id' => $language->id,
            'group' => $group,
            'key' => $key,
            'entity' => $entity,
            'entity_id' => $entityId,
        ], [
            'type' => 'default',
            'content' => (string) $default,
        ]);

        if ($translation->wasRecentlyCreated) {
            TranslationCreated::dispatch($translation);
        }

        static::refresh($translation, $language);

        return $translation;
    }

    public static function getTranslationChoice(
        string $key,
        int $count,
        array $replace = [],
        string $group = 'default',
        ?string $entity = null,
        ?string $entityId = null,
        ?string $default = null,
        ?Language $language = null
    ): string {
        $translation = static::getTranslation($key, $group, $entity, $entityId, $default, $language);

        $locale = $language?->code ?? app()->getLocale();
        $line = (new MessageSelector())->choose($translation->content, $count, $locale);

        foreach ($replace as $rKey => $rValue) {
            $line = str_replace(":{$rKey}", (string) $rValue, $line);
        }

        return $line;
    }

    /**
     * @param array<array{key: string, group?: string, entity?: string, entity_id?: string, content: string}> $items
     */
    public static function setTranslations(array $items, Language $language): int
    {
        $count = 0;
        $table = config('translatable.tables.translations');

        foreach ($items as $item) {
            $entity = $item['entity'] ?? '';
            $entityId = $item['entity_id'] ?? '';
            $group = $item['group'] ?? 'default';

            $existing = Translation::query()
                ->where('language_id', $language->id)
                ->where('group', $group)
                ->where('key', $item['key'])
                ->where('entity', $entity)
                ->where('entity_id', $entityId)
                ->first();

            if ($existing) {
                if ($existing->content !== $item['content']) {
                    $old = $existing->content;
                    $existing->content = $item['content'];
                    $existing->save();
                    TranslationUpdated::dispatch($existing, $old);
                    static::refresh($existing, $language);
                    $count++;
                }
            } else {
                $translation = Translation::create([
                    'language_id' => $language->id,
                    'group' => $group,
                    'key' => $item['key'],
                    'type' => $item['type'] ?? 'default',
                    'entity' => $entity,
                    'entity_id' => $entityId,
                    'content' => $item['content'],
                ]);
                TranslationCreated::dispatch($translation);
                static::refresh($translation, $language);
                $count++;
            }
        }

        return $count;
    }

    public static function refresh(
        Translation $translation,
        ?Language $language = null
    ): void {
        $language = $language ?? Language::getCurrent();
        $key = static::getKey(
            $translation->key,
            $translation->group,
            $translation->entity,
            $translation->entity_id
        );

        static::$cache[$language->code][$key] = $translation;

        $hash = config('cache.translation_hash');
        $cacheKey = "translation_static_cache_{$language->code}_{$hash}";
        Cache::tags(['translation_static_cache'])->forget($cacheKey);
    }

    protected static function findFallback(string $cacheKey, string $currentCode): ?Translation
    {
        $fallbackCode = config('translatable.fallback_locale')
            ?? config('app.fallback_locale');

        if (!$fallbackCode || mb_strtolower($fallbackCode) === mb_strtolower($currentCode)) {
            return null;
        }

        try {
            $fallbackLang = Language::byCode($fallbackCode);
        } catch (\Throwable) {
            return null;
        }

        static::bootLanguage($fallbackLang);

        return static::$cache[$fallbackLang->code][$cacheKey] ?? null;
    }

    protected static function getKey(
        string $key,
        string $group = 'default',
        ?string $entity = null,
        ?string $entityId = null,
    ): string {
        return $group . '|' . $key . '|' . ($entity ?? '') . '|' . ($entityId ?? '');
    }
}
