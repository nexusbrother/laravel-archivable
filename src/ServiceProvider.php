<?php

namespace Nexusbrother\Archivable;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Nexusbrother\Archivable\Console\ArchiveCommand;
use Nexusbrother\Archivable\Console\TableStructureSyncCommand;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/config/archive.php',
            'archive'
        );
        $this->registerCommands();
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/archive.php' => config_path('archive.php'),
        ], 'config');

        $this->registerSchedule();
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
    }

    /**
     * Register the scheduled tasks.
     *
     * @return void
     */
    protected function registerSchedule()
    {
        if (config('archive.enable')) {
            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);

                $schedule->command('model:archive-structure-sync')
                    ->dailyAt(config('archive.schedule_daily_at.archive_structure_sync'))
                    ->name('Sync archive table structures')
                    ->onOneServer()
                    ->runInBackground()
                    ->withoutOverlapping(720);

                $schedule->command('model:archive')
                    ->dailyAt(config('archive.schedule_daily_at.archive'))
                    ->name('Archive old model data')
                    ->onOneServer()
                    ->runInBackground()
                    ->withoutOverlapping(720);
            });
        }
    }
}
