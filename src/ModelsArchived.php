<?php

namespace Nexusbrother\Archivable;

class ModelsArchived
{
    /**
     * The class name of the model that was archived.
     *
     * @var string
     */
    public $model;

    /**
     * The number of archived records.
     *
     * @var int
     */
    public $count;

    /**
     * Create a new event instance.
     *
     * @param  string  $model
     * @param  int  $count
     * @return void
     */
    public function __construct($model, $count)
    {
        $this->model = $model;
        $this->count = $count;
    }
}
