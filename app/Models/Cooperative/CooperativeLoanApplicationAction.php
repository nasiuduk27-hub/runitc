<?php

namespace App\Models\Cooperative;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak aksi pengajuan pinjaman (audit trail per aplikasi).
 *
 * @property int $id
 * @property int $application_id
 * @property string $action
 * @property string|null $note
 * @property int $actor_user_id
 * @property string $actor_name
 */
class CooperativeLoanApplicationAction extends Model
{
    public const ACTION_SUBMITTED = 'submitted';

    public const ACTION_APPROVED = 'approved';

    public const ACTION_REJECTED = 'rejected';

    public const ACTION_CANCELLED = 'cancelled';

    protected $connection = 'run';

    protected $table = 'coop_loan_application_actions';

    protected $fillable = ['application_id', 'action', 'note', 'actor_user_id', 'actor_name'];

    protected $casts = [
        'application_id' => 'integer',
        'actor_user_id' => 'integer',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(CooperativeLoanApplication::class, 'application_id');
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            self::ACTION_SUBMITTED => 'Diajukan',
            self::ACTION_APPROVED => 'Disetujui',
            self::ACTION_REJECTED => 'Ditolak',
            self::ACTION_CANCELLED => 'Dibatalkan',
            default => ucfirst($this->action),
        };
    }
}
