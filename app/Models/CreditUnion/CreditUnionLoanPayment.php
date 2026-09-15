<?php

namespace App\Models\Cooperative;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pembayaran angsuran pinjaman — tabel RUNITC sendiri (koneksi run).
 *
 * Workflow: maker input (submitted) -> checker verifikasi (verified = diposting
 * ke icu_transaction/icu_dloan/icu_mloan/icu_member) atau ditolak/dibatalkan.
 *
 * @property int $id
 * @property int $loan_rec_id
 * @property int $member_rec_id
 * @property string $member_icuno
 * @property string $member_name
 * @property string $payment_date
 * @property int $amount
 * @property string $method
 * @property string|null $notes
 * @property string $status
 * @property string|null $icu_trnno
 * @property int $principal_portion
 * @property int $interest_portion
 * @property string|null $allocation_json
 * @property int $maker_user_id
 * @property int|null $checker_user_id
 */
class CooperativeLoanPayment extends Model
{
    protected $connection = 'run';

    protected $table = 'coop_loan_payments';

    protected $fillable = [
        'loan_rec_id', 'member_rec_id', 'member_icuno', 'member_name',
        'payment_date', 'amount', 'method', 'notes', 'status', 'icu_trnno',
        'principal_portion', 'interest_portion', 'allocation_json',
        'maker_user_id', 'checker_user_id', 'checked_at', 'decision_note',
    ];

    protected $casts = [
        'loan_rec_id' => 'integer',
        'member_rec_id' => 'integer',
        'payment_date' => 'date',
        'amount' => 'integer',
        'principal_portion' => 'integer',
        'interest_portion' => 'integer',
        'maker_user_id' => 'integer',
        'checker_user_id' => 'integer',
        'checked_at' => 'datetime',
    ];

    public function actions(): HasMany
    {
        return $this->hasMany(CooperativeLoanPaymentAction::class, 'payment_id')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CooperativeLoanPaymentAllocation::class, 'payment_id')
            ->orderBy('seqno')
            ->orderBy('id');
    }

    /**
     * Data anggota aktual dari tabel icu_member (read-only, lintas koneksi).
     */
    public function member(): ?CooperativeMember
    {
        return CooperativeMember::query()->find($this->member_rec_id);
    }
}
