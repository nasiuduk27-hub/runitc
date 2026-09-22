<?php

namespace App\Models\CreditUnion;

use Illuminate\Database\Eloquent\Model;

class CreditUnionReconciliationAction extends Model
{
    protected $connection = 'run';

    protected $table = 'cu_reconciliation_actions';

    protected $fillable = ['reconciliation_id', 'action', 'note', 'actor_user_id', 'actor_name'];

    protected $casts = ['actor_user_id' => 'integer'];
}
