# Laravel Archivable

Laravel Archivable 是一个强大的 Laravel 扩展包，用于数据库记录归档，它能够将不需要的记录从主数据库移动到归档数据库，同时保持表结构同步。

## 功能特性

- 将模型记录归档到指定的归档数据库
- 自动同步主数据库和归档数据库之间的表结构
- 支持批量归档操作，可配置分块大小
- 提供命令行工具进行批量归档和表结构同步
- 支持自定义归档条件
- 支持基于日期字段的自动归档
- 支持按月自动分表归档
- 提供归档事件监听

## 安装

使用 Composer 安装包：

```bash
composer require nexusbrother/laravel-archivable
```

发布配置文件：

```bash
php artisan vendor:publish --provider="Nexusbrother\Archivable\ServiceProvider" --tag="config"
```

## 配置

在你的 .env 文件中配置归档数据库连接：

```env
ARCHIVE_DB_CONNECTION=archive
```

确保在 config/database.php 中定义了 archive 数据库连接：

```php
'connections' => [
    // 其他连接...
    
    'archive' => [
        'driver' => 'mysql',
        'host' => env('ARCHIVE_DB_HOST', '127.0.0.1'),
        'port' => env('ARCHIVE_DB_PORT', '3306'),
        'database' => env('ARCHIVE_DB_DATABASE', 'archive'),
        'username' => env('ARCHIVE_DB_USERNAME', 'root'),
        'password' => env('ARCHIVE_DB_PASSWORD', ''),
        'unix_socket' => env('ARCHIVE_DB_SOCKET', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ],
],
```

## 配置项

在 config/archive.php 中可以配置以下选项：

```php
return [
    // 默认分块大小
    'default_chunk_size' => 1000,

    // 归档数据库连接
    'db' => env('ARCHIVE_DB_CONNECTION', 'archive'),

    // 模型扫描路径
    'model_paths' => [
        app_path('Models'),
    ],
];
```

## 使用方法

### 1. 基本归档功能

在模型中使用 Archivable Trait：

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Nexusbrother\Archivable\Archivable;

class User extends Model
{
    use Archivable;
    
    /**
     * 定义可归档的记录条件
     * 
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function archivable()
    {
        // 例如：归档超过一年未活跃的用户
        return $this->where('last_login_at', '<', now()->subYear());
    }
}
```

### 2. 基于日期字段的归档

使用 DateFieldArchivable Trait 实现基于日期字段的自动归档：

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Nexusbrother\Archivable\DateFieldArchivable;

class Order extends Model
{
    use DateFieldArchivable;
    
    /**
     * 获取日期字段名称
     * 
     * @return string
     */
    public function getArchiveDateField()
    {
        return 'created_at';
    }
    
    /**
     * 获取归档时间间隔
     * 
     * @return \DateInterval
     */
    public function getArchiveInterval()
    {
        return new \DateInterval('P1Y'); // 一年
    }
}
```

### 3. 按月分表归档

使用 MonthlyArchivable Trait 实现按月自动分表归档：

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Nexusbrother\Archivable\MonthlyArchivable;

class Log extends Model
{
    use MonthlyArchivable;
    
    /**
     * 获取日期字段名称
     * 
     * @return string
     */
    public function getArchiveDateField()
    {
        return 'created_at';
    }
    
    /**
     * 获取归档时间间隔（月数）
     * 
     * @return int
     */
    public function getArchiveMonths()
    {
        return 6; // 6个月前的记录将被归档
    }
}
```

### 4. 同步表结构

在归档记录之前，确保归档数据库中存在对应的表结构：

```bash
php artisan model:archive-structure-sync
```

也可以指定特定的模型：

```bash
php artisan model:archive-structure-sync --model="App\Models\User" --model="App\Models\Order"
```

### 5. 归档记录

使用命令行归档记录：

```bash
php artisan model:archive
```

指定特定的模型：

```bash
php artisan model:archive --model="App\Models\User" --model="App\Models\Order"
```

设置分块大小：

```bash
php artisan model:archive --chunk=500
```

仅预览将归档的记录数量：

```bash
php artisan model:archive --pretend
```

### 6. 编程方式归档

```php
// 归档单个记录
$user->archive();

// 归档符合条件的所有记录
$user->archiveAll($chunkSize = 1000);
```

## 事件

当模型记录被归档时，会触发 `Nexusbrother\Archivable\ModelsArchived` 事件，你可以监听这个事件来执行自定义逻辑：

```php
use Illuminate\Support\Facades\Event;
use Nexusbrother\Archivable\ModelsArchived;

Event::listen(ModelsArchived::class, function ($event) {
    // $event->model 包含被归档的模型类名
    // $event->count 包含被归档的记录数量
    
    // 执行自定义逻辑
});
```

## 要求

- PHP 8.1 或更高版本
- Laravel 9.0、10.0 或 11.0

## 许可证

该项目使用 MIT 许可证 - 详情请参阅 LICENSE 文件

## 贡献

欢迎贡献代码！请提交 Pull Request 或创建 Issue。