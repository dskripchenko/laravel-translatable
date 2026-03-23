<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelTranslatable\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property integer $page_id
 * @property integer $content_block_id
 */
class PageContentBlock extends Model
{
    /**
     * @var string
     */
    protected $table = 'page_content_block';

    public $timestamps = false;

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
