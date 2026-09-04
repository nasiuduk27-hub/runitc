<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class SysKota extends Model
{
    protected $connection = 'run';

    protected $table = 'sys_kota';

    public $timestamps = false;

    protected $primaryKey = 'rec_id';

    public $incrementing = false;

    protected $keyType = 'int';
}
