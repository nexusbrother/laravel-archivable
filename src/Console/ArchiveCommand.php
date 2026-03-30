<?php

namespace Nexusbrother\Archivable\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Finder\Finder;
use Nexusbrother\Archivable\Archivable;
use Nexusbrother\Archivable\ModelsArchived;

class ArchiveCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'model:archive
                                {--model=* : Class names of the models to be archivable}
                                {--except=* : Class names of the models to be excluded from archivable}
                                {--chunk=1000 : The number of models to retrieve per chunk of models to be archivable}
                                {--pretend : Display the number of Archivable records found instead of archivable them}';

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
            $this->components->info('No archiveAble models found.');

            return;
        }

        if ($this->option('pretend')) {
            $models->each(function ($model) {
                $this->pretendToArchive($model);
            });

            return;
        }

        $archiving = [];

        $events->listen(ModelsArchived::class, function ($event) use (&$archiving) {
            if (!in_array($event->model, $archiving)) {
                $archiving[] = $event->model;

                $this->newLine();

                $this->components->info(sprintf('archiving [%s] records.', $event->model));
            }

            $this->components->twoColumnDetail($event->model, "{$event->count} records");
        });

        $models->each(function ($model) {
            $this->archiveModel($model);
        });

        $events->forget(ModelsArchived::class);
    }

    /**
     * Archive the given model.
     *
     * @return void
     */
    protected function archiveModel(string $model)
    {
        $instance = new $model;

        $chunkSize = property_exists($instance, 'archivableChunkSize')
            ? $instance->archivableChunkSize
            : $this->option('chunk');

        $total = $this->isArchivable($model)
            ? $instance->archiveAll($chunkSize)
            : 0;

        if ($total == 0) {
            $this->components->info("No Archivable [$model] records found.");
        }
    }

    /**
     * Determine the models that should be archived.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function models()
    {
        if (!empty($models = $this->option('model'))) {
            return collect($models)->filter(function ($model) {
                return class_exists($model);
            })->values();
        }

        $except = $this->option('except');

        if (!empty($models) && !empty($except)) {
            throw new InvalidArgumentException('The --models and --except options cannot be combined.');
        }

        return collect((new Finder)->in($this->getDefaultPath())->files()->name('*.php'))
            ->map(function ($model) {
                $namespace = $this->laravel->getNamespace();

                return $namespace . str_replace(
                    ['/', '.php'],
                    ['\\', ''],
                    Str::after($model->getRealPath(), realpath(app_path()) . DIRECTORY_SEPARATOR)
                );
            })->when(!empty($except), function ($models) use ($except) {
                return $models->reject(function ($model) use ($except) {
                    return in_array($model, $except);
                });
            })->filter(function ($model) {
                return $this->isArchivable($model);
            })->filter(function ($model) {
                return class_exists($model);
            })->values();
    }

    /**
     * Get the default path where models are located.
     *
     * @return string|string[]
     */
    protected function getDefaultPath()
    {
        return config('archive.model_paths');
    }

    /**
     * Determine if the given model class is Archivable.
     *
     * @param  string  $model
     * @return bool
     */
    protected function isArchivable($model)
    {
        $uses = class_uses_recursive($model);

        $usedArchivable = array_intersect([Archivable::class], $uses);

        return !empty($usedArchivable);
    }

    /**
     * Display how many models will be archived.
     *
     * @param  string  $model
     * @return void
     */
    protected function pretendToArchive($model)
    {
        $instance = new $model;

        $count = $instance->archivable()->count();

        if ($count === 0) {
            $this->components->info("No Archivable [$model] records found.");
        } else {
            $this->components->info("{$count} [{$model}] records will be archived.");
        }
    }
}
