# Architecture

> dskripchenko/laravel-translatable -- technical reference

## Overview

The package provides database-backed translations for Laravel with three functional layers:

1. **Model translations** -- any Eloquent model gets multilingual fields via `TranslationTrait`
2. **UI string loader** -- `DatabaseTranslationLoader` replaces Laravel's file-based `__()` / `trans()`
3. **CMS content blocks** -- `ContentBlockService` manages translatable blocks tied to pages

All layers share a single `translations` table with polymorphic binding.

## File Structure

```
src/
├── Console/
│   ├── ExportCommand.php            # translatable:export {locale}
│   ├── ImportCommand.php            # translatable:import {file}
│   └── ScanCommand.php             # translatable:scan --path=...
├── Events/
│   ├── TranslationCreated.php       # Dispatched on first access (auto_create)
│   └── TranslationUpdated.php       # Dispatched on content change (with oldContent)
├── Http/Middleware/
│   └── DetectLanguage.php           # Auto-detect locale from URL/cookie/header
├── Loaders/
│   └── DatabaseTranslationLoader.php  # Decorates FileLoader, overlays DB translations
├── Models/
│   ├── Language.php                 # Languages with static cache + resetStaticCache()
│   ├── Translation.php              # Translation records (polymorphic via entity/entity_id)
│   ├── ContentBlock.php             # CMS blocks with TranslationTrait
│   ├── Page.php                     # Pages (auto-created by URI), M2M with ContentBlock
│   └── PageContentBlock.php         # Pivot model (no timestamps)
├── Providers/
│   └── TranslatableServiceProvider.php  # Migrations, config, commands, loader, middleware alias
├── Services/
│   ├── TranslationService.php       # Core: cache, getTranslation, fallback, batch, plural
│   └── ContentBlockService.php      # CMS: inline/global/begin-end, page binding
└── Traits/
    └── TranslationTrait.php         # t(), tc(), saveTranslation(s), scopes

config/translatable.php              # auto_create, fallback_locale, database_loader, tables
databases/migrations/                # 5 tables (all names configurable via env)
tests/Feature/                       # 124 tests, 97.4% coverage
```

## Database Schema

All table names are configurable via `config/translatable.php` and `TRANSLATABLE_*_TABLE` env variables.

```
┌──────────────┐         ┌───────────────────────────────────┐
│  languages   │ 1────*  │          translations              │
├──────────────┤         ├───────────────────────────────────┤
│ id           │         │ id                                │
│ code    idx  │         │ language_id  FK → languages       │
│ label        │         │ group  (128) idx                  │
│ is_active    │         │ key    (128) idx                  │
│ as_locale    │         │ type   default='default'          │
│ timestamps   │         │ entity (128) default=''           │
│ soft_deletes │         │ entity_id (64) default=''         │
└──────────────┘         │ content      text                 │
                         │ timestamps                        │
                         ├───────────────────────────────────┤
                         │ UNIQUE(language_id, group, key,   │
                         │        entity, entity_id)         │
                         │ INDEX(entity_id, entity)          │
                         │ FK language_id CASCADE            │
                         └───────────────────────────────────┘

┌──────────────┐         ┌─────────────────────┐         ┌──────────────┐
│    pages     │ *────*  │ page_content_block   │  *────1 │content_blocks│
├──────────────┤         ├─────────────────────┤         ├──────────────┤
│ id           │         │ id                  │         │ id           │
│ name   null  │         │ page_id        FK   │         │ key   unique │
│ uri    idx   │         │ content_block_id FK │         │ description  │
│ timestamps   │         └─────────────────────┘         │ type ='text' │
│ soft_deletes │         UNIQUE(page_id,                  │ content text │
└──────────────┘           content_block_id)              │ timestamps   │
                         FK cascade delete/update         └──────────────┘
```

### How `translations` rows are used

| Use case | group | entity | entity_id | Example |
|----------|-------|--------|-----------|---------|
| Model field | `'field'` | `App\Models\Product` | `'42'` | `$product->t('name')` |
| UI string | `'messages'` | `''` | `''` | `__('messages.welcome')` |
| JSON string | `'*'` | `''` | `''` | `__('Welcome')` |
| CMS block | `'field'` | `...ContentBlock` | `'7'` | `$cms->inline('hero.title', ...)` |
| General | `'default'` | `''` | `''` | `TranslationService::getTranslation('key')` |

## Component Interaction

```
Request
  │
  ├─[Middleware: DetectLanguage]──→ app()->setLocale()
  │
  ├─[View: __('messages.hello')]
  │     └─→ DatabaseTranslationLoader
  │           ├─ FileLoader::load()        ← file translations
  │           └─ Translation::where(group='messages', entity='')  ← DB overlay
  │
  ├─[Controller: $product->t('name')]
  │     └─→ TranslationTrait::t()
  │           └─→ TranslationService::getTranslation()
  │                 ├─ boot() → bootLanguage()     ← lazy load per language
  │                 ├─ $cache[lang][key] hit?       ← level 1: in-memory
  │                 ├─ Cache::tags()->remember()    ← level 2: Redis/Memcached
  │                 ├─ findFallback()               ← try fallback locale
  │                 └─ firstOrCreate()              ← auto-create if enabled
  │                       └─→ TranslationCreated event
  │
  └─[View: $cms->inline('hero.title', ...)]
        └─→ ContentBlockService::inline()
              ├─ get() → ContentBlock::firstOrCreate()
              ├─ getCurrentPage() → Page::firstOrCreate(uri)
              ├─ Page::link(block)
              ├─ $block->t('content')  → TranslationService
              └─ str_replace({placeholders})
```

## Caching Strategy

### Two-level cache

| Level | Storage | Lifetime | Scope |
|-------|---------|----------|-------|
| 1 | `TranslationService::$cache` (static array) | Single request | Per-process |
| 2 | `Cache::tags(['translation_static_cache'])` | `config('cache.translation_ttl')` | Shared (Redis) |

### Cache key format

```
translation_static_cache_{language_code}_{hash}
```

Where `hash` = `config('cache.translation_hash')`. Change this value on deploy to bust the entire cache.

### Invalidation

- `TranslationService::refresh()` -- updates in-memory cache + `Cache::forget()` for the specific language key
- `Language::resetStaticCache()` -- clears Language model caches (for long-running processes)
- `TranslationService::$cache = null` -- resets in-memory translation cache
- `ContentBlockService::$cache = null` -- resets content block cache

### Lazy loading

`boot()` initializes an empty `$cache` array. `bootLanguage($language)` loads translations for a specific language only when first accessed. Unused languages are never loaded.

## Fallback Chain

When `auto_create` is disabled and a translation is missing:

```
Requested language (e.g. 'fr')
    │ not found
    ▼
Fallback language (config: translatable.fallback_locale or app.fallback_locale)
    │ not found
    ▼
Default value (passed as parameter to t() or getTranslation())
```

When `auto_create` is enabled, a new record is created in the requested language with the default value. Fallback is not consulted -- the new record is authoritative.

## Configuration Reference

```php
// config/translatable.php
'auto_create'     => true,   // Create translation record on first access
'fallback_locale' => null,   // null = use config('app.fallback_locale')
'database_loader' => false,  // Replace Laravel's FileLoader with DB overlay
'tables' => [...]            // All 5 table names, overridable via env

// config/cache.php (set by application)
'translation_hash' => 'v1',  // Cache version key (change on deploy)
'translation_ttl'  => 3600,  // Persistent cache TTL in seconds
```

## Cross-Database Compatibility

The package works identically on MySQL, PostgreSQL, and SQLite:

- `entity` / `entity_id` are NOT NULL with `default('')` -- avoids NULL-in-UNIQUE issues across all databases
- `Language::byCode()` uses `mb_strtolower()` for case-insensitive lookup (PostgreSQL is case-sensitive by default)
- `orderByTranslation` uses driver-specific CAST (CHAR for MySQL, VARCHAR for PostgreSQL, TEXT for SQLite)
- No raw SQL beyond standard CAST expressions
