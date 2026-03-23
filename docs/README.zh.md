# Laravel Translatable

一个全面的 Laravel 本地化包，将**模型翻译**、**数据库 UI 字符串**和 **CMS 内容块**统一在单一解决方案中。

翻译存储在数据库中，支持两级缓存（内存 + Redis/Memcached）、自动创建记录、回退语言链，以及与 Laravel `__()` / `trans()` 辅助函数的完整集成。

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](../LICENSE.md)

> [English README](../README.md) | [README на русском](README.ru.md) | [README auf Deutsch](README.de.md)

## 为什么选择这个包？

大多数 Laravel 翻译包只解决单一问题。如果您需要多语言模型、来自数据库的 UI 字符串和 CMS 内容，通常需要安装 2-3 个独立的包。本包在统一架构中提供所有功能：

| 功能 | spatie | astrotomic | **本包** |
|------|:---:|:---:|:---:|
| 模型字段翻译 | JSON 列 | 每个模型独立表 | 单一 `translations` 表 |
| UI 字符串 (`__()` / `trans()`) | - | - | 内置 DatabaseLoader |
| CMS 内容块 | - | - | ContentBlockService |
| 两级缓存 | - | - | 内存 + Cache::tags |
| 回退语言 | - | 是 | 是 |
| 查询作用域 | JSON where | JOIN | whereTranslation / orderBy |
| 事件 | - | - | Created / Updated |
| Artisan CLI | - | - | export / import / scan |
| 语言检测中间件 | - | - | DetectLanguage |
| 复数形式 | - | - | tc() + MessageSelector |
| 批量操作 | - | - | setTranslations / saveTranslations |

## 系统要求

- PHP 8.1+
- Laravel 11 或 12
- 支持标签的缓存驱动（Redis、Memcached 或 Array）
- MySQL 5.7+ / PostgreSQL 12+ / SQLite 3.35+

## 安装

```bash
composer require dskripchenko/laravel-translatable
```

包使用 Laravel 自动发现。运行迁移：

```bash
php artisan migrate
```

## 快速入门

### 1. 创建语言

```php
use Dskripchenko\LaravelTranslatable\Models\Language;

Language::create(['code' => 'zh', 'label' => '中文', 'is_active' => true, 'as_locale' => true]);
Language::create(['code' => 'en', 'label' => 'English', 'is_active' => true]);
Language::create(['code' => 'ja', 'label' => '日本語', 'is_active' => true]);
```

### 2. 翻译模型字段

将 `TranslationTrait` 添加到任何 Eloquent 模型：

```php
use Dskripchenko\LaravelTranslatable\Traits\TranslationTrait;

class Product extends Model
{
    use TranslationTrait;
}
```

读写翻译：

```php
$product = Product::find(1);

// 读取（使用当前应用语言环境）
$product->t('name');
$product->t('name', '默认名称');

// 读取指定语言
$product->t('name', null, Language::byCode('en'));

// 写入
$product->saveTranslation('name', '产品名称', Language::byCode('zh'));

// 批量写入多个字段
$product->saveTranslations([
    'name' => '产品名称',
    'description' => '产品描述',
], Language::byCode('zh'));
```

### 3. 复数形式

使用 Laravel 格式存储复数内容：

```php
// 数据库中: "{0} 没有商品|{1} 一件商品|[2,*] :count 件商品"
$product->tc('items_label', 0);                // "没有商品"
$product->tc('items_label', 1);                // "一件商品"
$product->tc('items_label', 5, ['count' => 5]); // "5 件商品"
```

### 4. 按翻译查询

```php
// 按翻译名称查找产品
Product::whereTranslation('name', '笔记本电脑')->get();
Product::whereTranslation('name', 'like', '%笔记本%', 'zh')->get();

// 按翻译字段排序
Product::orderByTranslation('name', 'asc', 'zh')->get();
```

### 5. CMS 内容块

管理与页面关联的可翻译内容块：

```php
use Dskripchenko\LaravelTranslatable\Services\ContentBlockService;

$cms = new ContentBlockService();

// 文本块（自动关联到当前页面）
echo $cms->inline('hero.title', '标题', '欢迎访问我们的网站');

// 带参数替换
echo $cms->inline('greeting', '问候语', '你好，{name}！', ['name' => $user->name]);

// 全局块（不绑定页面）
echo $cms->global('site.name', '网站名称', '我的应用');
```

### 6. 数据库 UI 字符串

启用数据库翻译加载器以使用 `__()` 和 `trans()`：

```env
TRANSLATABLE_DATABASE_LOADER=true
```

现在 `__('messages.welcome')` 会先检查数据库，然后回退到语言文件。当管理员需要在不部署代码的情况下编辑 UI 文本时，这非常实用。

## 配置

```php
// config/translatable.php
return [
    'auto_create'     => env('TRANSLATABLE_AUTO_CREATE', true),      // 自动创建翻译
    'fallback_locale' => env('TRANSLATABLE_FALLBACK_LOCALE'),        // 回退语言
    'database_loader' => env('TRANSLATABLE_DATABASE_LOADER', false), // __() 数据库加载器
    'tables' => [ /* 可自定义表名 */ ],
];
```

缓存参数在 `config/cache.php` 中设置：

```php
'translation_hash' => env('TRANSLATION_CACHE_HASH', 'v1'), // 部署时更改以清除缓存
'translation_ttl'  => env('TRANSLATION_CACHE_TTL', 3600),  // 缓存生存时间（秒）
```

## 中间件

从 URL、Cookie 或 `Accept-Language` 头自动检测语言：

```php
Route::middleware('translatable.detect')->group(function () {
    // 语言自动检测并设置
});
```

优先级：路由参数 `{locale}` > 第一个 URL 段 > Cookie `locale` > `Accept-Language` 头。

## Artisan 命令

```bash
php artisan translatable:export zh --output=translations-zh.json  # 导出
php artisan translatable:import translations-zh.json --locale=zh  # 导入
php artisan translatable:import translations.json --dry-run       # 预览
php artisan translatable:scan --path=app,resources                # 扫描代码
```

## 事件

```php
use Dskripchenko\LaravelTranslatable\Events\TranslationCreated;
use Dskripchenko\LaravelTranslatable\Events\TranslationUpdated;

Event::listen(TranslationCreated::class, fn ($e) => Log::info("已创建: {$e->translation->key}"));
Event::listen(TranslationUpdated::class, fn ($e) => Log::info("已更新: {$e->translation->key}"));
```

## 架构

所有翻译存储在单一 `translations` 表中，通过 `entity` / `entity_id` 字段实现多态绑定。一个表服务于模型翻译、UI 字符串和 CMS 块。

**缓存**为两级：静态内存缓存（每请求）+ 通过 `Cache::tags`（Redis/Memcached）的持久缓存。翻译在首次访问时按语言延迟加载。

详细技术文档：[architecture.zh.md](architecture.zh.md)（[en](architecture.md) | [ru](architecture.ru.md) | [de](architecture.de.md)）。

## 何时选择本包

| 您的情况 | 建议 |
|----------|------|
| 小型项目，2-3 个模型，最少配置 | 考虑 [spatie/laravel-translatable](https://packagist.org/packages/spatie/laravel-translatable) |
| 翻译字段的复杂查询，严格类型 | 考虑 [astrotomic/laravel-translatable](https://packagist.org/packages/astrotomic/laravel-translatable) |
| 完整本地化：模型 + UI + CMS + 语言管理 | **本包** |
| 仅数据库中的 UI 字符串 | 考虑 [spatie/laravel-translation-loader](https://packagist.org/packages/spatie/laravel-translation-loader) |

详细比较：[competitive-analysis.zh.md](competitive-analysis.zh.md)（[en](competitive-analysis.md) | [ru](competitive-analysis.ru.md) | [de](competitive-analysis.de.md)）。

## 测试

```bash
vendor/bin/pest               # 124 个测试，覆盖率 97.4%
```

## 许可证

MIT。详情见 [LICENSE.md](../LICENSE.md)。
