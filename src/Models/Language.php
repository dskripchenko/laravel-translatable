<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelTranslatable\Models;

use Carbon\Carbon;
use Closure;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @property integer $id
 * @property string $code
 * @property string $label
 * @property boolean $is_active
 * @property boolean $as_locale
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $deleted_at
 *
 * @property Translation[] $translations
 */
class Language extends Model
{
    use SoftDeletes;

    protected static ?Language $defaultLanguage = null;
    protected static ?array $languagesByCode = null;

    protected $fillable = ['code', 'label', 'is_active', 'as_locale'];

    protected $casts = [
        'is_active' => 'boolean',
        'as_locale' => 'boolean',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * @return string
     */
    public function getTable(): string
    {
        return (string) config('translatable.tables.languages', parent::getTable());
    }

    /**
     * @return HasMany
     */
    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class);
    }

    /**
     * @return Language
     * @throws Exception
     */
    public static function getDefaultLanguage(): Language
    {
        if (!is_null(static::$defaultLanguage)) {
            return static::$defaultLanguage;
        }

        static::$defaultLanguage = Language::query()
            ->where('as_locale', true)
            ->first();

        $code = config('app.locale', 'en');

        if (!static::$defaultLanguage) {
            static::$defaultLanguage = Language::query()
                ->whereRaw('LOWER(code) = ?', [mb_strtolower($code)])
                ->first();
        }

        if (!static::$defaultLanguage) {
            throw new Exception(
                "Не установлен языковой пакет дефолтной локали {$code}"
            );
        }

        return static::$defaultLanguage;
    }

    /**
     * @param string|null $code
     * @return Language
     */
    public static function byCode(?string $code): Language
    {
        if (is_null(static::$languagesByCode)) {
            $rows = Language::query()->get()->all();
            static::$languagesByCode = [];
            foreach ($rows as $row) {
                static::$languagesByCode[mb_strtolower($row->code)] = $row;
            }
        }
        if (!$code) {
            $code = config('app.locale', 'en');
            foreach (static::$languagesByCode as $lang) {
                if ($lang->as_locale) {
                    $code = $lang->code;
                    break;
                }
            }
        }

        $language = static::$languagesByCode[mb_strtolower($code)] ?? null;

        if (!$language) {
            throw new NotFoundHttpException(
                "Языковой пакет не установлен"
            );
        }

        return $language;
    }

    /**
     * @return Language
     */
    public static function getCurrent(): Language
    {
        return Language::byCode(app()->getLocale());
    }

    /**
     * @return bool
     */
    public function isCurrent(): bool
    {
        return $this->id === static::getCurrent()->id;
    }


    /**
     * @param Closure $closure
     * @param Closure|null $filter
     * @return array
     */
    public static function runForAllLanguages(
        Closure $closure,
        ?Closure $filter = null
    ): array {
        $list = Language::query()
            ->when($filter, $filter)
            ->orderBy('id')
            ->get();

        $result = [];
        foreach ($list as $key => $value) {
            $assoc = $closure($value, $key);

            foreach ($assoc as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }
        return $result;
    }

    /**
     * @return string
     */
    public static function getRouteGroupPattern(): string
    {
        $languages = Language::query()
            ->get()
            ->mapWithKeys(
                function (Language $language) {
                    return [$language->id => $language->code];
                }
            )->toArray();
        $codes = trim(implode('|', $languages), '|');
        return "(|{$codes})";
    }

    /**
     * @return string|null
     */
    public function getCode(): ?string
    {
        return !$this->as_locale ? $this->code : null;
    }

    public static function resetStaticCache(): void
    {
        static::$defaultLanguage = null;
        static::$languagesByCode = null;
    }
}
