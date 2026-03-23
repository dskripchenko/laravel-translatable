# 竞品分析

> Laravel 翻译包比较，2026 年 3 月。

## 模型翻译方法

| 方法 | 存储方式 | 包 | 下载量 |
|------|---------|-----|--------|
| **JSON 列** | JSON 存在模型自身表中 | spatie/laravel-translatable | ~22.8M |
| **每模型独立表** | `{model}_translations` 表 | astrotomic/laravel-translatable | ~7.6M |
| **单一翻译表** | 一个 `translations` 表，多态绑定 | **dskripchenko/laravel-translatable** | -- |

另有仅用于 **UI 字符串**（非模型）的包：spatie/laravel-translation-loader (~2.8M)、joedixon/laravel-translation (~500K)。

## 详细比较

### spatie/laravel-translatable

**下载量：** ~22.8M | **Stars：** 2426 | **Laravel：** 11-13

将翻译作为 JSON 存储在模型表的列中。最简单的设置 -- 一个 trait，一个 JSON 列，无额外表。

**优势：** 零查询开销（翻译随模型加载），`whereLocale()` / `whereJsonContainsLocale()` 作用域，庞大的社区。

**局限：** JSON 不能高效索引以进行全文搜索，无规范化（所有语言在一个字段中），无集中翻译管理，无缓存层，每次模型查询加载所有语言。

### astrotomic/laravel-translatable

**下载量：** ~7.6M | **Stars：** 1392 | **Laravel：** 9-12

为每个可翻译模型创建独立的 `{model}_translations` 表。每个翻译字段获得自己的类型化列。

**优势：** 使用标准索引的完全规范化，类型化列（string、text 等），回退语言链，通过 `withTranslation()` 基于 JOIN 的预加载。

**局限：** 表爆炸（每个模型 = +1 表 + 1 PHP 类），每次字段变更需额外迁移，无内置缓存，初始设置更复杂，维护较不活跃（从已停止的 dimsav fork）。

### lexi-translate

**下载量：** <10K | **Stars：** ~30 | **Laravel：** 10-11

使用单一 `translations` 表和 morph 关系 -- 架构上类似于 dskripchenko。

**优势：** 单一表，标准 morph 模式，内置缓存，回退语言。

**局限：** 新项目，不支持 Laravel 12+，文档有限。

### spatie/laravel-translation-loader

**下载量：** ~2.8M | **Stars：** 835 | **Laravel：** 6-13

用数据库后端替换 Laravel 基于文件的翻译加载器。无需代码更改即可与 `__()`, `trans()`, `@lang()` 配合使用。

**优势：** 与 Laravel 翻译辅助函数完全兼容，数据库覆盖文件翻译，可扩展（自定义 YAML/CSV 提供者）。

**局限：** 仅 UI 字符串 -- 无模型翻译，无语言管理。

### joedixon/laravel-translation

**下载量：** ~500K | **Stars：** ~600

带有 Web UI、扫描器和数据库驱动的完整翻译管理系统。

**优势：** 内置 Web 编辑器，缺失翻译扫描器，文件 + 数据库驱动，artisan 命令。

**局限：** 仅 UI 字符串，维护较不活跃，依赖较重。

## 功能矩阵

| 功能 | spatie | astrotomic | lexi | spatie-loader | joedixon | **dskripchenko** |
|------|:------:|:----------:|:----:|:------------:|:--------:|:----------------:|
| 模型字段翻译 | JSON | 每模型表 | Morph | -- | -- | **Morph** |
| UI 字符串 `__()` / `trans()` | -- | -- | -- | 是 | 是 | **是** |
| 回退语言 | -- | 是 | 是 | -- | -- | **是** |
| 缓存 | -- | -- | 是 | -- | -- | **两级** |
| 查询作用域 | JSON where | JOIN | -- | -- | -- | **whereTranslation / orderBy** |
| 复数形式 | -- | -- | -- | Laravel 原生 | Laravel 原生 | **tc() + MessageSelector** |
| 事件 | -- | -- | -- | -- | -- | **Created / Updated** |
| 批量操作 | -- | -- | 是 | -- | -- | **setTranslations** |
| Artisan CLI | -- | -- | -- | -- | 是 | **export / import / scan** |
| 中间件 | -- | -- | -- | -- | -- | **DetectLanguage** |
| 语言管理 | -- | -- | -- | -- | -- | **Language 模型** |
| CMS 内容块 | -- | -- | -- | -- | -- | **ContentBlockService** |
| 页面-块绑定 | -- | -- | -- | -- | -- | **Page <-> ContentBlock** |
| 参数替换 | -- | -- | -- | -- | -- | **inline() 中的 {placeholder}** |
| 输出缓冲 | -- | -- | -- | -- | -- | **begin() / end()** |
| 访问时自动创建 | -- | -- | -- | -- | -- | **auto_create** |
| 可配置表名 | -- | -- | -- | -- | -- | **env + config** |
| 路由模式辅助 | -- | -- | -- | -- | -- | **getRouteGroupPattern()** |

## 何时选择哪个包

| 情况 | 建议 |
|------|------|
| 小型项目，2-3 个可翻译模型，最少配置 | **spatie/laravel-translatable** -- 零开销的 JSON 列。当翻译数据量小且不需要集中管理时，这是最简单有效的方案。 |
| 大型项目，翻译字段上有复杂查询，严格类型要求 | **astrotomic/laravel-translatable** -- 规范化的每模型表提供对翻译列的完整 SQL 能力和正确类型。 |
| 完整本地化栈：模型 + UI + CMS + 语言管理 + 缓存 | **dskripchenko/laravel-translatable** -- 一个包覆盖所有层。在需要自动创建、内容块且不想集成多个包时特别有优势。 |
| 仅从数据库获取 UI 字符串，现有代码库使用 `__()` | **spatie/laravel-translation-loader** -- 无需代码更改即可透明替换基于文件的翻译。 |
| 需要面向非技术团队的可视化翻译编辑器 | **joedixon/laravel-translation** -- 自带 Web 界面。 |
| 组合使用 | 可以将 **dskripchenko** 与 **spatie/translatable** 一起使用 -- 某些模型适合 JSON 存储（如简单配置字段），其他需要集中翻译表。两个包不冲突。 |

来源：
- [spatie/laravel-translatable -- Packagist](https://packagist.org/packages/spatie/laravel-translatable)
- [astrotomic/laravel-translatable -- Packagist](https://packagist.org/packages/astrotomic/laravel-translatable)
- [spatie/laravel-translation-loader -- Packagist](https://packagist.org/packages/spatie/laravel-translation-loader)
- [lexi-translate -- GitHub](https://github.com/omaralalwi/lexi-translate)
- [joedixon/laravel-translation -- GitHub](https://github.com/joedixon/laravel-translation)
