<?php

namespace App\Models\CreditUnion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pengajuan skip pokok / refinancing — tabel RUNITC (koneksi run).
 *
 * @property int $id
 * @property int $loan_rec_id
 * @property int $member_rec_id
 * @property string $member_icuno
 * @property string $member_name
 * @property string $start_period
 * @property int $months_count
 * @property int $rows_skipped
 * @property int $principal_moved
 * @property int|null $paid_amount
 * @property string|null $paid_at
 * @property string|null $payment_note
 * @property string|null $bank_trnno
 * @property int $extra_interest
 * @property int $new_term
 * @property string|null $plan_json
 * @property string|null $reference_no
 * @property string $status
 * @property string|null $reason
 * @property int $maker_user_id
 */
class CreditUnionLoanSkip extends Model
{
    protected $connection = 'run';

    protected $table = 'cu_loan_skips';

    protected $fillable = [
        'mode', 'reference_no', 'loan_rec_id', 'member_rec_id', 'member_icuno', 'member_name',
        'start_period', 'months_count', 'rows_skipped', 'principal_moved', 'paid_amount',
        'paid_at', 'payment_note', 'bank_trnno',
        'extra_interest', 'new_term', 'plan_json',
        'status', 'reason', 'maker_user_id', 'checker_user_id', 'checked_at', 'decision_note',
    ];

    protected $casts = [
        'mode' => 'string',
        'loan_rec_id' => 'integer',
        'member_rec_id' => 'integer',
        'start_period' => 'string',
        'months_count' => 'integer',
        'rows_skipped' => 'integer',
        'principal_moved' => 'integer',
        'paid_amount' => 'integer',
        'paid_at' => 'date',
        'extra_interest' => 'integer',
        'new_term' => 'integer',
        'maker_user_id' => 'integer',
        'checker_user_id' => 'integer',
        'checked_at' => 'datetime',
    ];

    public function actions(): HasMany
    {
        return $this->hasMany(CreditUnionLoanSkipAction::class, 'skip_id')
            ->orderBy('created_at')
            ->orderBy('id');
    }
}
