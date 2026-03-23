# Laravel Translatable

A comprehensive translation package for Laravel that combines **model translations**, **UI string localization**, and **CMS content blocks** in a single, unified solution.

Translations are stored in the database with two-level caching (in-memory + Redis/Memcached), automatic record creation, fallback locale support, and full integration with Laravel's `__()` / `trans()` helpers.

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE.md)

> [README auf Deutsch](docs/README.de.md) | [README на русском](docs/README.ru.md) | [中文文档](docs/README.zh.md)

## Why This Package?

Most Laravel translation packages solve only one problem. If you need multilingual models, UI strings from the database, and CMS-managed content, you typically install 2-3 separate packages. This package provides all three in a unified architecture:

| Capability | spatie/translatable | astrotomic/translatable | **This package** |
|------------|:---:|:---:|:---:|
| Model field translations | JSON column | Separate table per model | Single `translations` table |
| UI strings (`__()` / `trans()`) | - | - | Built-in database loader |
| CMS content blocks | - | - | ContentBlockService |
| Two-level caching | - | - | In-memory + Cache::tags |
| Fallback locale chain | - | Yes | Yes |
| Query scopes | JSON where | JOIN | whereTranslation / orderBy |
| Events | - | - | Created / Updated |
| Artisan CLI tools | - | - | export / import / scan |
| Language detection middleware | - | - | DetectLanguage |
| Plural forms | - | - | tc() + MessageSelector |
| Batch operations | - | - | setTranslations / saveTranslations |
| Language management model | - | - | Full Language model |

## Requirements

- PHP 8.1+
- Laravel 11 or 12
- Cache driver with tags support (Redis, Memcached, or Array)
- MySQL 5.7+ / PostgreSQL 12+ / SQLite 3.35+

## Installation

```bash
composer require dskripchenko/laravel-translatable
```

The package uses Laravel auto-discovery. Run migrations:

```bash
php artisan migrate
```

## Quick Start

### 1. Create Languages

```php
use Dskripchenko\LaravelTranslatable\Models\Language;

Language::create(['code' => 'en', 'label' => 'English', 'is_active' => true, 'as_locale' => true]);
Language::create(['code' => 'de', 'label' => 'Deutsch', 'is_active' => true]);
Language::create(['code' => 'ru', 'label' => 'Russian', 'is_active' => true]);
```

### 2. Translate Model Fields

Add `TranslationTrait` to any Eloquent model:

```php
use Dskripchenko\LaravelTranslatable\Traits\TranslationTrait;

class Product extends Model
{
    use TranslationTrait;

    protected $fillable = ['name', 'description', 'price'];
}
```

Read and write translations:

```php
$product = Product::find(1);

// Read (uses current app locale)
$product->t('name');
$product->t('name', 'Default name');

// Read specific locale
$product->t('name', null, Language::byCode('de'));

// Write
$product->saveTranslation('name', 'Produktname', Language::byCode('de'));

// Batch write
$product->saveTranslations([
    'name' => 'Produktname',
    'description' => 'Eine Beschreibung',
], Language::byCode('de'));
```

### 3. Plural Forms

Store pluralized content using Laravel's syntax:

```php
// In the database: "One item|:count items"
$product->tc('items_label', 1);                // "One item"
$product->tc('items_label', 5, ['count' => 5]); // "5 items"
```

### 4. Query by Translations

```php
// Find products by translated name
Product::whereTranslation('name', 'Laptop')->get();
Product::whereTranslation('name', 'like', '%Laptop%', 'en')->get();

// Sort by translated field
Product::orderByTranslation('name', 'asc', 'de')->get();
```

### 5. CMS Content Blocks

Manage translatable content blocks tied to pages:

```php
use Dskripchenko\LaravelTranslatable\Services\ContentBlockService;

$cms = new ContentBlockService();

// Simple text block (auto-linked to current page)
echo $cms->inline('hero.title', 'Hero Title', 'Welcome to our site');

// With parameter substitution
echo $cms->inline('greeting', 'Greeting', 'Hello, {name}!', ['name' => $user->name]);

// Global block (not tied to a page)
echo $cms->global('site.name', 'Site Name', 'My Application');

// HTML block via output buffering
$cms->begin('footer', 'Footer HTML');
?>
<footer>Default footer content</footer>
<?php
$cms->end();
```

### 6. UI Strings from Database

Enable the database translation loader to use `__()` and `trans()` with database-stored translations:

```env
TRANSLATABLE_DATABASE_LOADER=true
```

Now `__('messages.welcome')` first checks the database, then falls back to language files. This is useful when you want administrators to edit UI strings without code deployments.

## Configuration

Publish or customize in your `config/translatable.php`:

```php
return [
    // Auto-create translations on first access (disable in production for strict control)
    'auto_create' => env('TRANSLATABLE_AUTO_CREATE', true),

    // Fallback locale when translation is missing (uses app.fallback_locale if not set)
    'fallback_locale' => env('TRANSLATABLE_FALLBACK_LOCALE'),

    // Replace Laravel's file-based translation loader with database loader
    'database_loader' => env('TRANSLATABLE_DATABASE_LOADER', false),

    // Customize table names to avoid conflicts
    'tables' => [
        'languages'          => env('TRANSLATABLE_LANGUAGES_TABLE', 'languages'),
        'translations'       => env('TRANSLATABLE_TRANSLATIONS_TABLE', 'translations'),
        'content_blocks'     => env('TRANSLATABLE_CONTENT_BLOCKS_TABLE', 'content_blocks'),
        'pages'              => env('TRANSLATABLE_PAGES_TABLE', 'pages'),
        'page_content_block' => env('TRANSLATABLE_PAGE_CONTENT_BLOCK_TABLE', 'page_content_block'),
    ],
];
```

Additionally, set cache parameters in your application config:

```php
// config/cache.php
'translation_hash' => env('TRANSLATION_CACHE_HASH', 'v1'), // Change on deploy to bust cache
'translation_ttl'  => env('TRANSLATION_CACHE_TTL', 3600),  // Cache lifetime in seconds
```

## Middleware

Detect user language automatically from URL segments, cookies, or `Accept-Language` headers:

```php
// In routes or middleware groups
Route::middleware('translatable.detect')->group(function () {
    // Language is auto-detected and set
});
```

Detection priority: route parameter `{locale}` > first URL segment > `locale` cookie > `Accept-Language` header.

## Artisan Commands

```bash
# Export all translations for a locale
php artisan translatable:export en --output=translations-en.json

# Import translations from JSON
php artisan translatable:import translations-de.json --locale=de

# Preview without writing
php artisan translatable:import translations-de.json --dry-run

# Scan source code for translation calls
php artisan translatable:scan --path=app,resources
```

## Events

Listen for translation changes to trigger cache invalidation, webhooks, or audit logging:

```php
use Dskripchenko\LaravelTranslatable\Events\TranslationCreated;
use Dskripchenko\LaravelTranslatable\Events\TranslationUpdated;

// In EventServiceProvider or listener
Event::listen(TranslationCreated::class, function ($event) {
    Log::info("Translation created: {$event->translation->key}");
});

Event::listen(TranslationUpdated::class, function ($event) {
    Log::info("Translation updated: {$event->translation->key}, old: {$event->oldContent}");
});
```

## Architecture

The package stores all translations in a single `translations` table with polymorphic binding via `entity` / `entity_id` fields. This means one centralized table serves model translations, UI strings, and CMS blocks:

```
languages          translations              content_blocks
+----+------+      +----+-----------+------+  +----+-----+---------+
| id | code |  1-* | id | lang_id   | key  |  | id | key | content |
|  1 | en   |------| .. | entity    | ...  |  +----+-----+---------+
|  2 | de   |      |    | entity_id |      |        |
+----+------+      |    | content   |      |        | M2M
                   +----+-----------+------+  pages--+
```

**Caching** is two-level: in-memory static cache (per-request) + persistent cache via `Cache::tags` (Redis/Memcached). Translations are loaded lazily per language on first access.

For detailed architecture documentation, see [docs/architecture.md](docs/architecture.md) ([ru](docs/architecture.ru.md) | [de](docs/architecture.de.md) | [zh](docs/architecture.zh.md)).

## When to Choose This Package

| Your Situation | Recommendation |
|----------------|---------------|
| Small project, 2-3 translatable models, minimal setup | Consider [spatie/laravel-translatable](https://packagist.org/packages/spatie/laravel-translatable) -- JSON column approach with zero overhead |
| Complex queries on translated fields, strong typing | Consider [astrotomic/laravel-translatable](https://packagist.org/packages/astrotomic/laravel-translatable) -- normalized per-model tables |
| Full localization: models + UI + CMS + language management | **This package** -- unified solution |
| Only UI strings in database | Consider [spatie/laravel-translation-loader](https://packagist.org/packages/spatie/laravel-translation-loader) |

For a detailed comparison, see [docs/competitive-analysis.md](docs/competitive-analysis.md) ([ru](docs/competitive-analysis.ru.md) | [de](docs/competitive-analysis.de.md) | [zh](docs/competitive-analysis.zh.md)).

## Testing

```bash
composer install
vendor/bin/pest                            # All 124 tests
vendor/bin/pest --filter="test name"       # Single test
vendor/bin/pest --coverage                 # With coverage (requires pcov/xdebug)
```

Test coverage: **97.4%** (124 tests, 235 assertions).

## License

MIT License. See [LICENSE.md](LICENSE.md) for details.
