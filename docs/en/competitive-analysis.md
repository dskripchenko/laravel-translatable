# Competitive Analysis

> Comparison of Laravel translation packages as of March 2026.

## Approaches to Model Translation

| Approach | Storage | Package | Downloads |
|----------|---------|---------|-----------|
| **JSON column** | JSON in the model's own table | spatie/laravel-translatable | ~22.8M |
| **Separate table per model** | `{model}_translations` table | astrotomic/laravel-translatable | ~7.6M |
| **Single translations table** | One `translations` table, polymorphic | **dskripchenko/laravel-translatable** | -- |

Additionally, packages exist for **UI strings only** (not models): spatie/laravel-translation-loader (~2.8M), joedixon/laravel-translation (~500K).

## Detailed Comparison

### spatie/laravel-translatable

**Downloads:** ~22.8M | **Stars:** 2426 | **Laravel:** 11-13

Stores translations as JSON in a column on the model's own table. Simplest setup -- one trait, one JSON column, no extra tables.

**Strengths:** zero query overhead (translations load with the model), `whereLocale()` / `whereJsonContainsLocale()` scopes, massive community.

**Limitations:** JSON is not efficiently indexable for full-text search, no normalization (all languages in one field), no centralized translation management, no caching layer, every model query loads all languages.

### astrotomic/laravel-translatable

**Downloads:** ~7.6M | **Stars:** 1392 | **Laravel:** 9-12

Creates a separate `{model}_translations` table for each translatable model. Each translated field gets its own typed column.

**Strengths:** full normalization with standard indexes, typed columns (string, text, etc.), fallback locale chain, JOIN-based eager loading via `withTranslation()`.

**Limitations:** table explosion (each translatable model = +1 table + 1 PHP class), additional migrations for every field change, no built-in caching, more complex initial setup, less actively maintained (fork of abandoned dimsav).

### lexi-translate

**Downloads:** <10K | **Stars:** ~30 | **Laravel:** 10-11

Uses a single `translations` table with morph relationships -- architecturally similar to dskripchenko.

**Strengths:** single table, standard morph pattern, built-in caching, fallback locale.

**Limitations:** young project, does not support Laravel 12+, limited documentation.

### spatie/laravel-translation-loader

**Downloads:** ~2.8M | **Stars:** 835 | **Laravel:** 6-13

Replaces Laravel's file-based translation loader with a database backend. Works with `__()`, `trans()`, `@lang()` without code changes.

**Strengths:** full compatibility with Laravel's translation helpers, DB overrides file translations, extensible (custom YAML/CSV providers).

**Limitations:** UI strings only -- no model translations, no language management.

### joedixon/laravel-translation

**Downloads:** ~500K | **Stars:** ~600

Complete translation management system with web UI, scanner, and database driver.

**Strengths:** built-in web editor, missing translation scanner, file + database drivers, artisan commands.

**Limitations:** UI strings only, less active maintenance, heavier dependencies.

## Feature Matrix

| Feature | spatie | astrotomic | lexi | spatie-loader | joedixon | **dskripchenko** |
|---------|:------:|:----------:|:----:|:------------:|:--------:|:----------------:|
| Model field translations | JSON | Per-model table | Morph | -- | -- | **Morph** |
| UI strings `__()` / `trans()` | -- | -- | -- | Yes | Yes | **Yes** |
| Fallback locale | -- | Yes | Yes | -- | -- | **Yes** |
| Caching | -- | -- | Yes | -- | -- | **Two-level** |
| Query scopes | JSON where | JOIN | -- | -- | -- | **whereTranslation / orderBy** |
| Plural forms | -- | -- | -- | Laravel native | Laravel native | **tc() + MessageSelector** |
| Events | -- | -- | -- | -- | -- | **Created / Updated** |
| Batch operations | -- | -- | Yes | -- | -- | **setTranslations** |
| Artisan CLI | -- | -- | -- | -- | Yes | **export / import / scan** |
| Middleware | -- | -- | -- | -- | -- | **DetectLanguage** |
| Language management | -- | -- | -- | -- | -- | **Language model** |
| CMS content blocks | -- | -- | -- | -- | -- | **ContentBlockService** |
| Page-block binding | -- | -- | -- | -- | -- | **Page <-> ContentBlock** |
| Parameter substitution | -- | -- | -- | -- | -- | **{placeholder} in inline()** |
| Output buffering | -- | -- | -- | -- | -- | **begin() / end()** |
| Auto-create on access | -- | -- | -- | -- | -- | **auto_create config** |
| Configurable table names | -- | -- | -- | -- | -- | **env + config** |
| Route pattern helper | -- | -- | -- | -- | -- | **getRouteGroupPattern()** |

## When to Choose Which Package

| Situation | Recommendation |
|-----------|---------------|
| Small project, 2-3 translatable models, minimal setup needed | **spatie/laravel-translatable** -- JSON column with zero overhead. The simplest approach that works well when translation data is small and you don't need centralized management. |
| Large project with complex queries on translated fields, strong typing requirements | **astrotomic/laravel-translatable** -- normalized per-model tables give you full SQL power over translated columns with proper types. |
| Full localization stack: models + UI strings + CMS + language management + caching | **dskripchenko/laravel-translatable** -- one package covers all layers. Particularly strong when you need auto-creation, content blocks, and don't want to integrate multiple packages. |
| Only UI strings from database, existing codebase with `__()` calls | **spatie/laravel-translation-loader** -- drop-in replacement for file-based translations with zero code changes. |
| Need a visual translation editor for non-technical team members | **joedixon/laravel-translation** -- comes with a built-in web interface. |
| Combining packages | You can use **dskripchenko** alongside **spatie/translatable** if some models benefit from JSON storage (e.g., simple config-like fields) while others need the centralized translation table. The packages do not conflict. |

Sources:
- [spatie/laravel-translatable -- Packagist](https://packagist.org/packages/spatie/laravel-translatable)
- [astrotomic/laravel-translatable -- Packagist](https://packagist.org/packages/astrotomic/laravel-translatable)
- [spatie/laravel-translation-loader -- Packagist](https://packagist.org/packages/spatie/laravel-translation-loader)
- [lexi-translate -- GitHub](https://github.com/omaralalwi/lexi-translate)
- [joedixon/laravel-translation -- GitHub](https://github.com/joedixon/laravel-translation)
