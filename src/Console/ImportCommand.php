<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelTranslatable\Console;

use Dskripchenko\LaravelTranslatable\Models\Language;
use Dskripchenko\LaravelTranslatable\Models\Translation;
use Dskripchenko\LaravelTranslatable\Services\TranslationService;
use Illuminate\Console\Command;

class ImportCommand extends Command
{
    protected $signature = 'translatable:import
        {file : JSON file path to import}
        {--locale= : Override locale from file}
        {--dry-run : Show what would be imported without writing}';

    protected $description = 'Import translations from a JSON file';

    public function handle(): int
    {
        $path = $this->argument('file');

        if (!file_exists($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        $data = json_decode(file_get_contents($path), true);

        if (!$data || !isset($data['translations'])) {
            $this->error('Invalid JSON format. Expected {"translations": [...]}');
            return self::FAILURE;
        }

        $code = $this->option('locale') ?? ($data['locale'] ?? null);

        if (!$code) {
            $this->error('Locale not specified. Use --locale or include "locale" in JSON.');
            return self::FAILURE;
        }

        try {
            $language = Language::byCode($code);
        } catch (\Throwable) {
            $this->error("Language '{$code}' not found.");
            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($data['translations'] as $row) {
            if (!isset($row['group'], $row['key'], $row['content'])) {
                $skipped++;
                continue;
            }

            $entity = $row['entity'] ?? '';
            $entityId = $row['entity_id'] ?? '';

            if ($this->option('dry-run')) {
                $this->line("[DRY-RUN] {$row['group']}.{$row['key']} ({$entity}#{$entityId})");
                $created++;
                continue;
            }

            $translation = Translation::query()
                ->where('language_id', $language->id)
                ->where('group', $row['group'])
                ->where('key', $row['key'])
                ->where('entity', $entity)
                ->where('entity_id', $entityId)
                ->first();

            if ($translation) {
                if ($translation->content !== $row['content']) {
                    $translation->content = $row['content'];
                    $translation->save();
                    TranslationService::refresh($translation, $language);
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                $translation = Translation::create([
                    'language_id' => $language->id,
                    'group' => $row['group'],
                    'key' => $row['key'],
                    'type' => $row['type'] ?? 'default',
                    'entity' => $entity,
                    'entity_id' => $entityId,
                    'content' => $row['content'],
                ]);
                TranslationService::refresh($translation, $language);
                $created++;
            }
        }

        $this->info("Import complete: {$created} created, {$updated} updated, {$skipped} skipped.");

        return self::SUCCESS;
    }
}
