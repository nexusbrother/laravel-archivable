<?php

namespace Nexusbrother\Archivable\Console;

use Illuminate\Contracts\Events\Dispatcher;
use Nexusbrother\Archivable\ArchivableTableStructureSync;
use Nexusbrother\Archivable\ModelsArchived;

class TableStructureSyncCommand extends ArchiveCommand
{
    use ArchivableTableStructureSync;

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
    protected $description = 'Archive models that are no longer needed';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(Dispatcher $events)
    {
        $models = $this->models();

        if ($models->isEmpty()) {
            $this->output->info('No archiveAble models found.');

            return;
        }

        $models->each(function ($model) {
            $model = new $model;
            $model->syncStructure($this->output);
        });

        $events->forget(ModelsArchived::class);
    }
}
