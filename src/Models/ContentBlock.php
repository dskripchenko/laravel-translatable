<?php

namespace Dskripchenko\LaravelTranslatable\Models;

use Carbon\Carbon;
use Dskripchenko\LaravelTranslatable\Traits\TranslationTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property string $key
 * @property string $description
 * @property string $type
 * @property string $content
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ContentBlock extends Model
{
    use TranslationTrait;

    protected $fillable = ['key', 'description', 'type', 'content'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * @return string
     */
    public function getTable(): string
    {
        return (string) config('translatable.tables.content_blocks', parent::getTable());
    }
}
