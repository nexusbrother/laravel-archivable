<?php

namespace Nexusbrother\Archivable;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Nexusbrother\Archivable\Console\ArchiveCommand;
use Nexusbrother\Archivable\Console\TableStructureSyncCommand;

class ServiceProvider extends BaseServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // 加载配置文件
        $this->mergeConfigFrom(
            __DIR__ . '/config/archive.php',
            'archive'
        );
        $this->registerCommands();
    }

    /**
     * Register the console commands for the package.
     *
     * @return void
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ArchiveCommand::class,
                TableStructureSyncCommand::class,
            ]);
        }

        // 注册定时任务调度（关键：向 Laravel 项目的调度器添加任务）
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            // 定义任务执行频率（例如每天凌晨3点）
            $schedule->command('model:archive-structure-sync')
                ->dailyAt(config('archive.schedule_daily_at.archive_structure_sync'))
                ->when(function () {
                    return config('archive.enable');
                })
                ->name('同步注册了archive的model的表结构')
                ->onOneServer()
                ->withoutOverlapping();
            $schedule->command('model:archive')
                ->dailyAt(config('archive.schedule_daily_at.archive'))
                ->when(function () {
                    return config('archive.enable');
                })->name('归档注册了archive的model的数据')
                ->onOneServer()
                ->withoutOverlapping();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            ArchiveCommand::class,
        ];
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        // 发布配置文件
        $this->publishes([
            __DIR__ . '/config/archive.php' => config_path('archive.php'),
        ], 'config');
    }
}
