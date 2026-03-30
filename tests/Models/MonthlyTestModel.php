<?php

namespace Nexusbrother\Archivable\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Nexusbrother\Archivable\MonthlyArchivable;

class MonthlyTestModel extends Model
{
    use MonthlyArchivable;
    protected $table = 'monthly_test_models';
}
