# Test Coverage Report

> dskripchenko/laravel-translatable
> Generated: 2026-03-23 | Tool: Pest 3 + pcov | Database: SQLite in-memory

## Summary

| Metric | Value |
|--------|-------|
| **Total coverage** | **97.4%** |
| Source files | 16 |
| Source lines (non-blank) | 1217 |
| Test files | 16 |
| Test lines (non-blank) | 1313 |
| Tests | 124 |
| Assertions | 235 |
| Duration | ~3.6s |
| Files at 100% | 10 / 16 |

## Coverage by Component

| Component | File | Coverage | Uncovered lines | Tests |
|-----------|------|:--------:|:---------------:|:-----:|
| **Console** | ExportCommand.php | **100.0%** | -- | 15 |
| | ImportCommand.php | **98.2%** | 85 | |
| | ScanCommand.php | **91.2%** | 42-44 | |
| **Events** | TranslationCreated.php | **100.0%** | -- | 4 |
| | TranslationUpdated.php | **100.0%** | -- | |
| **Http** | DetectLanguage.php | **95.8%** | 56 | 5 |
| **Loaders** | DatabaseTranslationLoader.php | **100.0%** | -- | 7 |
| **Models** | ContentBlock.php | **100.0%** | -- | 5 |
| | Language.php | **98.3%** | 67 | 20 |
| | Page.php | **100.0%** | -- | 7 |
| | PageContentBlock.php | **100.0%** | -- | 3 |
| | Translation.php | **100.0%** | -- | 7 |
| **Providers** | TranslatableServiceProvider.php | **87.0%** | 48-49 | 4 |
| **Services** | ContentBlockService.php | **100.0%** | -- | 12 |
| | TranslationService.php | **98.3%** | 211-212 | 23 |
| **Traits** | TranslationTrait.php | **97.5%** | 155-156 | 12 |

## Coverage by Functional Area

| Area | Tests | Coverage | Description |
|------|:-----:|:--------:|-------------|
| Model translations (t, tc, saveTranslation) | 12 | 97-100% | Trait methods, entity binding, plural forms |
| Translation caching | 6 | 98% | Boot, lazy load, refresh, persistent cache |
| Fallback locale | 4 | 98% | Chain: requested -> fallback -> default |
| CMS content blocks | 12 | 100% | inline, global, begin/end, page binding, preload |
| Query scopes | 4 | 97% | whereTranslation, orderByTranslation |
| Batch operations | 4 | 98% | setTranslations, saveTranslations |
| Database loader (__/trans) | 7 | 100% | Load, override, fallback, delegation |
| Events | 4 | 100% | TranslationCreated, TranslationUpdated |
| Middleware | 5 | 96% | Cookie, URL segment, Accept-Language, no-op |
| Artisan commands | 15 | 91-100% | Export, import (with edge cases), scan |
| Models & schema | 42 | 98-100% | All 5 models, relations, constraints, config |
| Service provider | 4 | 87% | Config, migrations, tables, deep merge |

## Uncovered Lines Analysis

### Lines that cannot be covered (by design)

| File | Lines | Reason |
|------|-------|--------|
| TranslationTrait.php | 155-156 | `castToString()` MySQL/PostgreSQL branches. Tests run on SQLite -- only `default` branch is hit. Requires real MySQL/PostgreSQL instances to cover. |
| TranslationService.php | 211-212 | `findFallback()` catch branch for invalid fallback locale. The `Language::byCode()` static cache makes it hard to trigger a throw after initial load in the same process. |
| TranslatableServiceProvider.php | 48-49 | `database_loader` config branch. The `$this->app->extend('translation.loader', ...)` closure runs only when `database_loader=true`, which is off by default. Testable but requires a separate test case with config override at boot time. |

### Lines that could be covered with minor effort

| File | Lines | What | Effort |
|------|-------|------|--------|
| ImportCommand.php | 85 | `$skipped++` when existing translation has unchanged content | Low -- add test importing same content twice |
| ScanCommand.php | 42-44 | File extension filter `$ext !== 'php'` inner branch | Low -- add non-PHP file to scan directory |
| DetectLanguage.php | 56 | `return null` when `getPreferredLanguage()` returns null | Low -- request without Accept-Language header |
| Language.php | 67 | `return static::$defaultLanguage` cached early return | Low -- call getDefaultLanguage twice in same test |

**Estimated effort to reach ~99%:** 4 simple tests (add import-unchanged, scan-non-php-file, no-accept-language-header, double-getDefaultLanguage).

**Lines that would remain at ~99%:** TranslationTrait `castToString` MySQL/PostgreSQL branches (lines 155-156), TranslationService `findFallback` catch (lines 211-212), ServiceProvider `database_loader` boot closure (lines 48-49). These 6 lines require either a different database driver or complex boot-time test configuration and represent 0.5% of total source.

## Test Distribution

```
Tests by component:
  Models          42  ████████████████████████████████████████░░  34%
  Services        35  ████████████████████████████████░░░░░░░░░░  28%
  Console         15  ██████████████░░░░░░░░░░░░░░░░░░░░░░░░░░░  12%
  Traits          12  ████████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░  10%
  Loaders          7  ███████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░   6%
  Middleware        5  █████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░   4%
  Events            4  ████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░   3%
  Provider          4  ████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░   3%
  Total           124
```

## Conclusion

The test suite provides **97.4% line coverage** across 16 source files with 124 tests and 235 assertions. 10 of 16 files have 100% coverage. The remaining 2.6% (approximately 10 uncovered lines) falls into two categories:

1. **Easy to cover (~4 lines):** import skip counter, scan file filter, middleware null-header, Language cache return. Four additional tests would bring coverage to ~99%.

2. **Not coverable on SQLite (~6 lines):** MySQL/PostgreSQL-specific CAST branches and complex boot-time configurations. These require integration tests on real database instances or dedicated boot-time test infrastructure.

The current coverage level is appropriate for a library package. The uncovered paths are all edge cases or driver-specific branches that do not affect the core translation logic.
