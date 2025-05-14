<?php

namespace LaravelTranslatable\Services;

use App\Models\Language;
use App\Models\Translation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class TranslationService
{
    public static array | null $cache = null;

    public static function boot(): void
    {
        if (!is_null(static::$cache)) {
            return;
        }
        static::$cache = Language::runForAllLanguages(
            static function (Language $language) {
                $hash = config('cache.translation_hash');
                $cacheKey = "translation_static_cache_{$language->code}_{$hash}";
                $ttl  = config('cache.translation_ttl');
                $data = Cache::tags(['translation_static_cache'])
                    ->remember($cacheKey, $ttl, function () use ($language) {
                        return Translation::query()
                            ->where('language_id', $language->id)
                            ->get()->mapWithKeys(
                                function (Translation $translation) {
                                    $key = static::getKey(
                                        $translation->key,
                                        $translation->group,
                                        $translation->entity,
                                        $translation->entity_id
                                    );
                                    return [$key => $translation];
                                }
                            );
                    });
                return [$language->code => $data];
            }
        );
    }

    /**
     * @param string $key
     * @param string $group
     * @param string|null $entity
     * @param string|null $entityId
     * @param string|null $default
     * @param Language|null $language
     * @return Translation
     */
    public static function getTranslation(
        string $key,
        string $group = 'default',
        ?string $entity = null,
        ?string $entityId = null,
        ?string $default = null,
        ?Language $language = null
    ): Translation {

        static::boot();
        $language = $language ?? Language::getCurrent();
        $cacheKey = static::getKey($key, $group, $entity, $entityId);
        if (Arr::has(static::$cache, "{$language->code}.{$cacheKey}")) {
            return Arr::get(
                static::$cache,
                "{$language->code}.{$cacheKey}"
            );
        }

        $translation = new Translation();
        $translation->group = $group;
        $translation->key = $key;
        $translation->entity = $entity;
        $translation->entity_id = $entityId;
        $translation->language_id = $language->id;
        $translation->content = $default;
        $translation->save();

        static::refresh($translation, $language);

        return $translation;
    }

    /**
     * @param Translation $translation
     * @param Language|null $language
     */
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
        Cache::tags('translation_static_cache')->flush();
    }

    /**
     * @param string $key
     * @param string $group
     * @param string|null $entity
     * @param string|null $entityId
     * @return string
     */
    protected static function getKey(
        string $key,
        string $group = 'default',
        ?string $entity = null,
        ?string $entityId = null,
    ): string {
        $key = implode('_', [
            $group,
            $key,
            $entity,
            $entityId,
        ]);
        return md5($key);
    }
}
