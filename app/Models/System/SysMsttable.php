<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class SysMsttable extends Model
{
    protected $connection = 'run';

    protected $table = 'sys_msttable';

    public $timestamps = false;
}
