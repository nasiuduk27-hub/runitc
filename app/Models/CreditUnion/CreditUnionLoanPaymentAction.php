<?php

namespace App\Models\Cooperative;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak aksi pembayaran angsuran (audit trail per pembayaran).
 *
 * @property int $id
 * @property int $payment_id
 * @property string $action
 * @property string|null $note
 * @property int $actor_user_id
 * @property string $actor_name
 */
class CooperativeLoanPaymentAction extends Model
{
    public const ACTION_SUBMITTED = 'submitted';

    public const ACTION_VERIFIED = 'verified';

    public const ACTION_REJECTED = 'rejected';

    public const ACTION_CANCELLED = 'cancelled';

    protected $connection = 'run';

    protected $table = 'coop_loan_payment_actions';

    protected $fillable = ['payment_id', 'action', 'note', 'actor_user_id', 'actor_name'];

    protected $casts = [
        'payment_id' => 'integer',
        'actor_user_id' => 'integer',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(CooperativeLoanPayment::class, 'payment_id');
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            self::ACTION_SUBMITTED => 'Diajukan',
            self::ACTION_VERIFIED => 'Diverifikasi & Diposting',
            self::ACTION_REJECTED => 'Ditolak',
            self::ACTION_CANCELLED => 'Dibatalkan',
            default => ucfirst($this->action),
        };
    }
}
