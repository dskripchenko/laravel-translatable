<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelTranslatable\Traits;

use Dskripchenko\LaravelTranslatable\Events\TranslationUpdated;
use Dskripchenko\LaravelTranslatable\Models\Language;
use Dskripchenko\LaravelTranslatable\Models\Translation;
use Dskripchenko\LaravelTranslatable\Services\TranslationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait TranslationTrait
{
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
            (string) $this->getKey(),
            $this->getAttribute($field) ?? $default,
            $language
        )->content;
    }

    public function tc(
        string $field,
        int $count,
        array $replace = [],
        ?Language $language = null
    ): string {
        /** @var Model $this */
        return TranslationService::getTranslationChoice(
            $field,
            $count,
            $replace,
            'field',
            static::class,
            (string) $this->getKey(),
            $this->getAttribute($field),
            $language
        );
    }

    public function saveTranslation(
        string $field,
        ?string $value,
        ?Language $language = null
    ): void {
        /** @var Model $this */
        $translation = TranslationService::getTranslation(
            $field,
            'field',
            static::class,
            (string) $this->getKey(),
            (string) $value,
            $language
        );

        if ($translation->content !== (string) $value) {
            $old = $translation->content;
            $translation->content = (string) $value;
            $translation->save();
            TranslationService::refresh($translation, $language);
            TranslationUpdated::dispatch($translation, $old);
        }

        $this->touch();
    }

    /**
     * @param array<string, string> $fieldValues ['name' => 'Product', 'description' => 'Desc']
     */
    public function saveTranslations(array $fieldValues, ?Language $language = null): void
    {
        /** @var Model $this */
        $items = [];
        foreach ($fieldValues as $field => $value) {
            $items[] = [
                'key' => $field,
                'group' => 'field',
                'entity' => static::class,
                'entity_id' => (string) $this->getKey(),
                'content' => (string) $value,
            ];
        }

        $language = $language ?? Language::getCurrent();
        TranslationService::setTranslations($items, $language);
        $this->touch();
    }

    public function scopeWhereTranslation(
        Builder $query,
        string $field,
        mixed $operator,
        mixed $value = null,
        ?string $locale = null
    ): Builder {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $locale = $locale ?? app()->getLocale();
        $language = Language::byCode($locale);

        $entityIds = Translation::query()
            ->where('entity', static::class)
            ->where('key', $field)
            ->where('group', 'field')
            ->where('language_id', $language->id)
            ->where('content', $operator, $value)
            ->pluck('entity_id');

        return $query->whereIn(
            $query->getModel()->getQualifiedKeyName(),
            $entityIds
        );
    }

    public function scopeOrderByTranslation(
        Builder $query,
        string $field,
        string $direction = 'asc',
        ?string $locale = null
    ): Builder {
        $locale = $locale ?? app()->getLocale();
        $language = Language::byCode($locale);
        $table = config('translatable.tables.translations');

        return $query
            ->select($query->getModel()->qualifyColumn('*'))
            ->leftJoin("{$table} as _tsort", function ($join) use ($field, $language, $query) {
                $join->whereRaw('_tsort.entity_id = ' . static::castToString(
                    $query->getModel()->getQualifiedKeyName()
                ))
                    ->where('_tsort.entity', static::class)
                    ->where('_tsort.key', $field)
                    ->where('_tsort.group', 'field')
                    ->where('_tsort.language_id', $language->id);
            })
            ->orderBy('_tsort.content', $direction);
    }

    protected static function castToString(string $column): string
    {
        return match (\DB::getDriverName()) {
            'mysql' => "CAST({$column} AS CHAR(64))",
            'pgsql' => "CAST({$column} AS VARCHAR(64))",
            default => "CAST({$column} AS TEXT)",
        };
    }
}
