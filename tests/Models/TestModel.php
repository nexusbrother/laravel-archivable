<?php

namespace Nexusbrother\Archivable\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Nexusbrother\Archivable\DateFieldArchivable;

class TestModel extends Model
{
    use DateFieldArchivable;

    protected $table = 'test_models';

    public function getDestinationTable()
    {
        return 'test_models_' . now()->format('Ym');
    }
}
