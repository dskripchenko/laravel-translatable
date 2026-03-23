<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelTranslatable\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ScanCommand extends Command
{
    protected $signature = 'translatable:scan
        {--path=app,resources : Comma-separated directories to scan}';

    protected $description = 'Scan source files for translation calls and list keys';

    protected array $patterns = [
        '/->t\(\s*[\'"]([^\'"]+)[\'"]\s*[,)]/' => 'TranslationTrait::t',
        '/->tc\(\s*[\'"]([^\'"]+)[\'"]\s*,/' => 'TranslationTrait::tc',
        '/->inline\(\s*[\'"]([^\'"]+)[\'"]\s*,/' => 'ContentBlockService::inline',
        '/->global\(\s*[\'"]([^\'"]+)[\'"]\s*,/' => 'ContentBlockService::global',
    ];

    public function handle(): int
    {
        $dirs = explode(',', $this->option('path'));
        $basePath = base_path();
        $found = [];

        foreach ($dirs as $dir) {
            $path = $basePath . '/' . trim($dir);

            if (!is_dir($path)) {
                $this->warn("Directory not found: {$path}");
                continue;
            }

            $files = File::allFiles($path);

            foreach ($files as $file) {
                if (!in_array($file->getExtension(), ['php', 'blade.php'])) {
                    $ext = $file->getExtension();
                    if ($ext !== 'php') {
                        continue;
                    }
                }

                $content = $file->getContents();
                $relativePath = str_replace($basePath . '/', '', $file->getPathname());

                foreach ($this->patterns as $pattern => $source) {
                    preg_match_all($pattern, $content, $matches);

                    foreach ($matches[1] as $key) {
                        $found[] = [
                            'key' => $key,
                            'source' => $source,
                            'file' => $relativePath,
                        ];
                    }
                }
            }
        }

        if (empty($found)) {
            $this->info('No translation calls found.');
            return self::SUCCESS;
        }

        $uniqueKeys = collect($found)->unique('key')->sortBy('key');

        $this->table(
            ['Key', 'Source', 'File'],
            $uniqueKeys->map(fn ($item) => [$item['key'], $item['source'], $item['file']])->toArray()
        );

        $this->info(count($found) . ' calls found, ' . $uniqueKeys->count() . ' unique keys.');

        return self::SUCCESS;
    }
}
