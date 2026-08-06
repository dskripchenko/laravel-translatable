# Wettbewerbsanalyse

> Vergleich von Laravel-Übersetzungspaketen, Stand März 2026.

## Ansätze zur Model-Übersetzung

| Ansatz | Speicherung | Paket | Downloads |
|--------|-------------|-------|-----------|
| **JSON-Spalte** | JSON in der eigenen Tabelle des Models | spatie/laravel-translatable | ~22.8M |
| **Separate Tabelle pro Model** | `{model}_translations`-Tabelle | astrotomic/laravel-translatable | ~7.6M |
| **Einzelne Übersetzungstabelle** | Eine `translations`-Tabelle, polymorph | **dskripchenko/laravel-translatable** | -- |

Zusätzlich gibt es Pakete nur für **UI-Strings** (nicht Models): spatie/laravel-translation-loader (~2.8M), joedixon/laravel-translation (~500K).

## Detaillierter Vergleich

### spatie/laravel-translatable

**Downloads:** ~22.8M | **Stars:** 2426 | **Laravel:** 11-13

Speichert Übersetzungen als JSON in einer Spalte der Model-Tabelle. Einfachstes Setup -- ein Trait, eine JSON-Spalte, keine Extra-Tabellen.

**Stärken:** kein Query-Overhead (Übersetzungen laden mit dem Model), `whereLocale()` / `whereJsonContainsLocale()` Scopes, riesige Community.

**Einschränkungen:** JSON ist nicht effizient indexierbar für Volltextsuche, keine Normalisierung (alle Sprachen in einem Feld), keine zentrale Übersetzungsverwaltung, keine Caching-Schicht, jede Model-Abfrage lädt alle Sprachen.

### astrotomic/laravel-translatable

**Downloads:** ~7.6M | **Stars:** 1392 | **Laravel:** 9-12

Erstellt eine separate `{model}_translations`-Tabelle für jedes übersetzbare Model. Jedes übersetzte Feld bekommt eine eigene typisierte Spalte.

**Stärken:** volle Normalisierung mit Standardindizes, typisierte Spalten (string, text usw.), Fallback-Sprachkette, JOIN-basiertes Eager Loading via `withTranslation()`.

**Einschränkungen:** Tabellenexplosion (jedes Model = +1 Tabelle + 1 PHP-Klasse), zusätzliche Migrationen bei jeder Feldänderung, kein eingebautes Caching, komplexeres initiales Setup, weniger aktiv gepflegt (Fork des eingestellten dimsav).

### lexi-translate

**Downloads:** <10K | **Stars:** ~30 | **Laravel:** 10-11

Verwendet eine einzelne `translations`-Tabelle mit Morph-Beziehungen -- architektonisch ähnlich zu dskripchenko.

**Stärken:** einzelne Tabelle, Standard-Morph-Muster, eingebautes Caching, Fallback-Sprache.

**Einschränkungen:** junges Projekt, unterstützt Laravel 12+ nicht, begrenzte Dokumentation.

### spatie/laravel-translation-loader

**Downloads:** ~2.8M | **Stars:** 835 | **Laravel:** 6-13

Ersetzt Laravels dateibasierten Übersetzungslader durch ein Datenbank-Backend. Funktioniert mit `__()`, `trans()`, `@lang()` ohne Codeänderungen.

**Stärken:** volle Kompatibilität mit Laravels Übersetzungshelfern, DB überschreibt Dateiübersetzungen, erweiterbar (YAML/CSV-Provider).

**Einschränkungen:** nur UI-Strings -- keine Model-Übersetzungen, keine Sprachverwaltung.

### joedixon/laravel-translation

**Downloads:** ~500K | **Stars:** ~600

Komplettes Übersetzungsmanagementsystem mit Web-UI, Scanner und Datenbanktreiber.

**Stärken:** eingebauter Web-Editor, Scanner für fehlende Übersetzungen, File- und Database-Treiber, Artisan-Befehle.

**Einschränkungen:** nur UI-Strings, weniger aktive Pflege, schwerere Abhängigkeiten.

## Funktionsmatrix

| Funktion | spatie | astrotomic | lexi | spatie-loader | joedixon | **dskripchenko** |
|----------|:------:|:----------:|:----:|:------------:|:--------:|:----------------:|
| Model-Feldübersetzungen | JSON | Pro-Model-Tabelle | Morph | -- | -- | **Morph** |
| UI-Strings `__()` / `trans()` | -- | -- | -- | Ja | Ja | **Ja** |
| Fallback-Sprache | -- | Ja | Ja | -- | -- | **Ja** |
| Caching | -- | -- | Ja | -- | -- | **Zweistufig** |
| Query Scopes | JSON where | JOIN | -- | -- | -- | **whereTranslation / orderBy** |
| Pluralformen | -- | -- | -- | Laravel nativ | Laravel nativ | **tc() + MessageSelector** |
| Events | -- | -- | -- | -- | -- | **Created / Updated** |
| Batch-Operationen | -- | -- | Ja | -- | -- | **setTranslations** |
| Artisan CLI | -- | -- | -- | -- | Ja | **export / import / scan** |
| Middleware | -- | -- | -- | -- | -- | **DetectLanguage** |
| Sprachverwaltung | -- | -- | -- | -- | -- | **Language-Model** |
| CMS-Inhaltsblöcke | -- | -- | -- | -- | -- | **ContentBlockService** |
| Seiten-Block-Bindung | -- | -- | -- | -- | -- | **Page <-> ContentBlock** |
| Parameterersetzung | -- | -- | -- | -- | -- | **{placeholder} in inline()** |
| Output Buffering | -- | -- | -- | -- | -- | **begin() / end()** |
| Automatische Erstellung | -- | -- | -- | -- | -- | **auto_create** |
| Konfigurierbare Tabellennamen | -- | -- | -- | -- | -- | **env + config** |
| Routen-Pattern-Helfer | -- | -- | -- | -- | -- | **getRouteGroupPattern()** |

## Wann welches Paket wählen

| Situation | Empfehlung |
|-----------|-----------|
| Kleines Projekt, 2-3 übersetzbare Models, minimaler Aufwand | **spatie/laravel-translatable** -- JSON-Spalte ohne Overhead. Der einfachste Ansatz, der gut funktioniert wenn die Übersetzungsdaten klein sind und keine zentrale Verwaltung benötigt wird. |
| Großes Projekt mit komplexen Abfragen auf übersetzten Feldern, strenge Typisierung | **astrotomic/laravel-translatable** -- normalisierte Pro-Model-Tabellen bieten volle SQL-Leistung mit korrekten Typen. |
| Vollständiger Lokalisierungsstack: Models + UI + CMS + Sprachverwaltung + Caching | **dskripchenko/laravel-translatable** -- ein Paket deckt alle Schichten ab. Besonders stark wenn Automatische Erstellung, Inhaltsblöcke benötigt werden und keine Mehrpaket-Integration gewünscht ist. |
| Nur UI-Strings aus der DB, bestehende Codebasis mit `__()` Aufrufen | **spatie/laravel-translation-loader** -- transparenter Ersatz für dateibasierte Übersetzungen ohne Codeänderungen. |
| Visueller Übersetzungseditor für nicht-technische Teammitglieder | **joedixon/laravel-translation** -- kommt mit eingebautem Web-Interface. |
| Pakete kombinieren | Sie können **dskripchenko** zusammen mit **spatie/translatable** verwenden, wenn manche Models von JSON-Speicherung profitieren (z.B. einfache Config-Felder) und andere die zentrale Übersetzungstabelle benötigen. Die Pakete kollidieren nicht. |

Quellen:
- [spatie/laravel-translatable -- Packagist](https://packagist.org/packages/spatie/laravel-translatable)
- [astrotomic/laravel-translatable -- Packagist](https://packagist.org/packages/astrotomic/laravel-translatable)
- [spatie/laravel-translation-loader -- Packagist](https://packagist.org/packages/spatie/laravel-translation-loader)
- [lexi-translate -- GitHub](https://github.com/omaralalwi/lexi-translate)
- [joedixon/laravel-translation -- GitHub](https://github.com/joedixon/laravel-translation)
