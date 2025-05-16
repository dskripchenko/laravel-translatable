<?php

namespace Dskripchenko\LaravelTranslatable\Traits;

use Dskripchenko\LaravelTranslatable\Models\Language;
use Dskripchenko\LaravelTranslatable\Services\TranslationService;
use Illuminate\Database\Eloquent\Model;

trait TranslationTrait
{
    /**
     * @param string $field
     * @param string|null $default
     * @param Language|null $language
     * @return string
     */
    public function t(
        string $field,
        ?string $default = null,
        ?Language $language = null
    ): string {
        /** @var Model $this */
        return TranslationService::getTranslation(
            $field,
            'field',
            static::class,
            $this->getKey(),
            $this->getAttribute($field) ?? $default,
            $language
        )->content;
    }

    /**
     * @param string $field
     * @param string $value
     * @param Language|null $language
     */
    public function saveTranslation(
        string $field,
        string $value,
        ?Language $language = null
    ): void {
        /** @var Model $this */
        $translation = TranslationService::getTranslation(
            $field,
            'field',
            static::class,
            $this->getKey(),
            $value,
            $language
        );
        $translation->content = $value;
        $translation->save();
        TranslationService::refresh($translation, $language);
        $this->touch();
    }
}
