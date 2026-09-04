<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class SysitcLogin extends Model
{
    protected $connection = 'run';

    protected $table = 'sysitc_login';

    public $timestamps = false;

    protected $primaryKey = 'rec_id';

    public $incrementing = false;

    protected $keyType = 'int';
}
