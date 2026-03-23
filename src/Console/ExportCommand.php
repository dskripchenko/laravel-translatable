<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelTranslatable\Console;

use Dskripchenko\LaravelTranslatable\Models\Language;
use Dskripchenko\LaravelTranslatable\Models\Translation;
use Illuminate\Console\Command;

class ExportCommand extends Command
{
    protected $signature = 'translatable:export
        {locale : Language code to export}
        {--output= : Output file path (default: stdout)}';

    protected $description = 'Export translations for a locale to JSON';

    public function handle(): int
    {
        $code = $this->argument('locale');

        try {
            $language = Language::byCode($code);
        } catch (\Throwable) {
            $this->error("Language '{$code}' not found.");
            return self::FAILURE;
        }

        $translations = Translation::query()
            ->where('language_id', $language->id)
            ->get()
            ->map(fn (Translation $t) => [
                'group' => $t->group,
                'key' => $t->key,
                'entity' => $t->entity,
                'entity_id' => $t->entity_id,
                'type' => $t->type,
                'content' => $t->content,
            ])
            ->values()
            ->toArray();

        $json = json_encode([
            'locale' => $language->code,
            'exported_at' => now()->toIso8601String(),
            'count' => count($translations),
            'translations' => $translations,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $output = $this->option('output');

        if ($output) {
            file_put_contents($output, $json);
            $this->info("Exported " . count($translations) . " translations to {$output}");
        } else {
            $this->line($json);
        }

        return self::SUCCESS;
    }
}
