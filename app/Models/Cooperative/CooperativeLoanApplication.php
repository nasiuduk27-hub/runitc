<?php

namespace App\Models\Cooperative;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Pengajuan pinjaman koperasi — tabel RUNITC sendiri (koneksi run).
 * Tidak menyentuh tabel icu% sampai proses posting dilakukan.
 *
 * @property int $id
 * @property int $member_rec_id
 * @property string $member_icuno
 * @property string $member_name
 * @property int $principal_amount
 * @property int $tenor_months
 * @property float $annual_rate_percent
 * @property string $calculation_method
 * @property string|null $descr
 * @property string $status
 * @property int $monthly_installment
 * @property int $total_interest
 * @property int $total_payment
 * @property string|null $schedule_json
 * @property int $applicant_user_id
 * @property int|null $reviewer_user_id
 * @property string|null $reviewed_at
 * @property string|null $decision_note
 * @property int|null $posted_loan_rec_id
 * @property string $fund_release_method
 * @property int $admin_fee
 * @property string $admin_fee_type
 * @property string|null $bank_bnkcd
 * @property string|null $bank_accnm
 * @property string|null $bank_accno
 */
class CooperativeLoanApplication extends Model
{
    protected $connection = 'run';

    protected $table = 'coop_loan_applications';

    protected $fillable = [
        'member_rec_id', 'member_icuno', 'member_name',
        'principal_amount', 'tenor_months', 'annual_rate_percent', 'calculation_method', 'descr',
        'status', 'monthly_installment', 'total_interest', 'total_payment', 'schedule_json',
        'applicant_user_id', 'reviewer_user_id', 'reviewed_at', 'decision_note', 'posted_loan_rec_id',
        'fund_release_method', 'admin_fee', 'admin_fee_type', 'bank_bnkcd', 'bank_accnm', 'bank_accno',
    ];

    protected $casts = [
        'member_rec_id' => 'integer',
        'principal_amount' => 'integer',
        'tenor_months' => 'integer',
        'annual_rate_percent' => 'float',
        'monthly_installment' => 'integer',
        'total_interest' => 'integer',
        'total_payment' => 'integer',
        'applicant_user_id' => 'integer',
        'reviewer_user_id' => 'integer',
        'posted_loan_rec_id' => 'integer',
        'admin_fee' => 'integer',
        'admin_fee_type' => 'string',
        'reviewed_at' => 'datetime',
    ];

    public function actions(): HasMany
    {
        return $this->hasMany(CooperativeLoanApplicationAction::class, 'application_id')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function latestAction(): HasOne
    {
        return $this->hasOne(CooperativeLoanApplicationAction::class, 'application_id')->latestOfMany();
    }

    /**
     * Jadwal angsuran hasil simulasi saat pengajuan dibuat.
     *
     * @return list<array<string, int|bool|string>>
     */
    public function schedule(): array
    {
        $decoded = json_decode((string) $this->schedule_json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Data anggota aktual dari tabel icu_member (read-only, lintas koneksi).
     */
    public function member(): ?CooperativeMember
    {
        return CooperativeMember::query()->find($this->member_rec_id);
    }
}
