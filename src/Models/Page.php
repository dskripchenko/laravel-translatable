<?php

namespace Dskripchenko\LaravelTranslatable\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property integer $id
 * @property string $name
 * @property string $uri
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $deleted_at
 * @property ContentBlock[] $blocks
 */
class Page extends Model
{
    use SoftDeletes;

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * @var string[]
     */
    protected $fillable = ['name', 'uri'];

    /**
     * @param ContentBlock $block
     * @return void
     */
    public function link(ContentBlock $block): void
    {
        $this->blocks()
            ->syncWithoutDetaching([$block->id]);
    }

    /**
     * @return MorphToMany
     */
    public function blocks(): BelongsToMany
    {
        return $this->belongsToMany(ContentBlock::class, 'page_content_block');
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        return (string) config('translatable.tables.pages', parent::getTable());
    }
}
