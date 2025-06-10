<?php

namespace Dskripchenko\LaravelTranslatable\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property integer $page_id
 * @property integer $content_block_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PageContentBlock extends Model
{
    /**
     * @var string
     */
    protected $table = 'page_content_block';

    public $timestamps = null;

    /**
     * @var string[]
     */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * @var string[]
     */
    protected $fillable = [
        'page_id', 'content_block_id'
    ];

    /**
     * @return string
     */
    public function getTable(): string
    {
        return (string) config('translatable.tables.page_content_block', parent::getTable());
    }

}
