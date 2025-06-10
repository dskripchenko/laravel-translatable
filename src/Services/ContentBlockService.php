<?php

namespace Dskripchenko\LaravelTranslatable\Services;

use Dskripchenko\LaravelTranslatable\Models\ContentBlock;
use Dskripchenko\LaravelTranslatable\Models\Page;

class ContentBlockService
{
    /**
     * @var array|null
     */
    public static ?array $cache = null;

    /**
     * @var Page|null
     */
    protected ?Page $page = null;

    public function __construct()
    {
        if (is_null(self::$cache)) {
            self::$cache = ContentBlock::query()
                ->get()
                ->mapWithKeys(function (ContentBlock $block) {
                    return [$block->key => $block];
                })
                ->all();
        }
    }

    /**
     * @param string $key
     * @param string $description
     * @param string|null $default
     * @param array $parameters
     * @param string $type
     * @return string
     */
    public function inline(
        string $key,
        string $description,
        ?string $default = null,
        array $parameters = [],
        string $type = 'text'
    ): string {
        if (!$default) {
            $default = $description;
        }

        $block = $this->get($key, $description, $default, $type);

        $page = $this->getCurrentPage();
        $page->link($block);

        $result = $block->t('content', $default);

        if (!empty($parameters)) {
            $search  = array_keys($parameters);
            $search  = array_map(function ($key) {
                return "{{$key}}";
            }, $search);
            $replace = array_values($parameters);
            $result  = str_replace($search, $replace, $result);
        }
        return $result;
    }

    public function global(
        string $key,
        string $description,
        ?string $default = null,
        string $type = 'text'
    ): string {
        return $this->get($key, $description, $default, $type)
            ->t('content', $default);
    }

    /**
     * @param string $key
     * @param string $description
     * @param string|null $default
     * @param string $type
     *
     * @return ContentBlock
     */
    public function get(
        string $key,
        string $description,
        ?string $default = null,
        string $type = 'text'
    ): ContentBlock {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        /**
         * @var ContentBlock $block
         */
        $block = ContentBlock::query()
            ->firstOrCreate([
                'key' => $key,
            ], [
                'description' => $description,
                'type' => $type,
                'content' => $default,
            ]);
        self::$cache[$key] = $block;

        return $block;
    }

    /**
     * @param string $key
     * @param string $description
     * @param array $parameters
     * @param string $type
     */
    public function begin(
        string $key,
        string $description,
        array $parameters = [],
        string $type = 'html'
    ): void {
        ob_start(function ($content) use ($key, $description, $parameters, $type) {
            return $this->inline($key, $description, $content, $parameters, $type);
        });
    }

    public function end(): void
    {
        ob_end_flush();
    }

    /**
     * @param string $name
     * @return void
     */
    public function page(string $name): void
    {
        $page = $this->getCurrentPage();
        $page->name = $name;
        $page->save();
    }

    /**
     * @return Page
     */
    protected function getCurrentPage(): Page
    {
        if ($this->page) {
            return $this->page;
        }

        $route = request()?->route();
        $uri = $route->uri;

        /**
         * @var Page $page
         */
        $page = Page::query()->with('blocks')->firstOrCreate([
            'uri' => $uri,
        ]);
        $this->page = $page;

        return $page;
    }
}
