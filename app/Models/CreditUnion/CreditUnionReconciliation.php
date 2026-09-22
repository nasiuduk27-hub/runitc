<?php

namespace App\Models\CreditUnion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditUnionReconciliation extends Model
{
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $connection = 'run';

    protected $table = 'cu_reconciliations';

    protected $fillable = [
        'ref_no', 'scope', 'period', 'member_rec_id', 'member_icuno', 'member_name',
        'target_type', 'target_ref', 'expected_amount', 'actual_amount', 'difference_amount',
        'direction', 'reason', 'snapshot_json', 'status', 'correction_trnno',
        'maker_user_id', 'checker_user_id', 'checked_at', 'decision_note',
    ];

    protected $casts = [
        'member_rec_id' => 'integer',
        'expected_amount' => 'integer',
        'actual_amount' => 'integer',
        'difference_amount' => 'integer',
        'maker_user_id' => 'integer',
        'checker_user_id' => 'integer',
        'snapshot_json' => 'array',
        'checked_at' => 'datetime',
    ];

    public function actions(): HasMany
    {
        return $this->hasMany(CreditUnionReconciliationAction::class, 'reconciliation_id')
            ->orderBy('created_at')->orderBy('id');
    }
}
