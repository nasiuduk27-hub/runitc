<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class SysProvinsi extends Model
{
    protected $connection = 'run';

    protected $table = 'sys_provinsi';

    public $timestamps = false;

    protected $primaryKey = 'rec_id';

    public $incrementing = false;

    protected $keyType = 'int';
}
