<?php

namespace Nexusbrother\Archivable\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class UserModel extends Model
{
    protected $table    = 'users';
    protected $fillable = [
        'test_model_id',
    ];
}
