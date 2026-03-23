# Laravel Translatable

Комплексный пакет локализации для Laravel, объединяющий **переводы моделей**, **UI-строки из базы данных** и **CMS-контент-блоки** в едином решении.

Переводы хранятся в БД с двухуровневым кэшированием (in-memory + Redis/Memcached), автоматическим созданием записей, fallback-цепочкой и полной интеграцией с хелперами Laravel `__()` / `trans()`.

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](../LICENSE.md)

> [English README](../README.md) | [README auf Deutsch](README.de.md) | [中文文档](README.zh.md)

## Зачем этот пакет?

Большинство пакетов локализации для Laravel решают только одну задачу. Если нужны мультиязычные модели, UI-строки из базы данных и CMS-контент, обычно приходится устанавливать 2-3 отдельных пакета. Этот пакет объединяет всё в единой архитектуре:

| Возможность | spatie | astrotomic | **Этот пакет** |
|-------------|:---:|:---:|:---:|
| Переводы полей моделей | JSON-колонка | Отд. таблица на модель | Единая таблица `translations` |
| UI-строки (`__()` / `trans()`) | - | - | Встроенный DatabaseLoader |
| CMS-контент-блоки | - | - | ContentBlockService |
| Двухуровневый кэш | - | - | In-memory + Cache::tags |
| Fallback-локаль | - | Да | Да |
| Query scopes | JSON where | JOIN | whereTranslation / orderBy |
| Events | - | - | Created / Updated |
| Artisan CLI | - | - | export / import / scan |
| Middleware определения языка | - | - | DetectLanguage |
| Plural-формы | - | - | tc() + MessageSelector |
| Batch-операции | - | - | setTranslations / saveTranslations |

## Требования

- PHP 8.1+
- Laravel 11 или 12
- Кэш-драйвер с поддержкой тегов (Redis, Memcached или Array)
- MySQL 5.7+ / PostgreSQL 12+ / SQLite 3.35+

## Установка

```bash
composer require dskripchenko/laravel-translatable
```

Пакет использует авто-обнаружение Laravel. Выполните миграции:

```bash
php artisan migrate
```

## Быстрый старт

### 1. Создайте языки

```php
use Dskripchenko\LaravelTranslatable\Models\Language;

Language::create(['code' => 'en', 'label' => 'English', 'is_active' => true, 'as_locale' => true]);
Language::create(['code' => 'ru', 'label' => 'Русский', 'is_active' => true]);
Language::create(['code' => 'de', 'label' => 'Deutsch', 'is_active' => true]);
```

### 2. Переводы полей моделей

Добавьте `TranslationTrait` к любой Eloquent-модели:

```php
use Dskripchenko\LaravelTranslatable\Traits\TranslationTrait;

class Product extends Model
{
    use TranslationTrait;
}
```

Чтение и запись переводов:

```php
$product = Product::find(1);

// Чтение (текущая локаль приложения)
$product->t('name');
$product->t('name', 'Имя по умолчанию');

// Чтение конкретной локали
$product->t('name', null, Language::byCode('en'));

// Запись
$product->saveTranslation('name', 'Название товара', Language::byCode('ru'));

// Пакетная запись нескольких полей
$product->saveTranslations([
    'name' => 'Название товара',
    'description' => 'Описание товара',
], Language::byCode('ru'));
```

### 3. Plural-формы

Храните плюрализованный контент в формате Laravel:

```php
// В БД: "{0} Нет товаров|{1} Один товар|[2,*] :count товаров"
$product->tc('items_label', 0);                // "Нет товаров"
$product->tc('items_label', 1);                // "Один товар"
$product->tc('items_label', 5, ['count' => 5]); // "5 товаров"
```

### 4. Поиск по переводам

```php
// Найти товары по переведённому имени
Product::whereTranslation('name', 'Ноутбук')->get();
Product::whereTranslation('name', 'like', '%Ноутбук%', 'ru')->get();

// Сортировка по переведённому полю
Product::orderByTranslation('name', 'asc', 'ru')->get();
```

### 5. CMS-контент-блоки

Управляйте переводимыми блоками контента с привязкой к страницам:

```php
use Dskripchenko\LaravelTranslatable\Services\ContentBlockService;

$cms = new ContentBlockService();

// Текстовый блок (автоматически привязывается к текущей странице)
echo $cms->inline('hero.title', 'Заголовок', 'Добро пожаловать');

// С подстановкой параметров
echo $cms->inline('greeting', 'Приветствие', 'Привет, {name}!', ['name' => $user->name]);

// Глобальный блок (без привязки к странице)
echo $cms->global('site.name', 'Название сайта', 'Моё приложение');
```

### 6. UI-строки из базы данных

Включите загрузчик переводов из БД для работы с `__()` и `trans()`:

```env
TRANSLATABLE_DATABASE_LOADER=true
```

Теперь `__('messages.welcome')` сначала проверяет базу данных, затем файлы переводов. Это удобно, когда администраторы редактируют UI-строки без деплоя кода.

## Конфигурация

```php
// config/translatable.php
return [
    'auto_create'     => env('TRANSLATABLE_AUTO_CREATE', true),      // Автосоздание переводов
    'fallback_locale' => env('TRANSLATABLE_FALLBACK_LOCALE'),        // Fallback-локаль
    'database_loader' => env('TRANSLATABLE_DATABASE_LOADER', false), // Загрузчик __() из БД
    'tables' => [ /* настраиваемые имена таблиц */ ],
];
```

Параметры кэша в `config/cache.php`:

```php
'translation_hash' => env('TRANSLATION_CACHE_HASH', 'v1'), // Менять при деплое
'translation_ttl'  => env('TRANSLATION_CACHE_TTL', 3600),  // TTL в секундах
```

## Middleware

Автоматическое определение языка из URL, cookie или заголовка `Accept-Language`:

```php
Route::middleware('translatable.detect')->group(function () {
    // Язык определяется автоматически
});
```

Приоритет: параметр маршрута `{locale}` > первый сегмент URL > cookie `locale` > заголовок `Accept-Language`.

## Artisan-команды

```bash
php artisan translatable:export ru --output=translations-ru.json  # Экспорт
php artisan translatable:import translations-ru.json --locale=ru  # Импорт
php artisan translatable:import translations.json --dry-run       # Предпросмотр
php artisan translatable:scan --path=app,resources                # Сканирование кода
```

## События

```php
use Dskripchenko\LaravelTranslatable\Events\TranslationCreated;
use Dskripchenko\LaravelTranslatable\Events\TranslationUpdated;

Event::listen(TranslationCreated::class, function ($event) {
    Log::info("Перевод создан: {$event->translation->key}");
});

Event::listen(TranslationUpdated::class, function ($event) {
    Log::info("Перевод обновлён: {$event->translation->key}");
});
```

## Архитектура

Все переводы хранятся в единой таблице `translations` с полиморфной привязкой через поля `entity` / `entity_id`. Одна таблица обслуживает переводы моделей, UI-строки и CMS-блоки.

**Кэширование** двухуровневое: статический in-memory кэш (в рамках запроса) + persistent-кэш через `Cache::tags` (Redis/Memcached). Переводы загружаются лениво — по языку при первом обращении.

Подробная техническая документация: [architecture.ru.md](architecture.ru.md) ([en](architecture.md) | [de](architecture.de.md) | [zh](architecture.zh.md)).

## Когда выбирать этот пакет

| Ваша ситуация | Рекомендация |
|---------------|-------------|
| Маленький проект, 2-3 модели, минимум настройки | Рассмотрите [spatie/laravel-translatable](https://packagist.org/packages/spatie/laravel-translatable) |
| Сложные запросы по переведённым полям, строгая типизация | Рассмотрите [astrotomic/laravel-translatable](https://packagist.org/packages/astrotomic/laravel-translatable) |
| Полная локализация: модели + UI + CMS + управление языками | **Этот пакет** |
| Только UI-строки в БД | Рассмотрите [spatie/laravel-translation-loader](https://packagist.org/packages/spatie/laravel-translation-loader) |

Подробное сравнение: [competitive-analysis.ru.md](competitive-analysis.ru.md) ([en](competitive-analysis.md) | [de](competitive-analysis.de.md) | [zh](competitive-analysis.zh.md)).

## Тестирование

```bash
vendor/bin/pest               # 124 теста, покрытие 97.4%
```

## Лицензия

MIT. Подробности в [LICENSE.md](../LICENSE.md).
