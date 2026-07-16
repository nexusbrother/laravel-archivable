<?php

namespace Nexusbrother\Archivable\Console;

use Illuminate\Contracts\Events\Dispatcher;

class TableStructureSyncCommand extends ArchiveCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'model:archive-structure-sync
                                {--model=* : Class names of the models to be archivable}
                                {--except=* : Class names of the models to be excluded from archivable}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync archive table structures from source models';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(Dispatcher $events)
    {
        $models = $this->models();

        if ($models->isEmpty()) {
            $this->components->info('No archiveAble models found.');

            return;
        }

        $models->each(function ($model) {
            $model = new $model;
            $model->syncStructure($this->output);
        });
    }
}
