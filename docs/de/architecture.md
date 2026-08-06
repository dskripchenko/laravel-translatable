# Architektur

> dskripchenko/laravel-translatable -- technische Referenz

## Überblick

Das Paket bietet datenbankgestützte Übersetzungen für Laravel mit drei funktionalen Schichten:

1. **Model-Übersetzungen** -- jedes Eloquent-Model erhält mehrsprachige Felder über `TranslationTrait`
2. **UI-String-Lader** -- `DatabaseTranslationLoader` ersetzt Laravels dateibasierten `__()` / `trans()`
3. **CMS-Inhaltsblöcke** -- `ContentBlockService` verwaltet übersetzbare Blöcke mit Seitenbindung

Alle Schichten nutzen eine gemeinsame `translations`-Tabelle mit polymorpher Bindung.

## Dateistruktur

```
src/
├── Console/
│   ├── ExportCommand.php            # translatable:export {locale}
│   ├── ImportCommand.php            # translatable:import {file}
│   └── ScanCommand.php             # translatable:scan --path=...
├── Events/
│   ├── TranslationCreated.php       # Ausgelöst beim ersten Zugriff (auto_create)
│   └── TranslationUpdated.php       # Ausgelöst bei Inhaltsänderung (mit oldContent)
├── Http/Middleware/
│   └── DetectLanguage.php           # Automatische Spracherkennung aus URL/Cookie/Header
├── Loaders/
│   └── DatabaseTranslationLoader.php  # Dekoriert FileLoader, überlagert DB-Übersetzungen
├── Models/
│   ├── Language.php                 # Sprachen mit statischem Cache + resetStaticCache()
│   ├── Translation.php              # Übersetzungsdatensätze (polymorph via entity/entity_id)
│   ├── ContentBlock.php             # CMS-Blöcke mit TranslationTrait
│   ├── Page.php                     # Seiten (automatisch per URI erstellt), M2M mit ContentBlock
│   └── PageContentBlock.php         # Pivot-Model (ohne Timestamps)
├── Providers/
│   └── TranslatableServiceProvider.php  # Migrationen, Config, Befehle, Loader, Middleware
├── Services/
│   ├── TranslationService.php       # Kern: Cache, getTranslation, Fallback, Batch, Plural
│   └── ContentBlockService.php      # CMS: inline/global/begin-end, Seitenbindung
└── Traits/
    └── TranslationTrait.php         # t(), tc(), saveTranslation(s), Scopes

config/translatable.php              # auto_create, fallback_locale, database_loader, tables
databases/migrations/                # 5 Tabellen (alle Namen per env konfigurierbar)
tests/Feature/                       # 124 Tests, 97,4% Abdeckung
```

## Datenbankschema

Alle Tabellennamen sind über `config/translatable.php` und `TRANSLATABLE_*_TABLE` Umgebungsvariablen konfigurierbar.

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

### Verwendung der `translations`-Zeilen

| Anwendungsfall | group | entity | entity_id | Beispiel |
|----------------|-------|--------|-----------|----------|
| Model-Feld | `'field'` | `App\Models\Product` | `'42'` | `$product->t('name')` |
| UI-String | `'messages'` | `''` | `''` | `__('messages.welcome')` |
| JSON-String | `'*'` | `''` | `''` | `__('Welcome')` |
| CMS-Block | `'field'` | `...ContentBlock` | `'7'` | `$cms->inline('hero.title', ...)` |
| Allgemein | `'default'` | `''` | `''` | `TranslationService::getTranslation('key')` |

## Komponenteninteraktion

```
Request
  │
  ├─[Middleware: DetectLanguage]──→ app()->setLocale()
  │
  ├─[View: __('messages.hello')]
  │     └─→ DatabaseTranslationLoader
  │           ├─ FileLoader::load()        ← Datei-Übersetzungen
  │           └─ Translation::where(group='messages', entity='')  ← DB-Überlagerung
  │
  ├─[Controller: $product->t('name')]
  │     └─→ TranslationTrait::t()
  │           └─→ TranslationService::getTranslation()
  │                 ├─ boot() → bootLanguage()     ← Lazy Loading pro Sprache
  │                 ├─ $cache[lang][key] hit?       ← Ebene 1: In-Memory
  │                 ├─ Cache::tags()->remember()    ← Ebene 2: Redis/Memcached
  │                 ├─ findFallback()               ← Fallback-Sprache versuchen
  │                 └─ firstOrCreate()              ← Automatisch erstellen wenn aktiviert
  │                       └─→ TranslationCreated Event
  │
  └─[View: $cms->inline('hero.title', ...)]
        └─→ ContentBlockService::inline()
              ├─ get() → ContentBlock::firstOrCreate()
              ├─ getCurrentPage() → Page::firstOrCreate(uri)
              ├─ Page::link(block)
              ├─ $block->t('content')  → TranslationService
              └─ str_replace({Platzhalter})
```

## Caching-Strategie

### Zweistufiger Cache

| Ebene | Speicher | Lebensdauer | Bereich |
|-------|----------|-------------|---------|
| 1 | `TranslationService::$cache` (statisches Array) | Ein Request | Pro Prozess |
| 2 | `Cache::tags(['translation_static_cache'])` | `config('cache.translation_ttl')` | Gemeinsam (Redis) |

### Cache-Schlüsselformat

```
translation_static_cache_{language_code}_{hash}
```

Wobei `hash` = `config('cache.translation_hash')`. Ändern Sie diesen Wert beim Deploy, um den gesamten Cache zu invalidieren.

### Invalidierung

- `TranslationService::refresh()` -- aktualisiert In-Memory-Cache + `Cache::forget()` für den spezifischen Sprach-Key
- `Language::resetStaticCache()` -- leert Language-Model-Caches (für Long-Running-Prozesse)
- `TranslationService::$cache = null` -- setzt In-Memory-Übersetzungscache zurück
- `ContentBlockService::$cache = null` -- setzt Inhaltsblock-Cache zurück

### Lazy Loading

`boot()` initialisiert ein leeres `$cache`-Array. `bootLanguage($language)` lädt Übersetzungen für eine bestimmte Sprache erst beim ersten Zugriff. Nicht verwendete Sprachen werden nie geladen.

## Fallback-Kette

Wenn `auto_create` deaktiviert ist und eine Übersetzung fehlt:

```
Angeforderte Sprache (z.B. 'fr')
    │ nicht gefunden
    ▼
Fallback-Sprache (Config: translatable.fallback_locale oder app.fallback_locale)
    │ nicht gefunden
    ▼
Standardwert (als Parameter an t() oder getTranslation() übergeben)
```

Wenn `auto_create` aktiviert ist, wird ein neuer Datensatz in der angeforderten Sprache mit dem Standardwert erstellt. Fallback wird nicht konsultiert -- der neue Datensatz ist maßgeblich.

## Konfigurationsreferenz

```php
// config/translatable.php
'auto_create'     => true,   // Übersetzungsdatensatz beim ersten Zugriff erstellen
'fallback_locale' => null,   // null = config('app.fallback_locale') verwenden
'database_loader' => false,  // Laravels FileLoader durch DB-Überlagerung ersetzen
'tables' => [...]            // Alle 5 Tabellennamen, per env überschreibbar

// config/cache.php (vom Anwendung gesetzt)
'translation_hash' => 'v1',  // Cache-Versionsschlüssel (beim Deploy ändern)
'translation_ttl'  => 3600,  // Persistenter Cache TTL in Sekunden
```

## Datenbankübergreifende Kompatibilität

Das Paket funktioniert identisch auf MySQL, PostgreSQL und SQLite:

- `entity` / `entity_id` sind NOT NULL mit `default('')` -- vermeidet NULL-in-UNIQUE-Probleme auf allen Datenbanken
- `Language::byCode()` verwendet `mb_strtolower()` für groß-/kleinschreibungsunabhängige Suche (PostgreSQL ist standardmäßig case-sensitive)
- `orderByTranslation` verwendet treiberspezifisches CAST (CHAR für MySQL, VARCHAR für PostgreSQL, TEXT für SQLite)
- Kein Raw-SQL außer Standard-CAST-Ausdrücken
