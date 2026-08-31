<?php

namespace App\Models\Cooperative;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CooperativeSavingsWithdrawal extends Model
{
    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $connection = 'run';

    protected $table = 'coop_savings_withdrawals';

    protected $fillable = [
        'member_rec_id', 'member_icuno', 'member_name', 'amount', 'bank_account', 'reason',
        'status', 'withdrawal_trnno', 'maker_user_id', 'checker_user_id', 'checked_at', 'decision_note',
    ];

    protected $casts = [
        'member_rec_id' => 'integer',
        'amount' => 'integer',
        'maker_user_id' => 'integer',
        'checker_user_id' => 'integer',
        'checked_at' => 'datetime',
    ];

    public function actions(): HasMany
    {
        return $this->hasMany(CooperativeSavingsWithdrawalAction::class, 'withdrawal_id')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_SUBMITTED => 'Menunggu Persetujuan',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_REJECTED => 'Ditolak',
            self::STATUS_CANCELLED => 'Dibatalkan',
            default => ucfirst((string) $this->status),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'bg-green-50 text-green-700 border-green-200',
            self::STATUS_REJECTED, self::STATUS_CANCELLED => 'bg-red-50 text-red-600 border-red-200',
            default => 'bg-amber-50 text-amber-700 border-amber-200',
        };
    }
}
