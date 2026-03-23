<?php

declare(strict_types=1);

use Dskripchenko\LaravelTranslatable\Models\Translation;

it('exports translations to json', function () {
    $langs = $this->createLanguages();

    Translation::create([
        'language_id' => $langs['en']->id,
        'group' => 'default',
        'key' => 'hello',
        'entity' => '',
        'entity_id' => '',
        'type' => 'default',
        'content' => 'Hello',
    ]);

    $output = tempnam(sys_get_temp_dir(), 'export_');

    $this->artisan('translatable:export', ['locale' => 'en', '--output' => $output])
        ->assertSuccessful();

    $data = json_decode(file_get_contents($output), true);

    expect($data['locale'])->toBe('en')
        ->and($data['count'])->toBe(1)
        ->and($data['translations'][0]['key'])->toBe('hello')
        ->and($data['translations'][0]['content'])->toBe('Hello');

    unlink($output);
});

it('export fails for unknown locale', function () {
    $this->createLanguages();

    $this->artisan('translatable:export', ['locale' => 'xx'])
        ->assertFailed();
});

it('imports translations from json', function () {
    $langs = $this->createLanguages();

    $data = [
        'locale' => 'en',
        'translations' => [
            ['group' => 'default', 'key' => 'imported', 'content' => 'Imported value'],
            ['group' => 'ui', 'key' => 'button', 'content' => 'Click me'],
        ],
    ];

    $file = tempnam(sys_get_temp_dir(), 'import_');
    file_put_contents($file, json_encode($data));

    $this->artisan('translatable:import', ['file' => $file])
        ->assertSuccessful();

    expect(Translation::count())->toBe(2)
        ->and(Translation::where('key', 'imported')->first()->content)->toBe('Imported value');

    unlink($file);
});

it('import updates existing translations', function () {
    $langs = $this->createLanguages();

    Translation::create([
        'language_id' => $langs['en']->id,
        'group' => 'default',
        'key' => 'existing',
        'entity' => '',
        'entity_id' => '',
        'type' => 'default',
        'content' => 'Old value',
    ]);

    $data = [
        'locale' => 'en',
        'translations' => [
            ['group' => 'default', 'key' => 'existing', 'content' => 'New value'],
        ],
    ];

    $file = tempnam(sys_get_temp_dir(), 'import_');
    file_put_contents($file, json_encode($data));

    $this->artisan('translatable:import', ['file' => $file])
        ->assertSuccessful();

    expect(Translation::count())->toBe(1)
        ->and(Translation::first()->content)->toBe('New value');

    unlink($file);
});

it('import dry-run does not write', function () {
    $langs = $this->createLanguages();

    $data = [
        'locale' => 'en',
        'translations' => [
            ['group' => 'default', 'key' => 'dry', 'content' => 'Dry run'],
        ],
    ];

    $file = tempnam(sys_get_temp_dir(), 'import_');
    file_put_contents($file, json_encode($data));

    $this->artisan('translatable:import', ['file' => $file, '--dry-run' => true])
        ->assertSuccessful();

    expect(Translation::count())->toBe(0);

    unlink($file);
});

it('scan command runs without error', function () {
    $this->artisan('translatable:scan', ['--path' => 'src'])
        ->assertSuccessful();
});

// --- Export edge cases ---

it('exports to stdout when no output flag', function () {
    $langs = $this->createLanguages();

    Translation::create([
        'language_id' => $langs['en']->id,
        'group' => 'default',
        'key' => 'test',
        'entity' => '',
        'entity_id' => '',
        'type' => 'default',
        'content' => 'Test',
    ]);

    $this->artisan('translatable:export', ['locale' => 'en'])
        ->assertSuccessful()
        ->expectsOutputToContain('"key": "test"');
});

// --- Import edge cases ---

it('import fails for missing file', function () {
    $this->artisan('translatable:import', ['file' => '/nonexistent/file.json'])
        ->assertFailed();
});

it('import fails for invalid json', function () {
    $file = tempnam(sys_get_temp_dir(), 'import_');
    file_put_contents($file, '{"bad": true}');

    $this->artisan('translatable:import', ['file' => $file])
        ->assertFailed();

    unlink($file);
});

it('import fails when locale not specified', function () {
    $file = tempnam(sys_get_temp_dir(), 'import_');
    file_put_contents($file, json_encode(['translations' => []]));

    $this->artisan('translatable:import', ['file' => $file])
        ->assertFailed();

    unlink($file);
});

it('import fails for unknown locale', function () {
    $this->createLanguages();

    $file = tempnam(sys_get_temp_dir(), 'import_');
    file_put_contents($file, json_encode(['locale' => 'xx', 'translations' => []]));

    $this->artisan('translatable:import', ['file' => $file])
        ->assertFailed();

    unlink($file);
});

it('import skips rows with missing required fields', function () {
    $langs = $this->createLanguages();

    $data = [
        'locale' => 'en',
        'translations' => [
            ['group' => 'default', 'key' => 'valid', 'content' => 'OK'],
            ['group' => 'default'],
            ['key' => 'no_group'],
        ],
    ];

    $file = tempnam(sys_get_temp_dir(), 'import_');
    file_put_contents($file, json_encode($data));

    $this->artisan('translatable:import', ['file' => $file])
        ->assertSuccessful();

    expect(Translation::count())->toBe(1);

    unlink($file);
});

it('import uses --locale override', function () {
    $langs = $this->createLanguages();

    $data = [
        'locale' => 'en',
        'translations' => [
            ['group' => 'default', 'key' => 'overridden', 'content' => 'Russian text'],
        ],
    ];

    $file = tempnam(sys_get_temp_dir(), 'import_');
    file_put_contents($file, json_encode($data));

    $this->artisan('translatable:import', ['file' => $file, '--locale' => 'ru'])
        ->assertSuccessful();

    $t = Translation::first();
    expect($t->language_id)->toBe($langs['ru']->id);

    unlink($file);
});

// --- Scan with actual files ---

it('scan finds translation calls in php files', function () {
    $dir = base_path('_scan_test');
    @mkdir($dir, 0777, true);

    file_put_contents($dir . '/test.php', implode("\n", [
        '<?php',
        '$model->t(\'name\');',
        '$model->tc(\'items\', 5);',
        '$service->inline(\'hero.title\', \'Title\');',
    ]));

    $this->artisan('translatable:scan', ['--path' => '_scan_test'])
        ->assertSuccessful()
        ->expectsOutputToContain('name')
        ->expectsOutputToContain('items')
        ->expectsOutputToContain('hero.title');

    unlink($dir . '/test.php');
    rmdir($dir);
});

it('scan warns about missing directories', function () {
    $this->artisan('translatable:scan', ['--path' => 'nonexistent_dir_xyz'])
        ->assertSuccessful();
});
