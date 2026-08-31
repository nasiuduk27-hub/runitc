<?php

namespace App\Models\Cooperative;

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
 * @property int $extra_interest
 * @property int $new_term
 * @property string|null $plan_json
 * @property string $status
 * @property string|null $reason
 * @property int $maker_user_id
 */
class CooperativeLoanSkip extends Model
{
    protected $connection = 'run';

    protected $table = 'coop_loan_skips';

    protected $fillable = [
        'mode', 'loan_rec_id', 'member_rec_id', 'member_icuno', 'member_name',
        'start_period', 'months_count', 'rows_skipped', 'principal_moved',
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
        'extra_interest' => 'integer',
        'new_term' => 'integer',
        'maker_user_id' => 'integer',
        'checker_user_id' => 'integer',
        'checked_at' => 'datetime',
    ];

    public function actions(): HasMany
    {
        return $this->hasMany(CooperativeLoanSkipAction::class, 'skip_id')
            ->orderBy('created_at')
            ->orderBy('id');
    }
}
