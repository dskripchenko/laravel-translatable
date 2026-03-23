# Laravel Translatable

Ein umfassendes Lokalisierungspaket für Laravel, das **Model-Übersetzungen**, **UI-Strings aus der Datenbank** und **CMS-Inhaltsblöcke** in einer einheitlichen Lösung vereint.

Übersetzungen werden in der Datenbank gespeichert -- mit zweistufigem Caching (In-Memory + Redis/Memcached), automatischer Datensatzerstellung, Fallback-Sprachkette und vollständiger Integration mit Laravels `__()` / `trans()` Hilfsfunktionen.

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](../LICENSE.md)

> [English README](../README.md) | [README на русском](README.ru.md) | [中文文档](README.zh.md)

## Warum dieses Paket?

Die meisten Laravel-Übersetzungspakete lösen nur ein einzelnes Problem. Wenn Sie mehrsprachige Models, UI-Strings aus der Datenbank und CMS-Inhalte benötigen, müssen Sie normalerweise 2-3 separate Pakete installieren. Dieses Paket vereint alles in einer einheitlichen Architektur:

| Funktion | spatie | astrotomic | **Dieses Paket** |
|----------|:---:|:---:|:---:|
| Model-Feldübersetzungen | JSON-Spalte | Eigene Tabelle pro Model | Einzelne `translations`-Tabelle |
| UI-Strings (`__()` / `trans()`) | - | - | Integrierter DatabaseLoader |
| CMS-Inhaltsblöcke | - | - | ContentBlockService |
| Zweistufiges Caching | - | - | In-Memory + Cache::tags |
| Fallback-Sprache | - | Ja | Ja |
| Query Scopes | JSON where | JOIN | whereTranslation / orderBy |
| Events | - | - | Created / Updated |
| Artisan CLI | - | - | export / import / scan |
| Spracherkennungs-Middleware | - | - | DetectLanguage |
| Pluralformen | - | - | tc() + MessageSelector |
| Batch-Operationen | - | - | setTranslations / saveTranslations |

## Voraussetzungen

- PHP 8.1+
- Laravel 11 oder 12
- Cache-Treiber mit Tag-Unterstützung (Redis, Memcached oder Array)
- MySQL 5.7+ / PostgreSQL 12+ / SQLite 3.35+

## Installation

```bash
composer require dskripchenko/laravel-translatable
```

Das Paket nutzt Laravels Auto-Discovery. Führen Sie die Migrationen aus:

```bash
php artisan migrate
```

## Schnellstart

### 1. Sprachen anlegen

```php
use Dskripchenko\LaravelTranslatable\Models\Language;

Language::create(['code' => 'de', 'label' => 'Deutsch', 'is_active' => true, 'as_locale' => true]);
Language::create(['code' => 'en', 'label' => 'English', 'is_active' => true]);
Language::create(['code' => 'fr', 'label' => 'Français', 'is_active' => true]);
```

### 2. Model-Felder übersetzen

Fügen Sie `TranslationTrait` zu einem Eloquent-Model hinzu:

```php
use Dskripchenko\LaravelTranslatable\Traits\TranslationTrait;

class Product extends Model
{
    use TranslationTrait;
}
```

Übersetzungen lesen und schreiben:

```php
$product = Product::find(1);

// Lesen (aktuelle App-Locale)
$product->t('name');
$product->t('name', 'Standardname');

// Bestimmte Sprache lesen
$product->t('name', null, Language::byCode('en'));

// Schreiben
$product->saveTranslation('name', 'Product Name', Language::byCode('en'));

// Mehrere Felder gleichzeitig
$product->saveTranslations([
    'name' => 'Produktname',
    'description' => 'Eine Beschreibung',
], Language::byCode('de'));
```

### 3. Pluralformen

Speichern Sie pluralisierten Inhalt im Laravel-Format:

```php
// In der Datenbank: "{0} Keine Artikel|{1} Ein Artikel|[2,*] :count Artikel"
$product->tc('items_label', 0);                // "Keine Artikel"
$product->tc('items_label', 1);                // "Ein Artikel"
$product->tc('items_label', 5, ['count' => 5]); // "5 Artikel"
```

### 4. Nach Übersetzungen suchen

```php
// Produkte nach übersetztem Namen finden
Product::whereTranslation('name', 'Laptop')->get();
Product::whereTranslation('name', 'like', '%Laptop%', 'de')->get();

// Nach übersetztem Feld sortieren
Product::orderByTranslation('name', 'asc', 'de')->get();
```

### 5. CMS-Inhaltsblöcke

Verwalten Sie übersetzbare Inhaltsblöcke mit Seitenverknüpfung:

```php
use Dskripchenko\LaravelTranslatable\Services\ContentBlockService;

$cms = new ContentBlockService();

// Textblock (automatisch mit der aktuellen Seite verknüpft)
echo $cms->inline('hero.title', 'Überschrift', 'Willkommen auf unserer Seite');

// Mit Parameterersetzung
echo $cms->inline('greeting', 'Begrüßung', 'Hallo, {name}!', ['name' => $user->name]);

// Globaler Block (nicht seitengebunden)
echo $cms->global('site.name', 'Seitenname', 'Meine Anwendung');
```

### 6. UI-Strings aus der Datenbank

Aktivieren Sie den Datenbank-Übersetzungslader:

```env
TRANSLATABLE_DATABASE_LOADER=true
```

Jetzt prüft `__('messages.welcome')` zuerst die Datenbank und fällt dann auf Sprachdateien zurück. Dies ist praktisch, wenn Administratoren UI-Texte ohne Code-Deployment bearbeiten möchten.

## Konfiguration

```php
// config/translatable.php
return [
    'auto_create'     => env('TRANSLATABLE_AUTO_CREATE', true),      // Automatische Erstellung
    'fallback_locale' => env('TRANSLATABLE_FALLBACK_LOCALE'),        // Fallback-Sprache
    'database_loader' => env('TRANSLATABLE_DATABASE_LOADER', false), // DB-Loader für __()
    'tables' => [ /* anpassbare Tabellennamen */ ],
];
```

Cache-Parameter in `config/cache.php`:

```php
'translation_hash' => env('TRANSLATION_CACHE_HASH', 'v1'), // Bei Deploy ändern
'translation_ttl'  => env('TRANSLATION_CACHE_TTL', 3600),  // TTL in Sekunden
```

## Middleware

Automatische Spracherkennung aus URL, Cookie oder `Accept-Language`-Header:

```php
Route::middleware('translatable.detect')->group(function () {
    // Sprache wird automatisch erkannt
});
```

Priorität: Routenparameter `{locale}` > Erstes URL-Segment > Cookie `locale` > `Accept-Language`-Header.

## Artisan-Befehle

```bash
php artisan translatable:export de --output=translations-de.json  # Export
php artisan translatable:import translations-de.json --locale=de  # Import
php artisan translatable:import translations.json --dry-run       # Vorschau
php artisan translatable:scan --path=app,resources                # Code scannen
```

## Events

```php
use Dskripchenko\LaravelTranslatable\Events\TranslationCreated;
use Dskripchenko\LaravelTranslatable\Events\TranslationUpdated;

Event::listen(TranslationCreated::class, fn ($e) => Log::info("Erstellt: {$e->translation->key}"));
Event::listen(TranslationUpdated::class, fn ($e) => Log::info("Aktualisiert: {$e->translation->key}"));
```

## Architektur

Alle Übersetzungen werden in einer einzigen `translations`-Tabelle mit polymorpher Bindung über `entity` / `entity_id` gespeichert. Eine Tabelle bedient Model-Übersetzungen, UI-Strings und CMS-Blöcke.

**Caching** ist zweistufig: statischer In-Memory-Cache (pro Request) + persistenter Cache via `Cache::tags` (Redis/Memcached). Übersetzungen werden lazy pro Sprache beim ersten Zugriff geladen.

Detaillierte technische Dokumentation: [architecture.de.md](architecture.de.md) ([en](architecture.md) | [ru](architecture.ru.md) | [zh](architecture.zh.md)).

## Wann dieses Paket wählen

| Ihre Situation | Empfehlung |
|----------------|-----------|
| Kleines Projekt, 2-3 Models, minimaler Aufwand | Erwägen Sie [spatie/laravel-translatable](https://packagist.org/packages/spatie/laravel-translatable) |
| Komplexe Abfragen, strenge Typisierung | Erwägen Sie [astrotomic/laravel-translatable](https://packagist.org/packages/astrotomic/laravel-translatable) |
| Vollständige Lokalisierung: Models + UI + CMS + Sprachverwaltung | **Dieses Paket** |
| Nur UI-Strings in der Datenbank | Erwägen Sie [spatie/laravel-translation-loader](https://packagist.org/packages/spatie/laravel-translation-loader) |

Detaillierter Vergleich: [competitive-analysis.de.md](competitive-analysis.de.md) ([en](competitive-analysis.md) | [ru](competitive-analysis.ru.md) | [zh](competitive-analysis.zh.md)).

## Tests

```bash
vendor/bin/pest               # 124 Tests, Abdeckung 97,4%
```

## Lizenz

MIT. Details in [LICENSE.md](../LICENSE.md).
