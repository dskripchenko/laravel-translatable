# Архитектура

> dskripchenko/laravel-translatable -- техническое описание

## Обзор

Пакет обеспечивает хранение переводов в базе данных для Laravel с тремя функциональными слоями:

1. **Переводы моделей** -- любая Eloquent-модель получает мультиязычные поля через `TranslationTrait`
2. **Загрузчик UI-строк** -- `DatabaseTranslationLoader` заменяет файловый загрузчик Laravel для `__()` / `trans()`
3. **CMS-контент-блоки** -- `ContentBlockService` управляет переводимыми блоками с привязкой к страницам

Все слои используют единую таблицу `translations` с полиморфной привязкой.

## Структура файлов

```
src/
├── Console/
│   ├── ExportCommand.php            # translatable:export {locale}
│   ├── ImportCommand.php            # translatable:import {file}
│   └── ScanCommand.php             # translatable:scan --path=...
├── Events/
│   ├── TranslationCreated.php       # Вызывается при первом обращении (auto_create)
│   └── TranslationUpdated.php       # Вызывается при изменении контента (с oldContent)
├── Http/Middleware/
│   └── DetectLanguage.php           # Автоопределение локали из URL/cookie/заголовка
├── Loaders/
│   └── DatabaseTranslationLoader.php  # Декорирует FileLoader, накладывает БД-переводы
├── Models/
│   ├── Language.php                 # Языки со статическим кэшем + resetStaticCache()
│   ├── Translation.php              # Записи переводов (полиморфно через entity/entity_id)
│   ├── ContentBlock.php             # CMS-блоки с TranslationTrait
│   ├── Page.php                     # Страницы (автосоздание по URI), M2M с ContentBlock
│   └── PageContentBlock.php         # Pivot-модель (без timestamps)
├── Providers/
│   └── TranslatableServiceProvider.php  # Миграции, конфиг, команды, загрузчик, middleware
├── Services/
│   ├── TranslationService.php       # Ядро: кэш, getTranslation, fallback, batch, plural
│   └── ContentBlockService.php      # CMS: inline/global/begin-end, привязка к страницам
└── Traits/
    └── TranslationTrait.php         # t(), tc(), saveTranslation(s), scopes

config/translatable.php              # auto_create, fallback_locale, database_loader, tables
databases/migrations/                # 5 таблиц (все имена настраиваемы через env)
tests/Feature/                       # 124 теста, покрытие 97.4%
```

## Схема БД

Все имена таблиц настраиваются через `config/translatable.php` и переменные окружения `TRANSLATABLE_*_TABLE`.

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

### Как используются строки `translations`

| Сценарий | group | entity | entity_id | Пример |
|----------|-------|--------|-----------|--------|
| Поле модели | `'field'` | `App\Models\Product` | `'42'` | `$product->t('name')` |
| UI-строка | `'messages'` | `''` | `''` | `__('messages.welcome')` |
| JSON-строка | `'*'` | `''` | `''` | `__('Welcome')` |
| CMS-блок | `'field'` | `...ContentBlock` | `'7'` | `$cms->inline('hero.title', ...)` |
| Общий | `'default'` | `''` | `''` | `TranslationService::getTranslation('key')` |

## Взаимодействие компонентов

```
Запрос
  │
  ├─[Middleware: DetectLanguage]──→ app()->setLocale()
  │
  ├─[View: __('messages.hello')]
  │     └─→ DatabaseTranslationLoader
  │           ├─ FileLoader::load()        ← файловые переводы
  │           └─ Translation::where(group='messages', entity='')  ← БД-наложение
  │
  ├─[Controller: $product->t('name')]
  │     └─→ TranslationTrait::t()
  │           └─→ TranslationService::getTranslation()
  │                 ├─ boot() → bootLanguage()     ← ленивая загрузка по языку
  │                 ├─ $cache[lang][key] hit?       ← уровень 1: in-memory
  │                 ├─ Cache::tags()->remember()    ← уровень 2: Redis/Memcached
  │                 ├─ findFallback()               ← попытка fallback-локали
  │                 └─ firstOrCreate()              ← автосоздание если включено
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

## Стратегия кэширования

### Двухуровневый кэш

| Уровень | Хранилище | Время жизни | Область |
|---------|-----------|-------------|---------|
| 1 | `TranslationService::$cache` (статический массив) | Один запрос | Процесс |
| 2 | `Cache::tags(['translation_static_cache'])` | `config('cache.translation_ttl')` | Общий (Redis) |

### Формат ключа кэша

```
translation_static_cache_{language_code}_{hash}
```

Где `hash` = `config('cache.translation_hash')`. Измените значение при деплое для полной инвалидации кэша.

### Инвалидация

- `TranslationService::refresh()` -- обновляет in-memory кэш + `Cache::forget()` для конкретного языка
- `Language::resetStaticCache()` -- очищает кэши модели Language (для long-running процессов)
- `TranslationService::$cache = null` -- сброс in-memory кэша переводов
- `ContentBlockService::$cache = null` -- сброс кэша контент-блоков

### Ленивая загрузка

`boot()` инициализирует пустой массив `$cache`. `bootLanguage($language)` загружает переводы конкретного языка только при первом обращении. Неиспользуемые языки никогда не загружаются.

## Цепочка fallback

Когда `auto_create` отключён и перевод не найден:

```
Запрошенный язык (напр. 'fr')
    │ не найден
    ▼
Fallback-язык (config: translatable.fallback_locale или app.fallback_locale)
    │ не найден
    ▼
Значение по умолчанию (параметр t() или getTranslation())
```

Когда `auto_create` включён, создаётся новая запись на запрошенном языке с дефолтным значением. Fallback не используется -- новая запись является авторитетной.

## Справочник конфигурации

```php
// config/translatable.php
'auto_create'     => true,   // Создавать запись перевода при первом обращении
'fallback_locale' => null,   // null = использовать config('app.fallback_locale')
'database_loader' => false,  // Заменить FileLoader на БД-наложение
'tables' => [...]            // 5 имён таблиц, переопределяемых через env

// config/cache.php (устанавливается приложением)
'translation_hash' => 'v1',  // Ключ версии кэша (менять при деплое)
'translation_ttl'  => 3600,  // TTL persistent-кэша в секундах
```

## Кросс-СУБД совместимость

Пакет одинаково работает на MySQL, PostgreSQL и SQLite:

- `entity` / `entity_id` -- NOT NULL с `default('')` -- избегает проблемы NULL-в-UNIQUE на всех СУБД
- `Language::byCode()` использует `mb_strtolower()` для регистронезависимого поиска (PostgreSQL регистрозависим по умолчанию)
- `orderByTranslation` использует CAST, специфичный для драйвера (CHAR для MySQL, VARCHAR для PostgreSQL, TEXT для SQLite)
- Нет raw SQL кроме стандартных CAST-выражений
