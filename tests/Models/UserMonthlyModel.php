<?php

namespace Nexusbrother\Archivable\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class UserMonthlyModel extends Model
{
    protected $table = 'user_monthly_models';

    protected $fillable = [
        'monthly_test_model_id',
    ];
}
