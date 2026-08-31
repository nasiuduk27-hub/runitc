<?php

namespace App\Models\Cooperative;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CooperativeSavingsWithdrawalAction extends Model
{
    public const ACTION_SUBMITTED = 'submitted';

    public const ACTION_APPROVED = 'approved';

    public const ACTION_REJECTED = 'rejected';

    public const ACTION_CANCELLED = 'cancelled';

    protected $connection = 'run';

    protected $table = 'coop_savings_withdrawal_actions';

    protected $fillable = ['withdrawal_id', 'action', 'note', 'actor_user_id', 'actor_name'];

    protected $casts = [
        'withdrawal_id' => 'integer',
        'actor_user_id' => 'integer',
    ];

    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(CooperativeSavingsWithdrawal::class, 'withdrawal_id');
    }
}
