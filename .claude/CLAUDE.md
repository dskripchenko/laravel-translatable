# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Проект

Библиотека `dskripchenko/laravel-translatable` — динамическая локализация контента для Laravel. Переводы хранятся в БД, автоматически создаются при первом обращении, любая Eloquent-модель получает мультиязычность через trait. Подсистема контент-блоков обеспечивает CMS-функциональность с привязкой к страницам.

**Стек:** PHP 8.1+ / Laravel 11–12 / Pest 3 (тесты) / SQLite in-memory (тестовая БД)

**Зависимости:** `dskripchenko/php-array-helper` ^1.1 (функция `array_merge_deep` для глубокого слияния конфигов).

## Команды

```bash
composer install                                # Установка зависимостей
vendor/bin/pest                                 # Все тесты (73 теста)
vendor/bin/pest --filter="test name"            # Один тест по имени
vendor/bin/pest tests/Feature/Services/         # Тесты в директории
vendor/bin/pest tests/Feature/Models/PageTest.php  # Один файл
```

## Архитектура

Полное техническое описание — `docs/architecture.md`.

### Компоненты

```
src/
├── Providers/TranslatableServiceProvider.php  # Миграции + глубокий merge конфига
├── Models/
│   ├── Language.php            # Языки (статический кэш, lookup, утилиты маршрутов)
│   ├── Translation.php         # Переводы (полиморфная привязка entity/entity_id)
│   ├── ContentBlock.php        # Контент-блоки с TranslationTrait
│   ├── Page.php                # Страницы (uri-based), M2M с ContentBlock
│   └── PageContentBlock.php    # Pivot Page <-> ContentBlock (без timestamps)
├── Services/
│   ├── TranslationService.php  # Ядро: ленивый двухуровневый кэш + firstOrCreate
│   └── ContentBlockService.php # CMS: inline/global блоки, output buffering
└── Traits/
    └── TranslationTrait.php    # t() и saveTranslation() для любой модели
```

### Схема БД (5 таблиц, имена конфигурируемы)

```
languages (code, label, is_active, as_locale, soft_deletes)
translations (language_id FK, group, key, type, entity, entity_id, content)
  └─ UNIQUE(language_id, group, key, entity, entity_id)
content_blocks (key UNIQUE, description, type, content)
pages (name, uri, soft_deletes)
page_content_block (page_id FK cascade, content_block_id FK cascade)
```

### Поток данных

1. `TranslationService::boot()` — инициализирует пустой `$cache`. `bootLanguage()` лениво подгружает переводы конкретного языка через `Cache::tags()->remember()`.
2. `getTranslation(key, group, entity, entity_id)` — ищет в кэше по `"{group}|{key}|{entity}|{entity_id}"`, при промахе `firstOrCreate` в БД (если `auto_create=true`).
3. `TranslationTrait::t('field')` — обёртка с `entity=static::class`, `entity_id=(string)$this->getKey()`, `group='field'`.
4. `ContentBlockService::inline()` — `firstOrCreate` блок, перевод через trait, подстановка `{placeholder}` параметров, привязка к текущей странице.

### Кэширование

- **In-memory:** `TranslationService::$cache`, `ContentBlockService::$cache`, `Language::$defaultLanguage`/`$languagesByCode`
- **Persistent:** `Cache::tags(['translation_static_cache'])` с TTL из `config('cache.translation_ttl')` и хэшем из `config('cache.translation_hash')`
- **Инвалидация:** `refresh()` удаляет persistent-ключ конкретного языка через `forget()`, не flush всего тега
- **Сброс:** `Language::resetStaticCache()`, `TranslationService::$cache = null`, `ContentBlockService::$cache = null`

## Ключевые соглашения

- `declare(strict_types=1)` во всех PHP-файлах
- Все модели используют конфигурируемые имена таблиц через `getTable()` + `config('translatable.tables.*')`
- `Language::getDefaultLanguage()` — стратегия: `as_locale=true` → `config('app.locale')` → Exception
- Автосоздание переводов управляется через `config('translatable.auto_create')` (default `true`)
- `firstOrCreate` в `getTranslation()` для потокобезопасности (UNIQUE constraint)
- `saveTranslation()` пропускает запись если значение не изменилось
- Конфиг мержится через `array_merge_deep` — вложенные ключи дополняют, а не заменяют дефолты
- `Cache::tags` требует драйвер с поддержкой тегов (Redis, Memcached, Array)

## Конфигурация

```php
// config/translatable.php
'auto_create' => env('TRANSLATABLE_AUTO_CREATE', true),
'tables' => [ /* имена таблиц через env */ ]

// Определяются в приложении (не в пакете):
config('cache.translation_hash')  // хэш для версионирования кэша
config('cache.translation_ttl')   // TTL persistent-кэша
config('app.locale')              // дефолтная локаль
```
