<?php

namespace App\Models\Cooperative;

use Illuminate\Database\Eloquent\Model;

class CooperativeMonthlyHrdTransaction extends Model
{
    protected $connection = 'mysql';

    protected $table = 'icu_mtrx2hrd';

    protected $primaryKey = 'rec_id';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'trx_amt' => 'integer',
        'statrec' => 'integer',
    ];
}
