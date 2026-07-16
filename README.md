# Laravel Archivable

Laravel Archivable 是一个数据库记录归档扩展包，能够将过期记录从主库自动迁移到独立的归档数据库，同时保持表结构同步。

## 功能特性

- 📦 将模型记录归档到独立的归档数据库
- 🔄 自动同步主库与归档库之间的表结构（新增/修改字段、索引）
- 📅 支持基于日期字段的自动归档（`DateFieldArchivable`）
- 🗓️ 支持按月自动分表归档（`MonthlyArchivable`）
- ⚡ 批量归档，可配置 chunk 大小，支持外键约束自动处理
- 📊 命令行归档时显示实时进度条
- 🔔 归档完成触发 `ModelsArchived` 事件，方便自定义后续逻辑
- ⏰ 内置定时任务调度，可配置执行时间

## 安装

```bash
composer require nexusbrother/laravel-archivable
```

发布配置文件：

```bash
php artisan vendor:publish --provider="Nexusbrother\Archivable\ServiceProvider" --tag="config"
```

## 配置

### 1. 数据库连接

在 `config/database.php` 的 `connections` 中添加 `archive` 连接：

```php
'archive' => [
    'driver'   => 'mysql',
    'host'     => env('ARCHIVE_DB_HOST', '127.0.0.1'),
    'port'     => env('ARCHIVE_DB_PORT', '3306'),
    'database' => env('ARCHIVE_DB_DATABASE', 'archive'),
    'username' => env('ARCHIVE_DB_USERNAME', 'root'),
    'password' => env('ARCHIVE_DB_PASSWORD', ''),
    'charset'  => 'utf8mb4',
    'collation'=> 'utf8mb4_unicode_ci',
    'prefix'   => '',
    'strict'   => true,
    'engine'   => null,
],
```

### 2. 环境变量

```env
# 启用定时归档任务（默认 false）
ARCHIVE_ENABLE=true

# 归档数据库连接名（默认 archive）
ARCHIVE_DB_CONNECTION=archive

# 定时任务执行时间
ARCHIVE_SCHEDULE_STRUCTURE_SYNC_DAILY_AT=09:00
ARCHIVE_SCHEDULE_ARCHIVE_DAILY_AT=09:10
```

### 3. 配置项

`config/archive.php`：

```php
return [
    // 是否启用定时归档任务
    'enable' => env('ARCHIVE_ENABLE', false),

    // 定时任务执行时间
    'schedule_daily_at' => [
        'archive_structure_sync' => '09:00',  // 表结构同步
        'archive'                => '09:10',  // 数据归档
    ],

    // 每批处理的记录数
    'default_chunk_size' => 1000,

    // 归档数据库连接名
    'db' => env('ARCHIVE_DB_CONNECTION', 'archive'),

    // 自动扫描 Archivable 模型的目录
    'model_paths' => [
        app_path('Models'),
    ],
];
```

## 使用方法

### 1. 基于日期字段归档（DateFieldArchivable）

适合按时间归档的场景，所有记录归档到**同一张目标表**：

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Nexusbrother\Archivable\DateFieldArchivable;

class Order extends Model
{
    use DateFieldArchivable;

    /**
     * 可选：自定义日期字段（默认 created_at）
     */
    public function getDateField(): string
    {
        return 'created_at';
    }

    /**
     * 可选：自定义归档时间界限（默认 6 个月前）
     */
    public function getDateLimit()
    {
        return today()->subYear();
    }

    /**
     * 可选：自定义归档目标表名（默认与源表同名）
     */
    public function getDestinationTable(): string
    {
        return $this->getTable() . '_' . now()->format('Ym');
    }
}
```

### 2. 按月分表归档（MonthlyArchivable）

适合日志、消息等按月分区的数据，每个月的记录归档到**独立的月表**（如 `logs_202501`、`logs_202502`）：

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Nexusbrother\Archivable\MonthlyArchivable;

class Log extends Model
{
    use MonthlyArchivable;

    /**
     * 可选：自定义日期字段（默认 created_at）
     */
    public function getDateField(): string
    {
        return 'created_at';
    }

    /**
     * 可选：归档多少个月之前的数据（默认 6 个月）
     */
    public function getArchiveMonthLimit()
    {
        return today()->subMonths(3);
    }
}
```

### 3. 自定义归档条件（Archivable）

完全自定义归档逻辑：

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Nexusbrother\Archivable\Archivable;

class User extends Model
{
    use Archivable;

    /**
     * 定义可归档的记录查询
     */
    public function archivable()
    {
        return $this->where('last_login_at', '<', now()->subYear());
    }

    /**
     * 可选：自定义归档目标表
     */
    public function getDestinationTable(): string
    {
        return 'archived_users';
    }
}
```

## 命令行

### 表结构同步

归档前先同步表结构，确保归档表存在且字段一致：

```bash
# 同步所有 Archivable 模型
php artisan model:archive-structure-sync

# 指定模型
php artisan model:archive-structure-sync --model="App\Models\Order"

# 排除模型
php artisan model:archive-structure-sync --except="App\Models\User"
```

### 数据归档

```bash
# 归档所有已注册模型
php artisan model:archive

# 指定模型
php artisan model:archive --model="App\Models\Order" --model="App\Models\Log"

# 排除模型
php artisan model:archive --except="App\Models\User"

# 自定义 chunk 大小
php artisan model:archive --chunk=500

# 仅预览，不执行
php artisan model:archive --pretend
```

执行时会显示实时进度条：

```
 4532/50000 [▓▓▓░░░░░░░░░░░░░░░░░]  9%
```

## 编程方式使用

```php
// 归档单个记录
$order->archive();

// 归档所有符合条件的记录
$order->archiveAll();

// 自定义 chunk 大小
$order->archiveAll(500);

// 带进度回调（每处理完一个 chunk 触发）
$order->archiveAll(1000, function (int $chunkCount) {
    // 处理了 $chunkCount 条记录
});

// 带开始回调和进度回调
$order->archiveAll(1000, function (int $chunkCount) {
    // 每批完成
}, function (int $total) {
    // 开始归档，共 $total 条待处理
});
```

## 事件

归档完成后触发 `ModelsArchived` 事件：

```php
use Illuminate\Support\Facades\Event;
use Nexusbrother\Archivable\ModelsArchived;

Event::listen(ModelsArchived::class, function (ModelsArchived $event) {
    // $event->model  被归档的模型类名
    // $event->count  归档的记录数量

    Log::info("Archived {$event->count} records from {$event->model}");
});
```

## 定时任务

包内置两个定时任务，在 `config('archive.enable')` 为 `true` 时自动注册：

| 任务 | 默认时间 | 说明 |
|------|---------|------|
| `model:archive-structure-sync` | 09:00 | 同步所有 Archivable 模型的归档表结构 |
| `model:archive` | 09:10 | 执行数据归档 |

> 两个任务均配置了 `onOneServer()` 和 `withoutOverlapping()`，适合多服务器部署。

## 系统要求

- PHP >= 8.1
- Laravel 9 / 10 / 11

## 许可证

MIT License
