<?php

namespace Dskripchenko\LaravelTranslatable\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property integer $id
 * @property integer $language_id
 * @property string $key
 * @property string $group
 * @property string $type
 * @property string|null $entity
 * @property integer|null $entity_id
 * @property string $content
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Language $language
 */
class Translation extends Model
{
    use HasFactory;

    protected $fillable = [
        'language_id', 'group', 'key',
        'type', 'entity', 'entity_id', 'content'
    ];

    protected array $allowedSorts = [
        'id', 'language_id', 'key', 'group',
        'type', 'entity', 'entity_id',
        'content', 'updated_at'
    ];

    protected array $allowedFilters = [
        'id', 'language_id', 'key', 'group',
        'type', 'entity', 'entity_id',
        'content', 'updated_at'
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * @return string
     */
    public function getTable(): string
    {
        return (string) config('translatable.tables.translations', parent::getTable());
    }

    /**
     * @return BelongsTo
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
