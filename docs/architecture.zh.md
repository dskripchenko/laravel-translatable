# 架构

> dskripchenko/laravel-translatable -- 技术参考

## 概述

本包为 Laravel 提供数据库支持的翻译功能，包含三个功能层：

1. **模型翻译** -- 任何 Eloquent 模型通过 `TranslationTrait` 获得多语言字段
2. **UI 字符串加载器** -- `DatabaseTranslationLoader` 替换 Laravel 基于文件的 `__()` / `trans()`
3. **CMS 内容块** -- `ContentBlockService` 管理与页面绑定的可翻译内容块

所有层共享一个带有多态绑定的 `translations` 表。

## 文件结构

```
src/
├── Console/
│   ├── ExportCommand.php            # translatable:export {locale}
│   ├── ImportCommand.php            # translatable:import {file}
│   └── ScanCommand.php             # translatable:scan --path=...
├── Events/
│   ├── TranslationCreated.php       # 首次访问时触发（auto_create）
│   └── TranslationUpdated.php       # 内容变更时触发（含 oldContent）
├── Http/Middleware/
│   └── DetectLanguage.php           # 从 URL/Cookie/Header 自动检测语言环境
├── Loaders/
│   └── DatabaseTranslationLoader.php  # 装饰 FileLoader，叠加数据库翻译
├── Models/
│   ├── Language.php                 # 语言模型，含静态缓存 + resetStaticCache()
│   ├── Translation.php              # 翻译记录（通过 entity/entity_id 多态绑定）
│   ├── ContentBlock.php             # CMS 块，使用 TranslationTrait
│   ├── Page.php                     # 页面（按 URI 自动创建），与 ContentBlock 多对多
│   └── PageContentBlock.php         # 中间表模型（无时间戳）
├── Providers/
│   └── TranslatableServiceProvider.php  # 迁移、配置、命令、加载器、中间件别名
├── Services/
│   ├── TranslationService.php       # 核心：缓存、getTranslation、回退、批量、复数
│   └── ContentBlockService.php      # CMS：inline/global/begin-end、页面绑定
└── Traits/
    └── TranslationTrait.php         # t()、tc()、saveTranslation(s)、作用域

config/translatable.php              # auto_create、fallback_locale、database_loader、tables
databases/migrations/                # 5 个表（所有名称可通过 env 配置）
tests/Feature/                       # 124 个测试，覆盖率 97.4%
```

## 数据库架构

所有表名可通过 `config/translatable.php` 和 `TRANSLATABLE_*_TABLE` 环境变量配置。

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

### `translations` 行的使用方式

| 使用场景 | group | entity | entity_id | 示例 |
|----------|-------|--------|-----------|------|
| 模型字段 | `'field'` | `App\Models\Product` | `'42'` | `$product->t('name')` |
| UI 字符串 | `'messages'` | `''` | `''` | `__('messages.welcome')` |
| JSON 字符串 | `'*'` | `''` | `''` | `__('Welcome')` |
| CMS 块 | `'field'` | `...ContentBlock` | `'7'` | `$cms->inline('hero.title', ...)` |
| 通用 | `'default'` | `''` | `''` | `TranslationService::getTranslation('key')` |

## 组件交互

```
请求
  │
  ├─[中间件: DetectLanguage]──→ app()->setLocale()
  │
  ├─[视图: __('messages.hello')]
  │     └─→ DatabaseTranslationLoader
  │           ├─ FileLoader::load()        ← 文件翻译
  │           └─ Translation::where(group='messages', entity='')  ← 数据库叠加
  │
  ├─[控制器: $product->t('name')]
  │     └─→ TranslationTrait::t()
  │           └─→ TranslationService::getTranslation()
  │                 ├─ boot() → bootLanguage()     ← 按语言延迟加载
  │                 ├─ $cache[lang][key] 命中？     ← 第 1 级：内存
  │                 ├─ Cache::tags()->remember()    ← 第 2 级：Redis/Memcached
  │                 ├─ findFallback()               ← 尝试回退语言
  │                 └─ firstOrCreate()              ← 启用时自动创建
  │                       └─→ TranslationCreated 事件
  │
  └─[视图: $cms->inline('hero.title', ...)]
        └─→ ContentBlockService::inline()
              ├─ get() → ContentBlock::firstOrCreate()
              ├─ getCurrentPage() → Page::firstOrCreate(uri)
              ├─ Page::link(block)
              ├─ $block->t('content')  → TranslationService
              └─ str_replace({占位符})
```

## 缓存策略

### 两级缓存

| 级别 | 存储 | 生存时间 | 范围 |
|------|------|----------|------|
| 1 | `TranslationService::$cache`（静态数组） | 单次请求 | 进程内 |
| 2 | `Cache::tags(['translation_static_cache'])` | `config('cache.translation_ttl')` | 共享（Redis） |

### 缓存键格式

```
translation_static_cache_{language_code}_{hash}
```

其中 `hash` = `config('cache.translation_hash')`。部署时更改此值以清除整个缓存。

### 失效

- `TranslationService::refresh()` -- 更新内存缓存 + 对特定语言键执行 `Cache::forget()`
- `Language::resetStaticCache()` -- 清除 Language 模型缓存（用于长运行进程）
- `TranslationService::$cache = null` -- 重置内存翻译缓存
- `ContentBlockService::$cache = null` -- 重置内容块缓存

### 延迟加载

`boot()` 初始化空的 `$cache` 数组。`bootLanguage($language)` 仅在首次访问时加载特定语言的翻译。未使用的语言永远不会被加载。

## 回退链

当 `auto_create` 禁用且翻译缺失时：

```
请求的语言（例如 'fr'）
    │ 未找到
    ▼
回退语言（配置：translatable.fallback_locale 或 app.fallback_locale）
    │ 未找到
    ▼
默认值（作为参数传给 t() 或 getTranslation()）
```

当 `auto_create` 启用时，会在请求的语言中创建新记录，使用默认值。不会查询回退语言 -- 新记录具有权威性。

## 配置参考

```php
// config/translatable.php
'auto_create'     => true,   // 首次访问时创建翻译记录
'fallback_locale' => null,   // null = 使用 config('app.fallback_locale')
'database_loader' => false,  // 用数据库叠加替换 Laravel 的 FileLoader
'tables' => [...]            // 所有 5 个表名，可通过 env 覆盖

// config/cache.php（由应用程序设置）
'translation_hash' => 'v1',  // 缓存版本键（部署时更改）
'translation_ttl'  => 3600,  // 持久缓存 TTL（秒）
```

## 跨数据库兼容性

本包在 MySQL、PostgreSQL 和 SQLite 上行为一致：

- `entity` / `entity_id` 为 NOT NULL，`default('')` -- 避免所有数据库上的 NULL-in-UNIQUE 问题
- `Language::byCode()` 使用 `mb_strtolower()` 进行大小写不敏感查找（PostgreSQL 默认区分大小写）
- `orderByTranslation` 使用驱动特定的 CAST（MySQL 用 CHAR，PostgreSQL 用 VARCHAR，SQLite 用 TEXT）
- 除标准 CAST 表达式外无原始 SQL
