<?php

namespace App\Models\Cooperative;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak aksi skip pokok (audit trail per pengajuan).
 *
 * @property int $id
 * @property int $skip_id
 * @property string $action
 * @property string|null $note
 * @property int $actor_user_id
 * @property string $actor_name
 */
class CooperativeLoanSkipAction extends Model
{
    public const ACTION_SUBMITTED = 'submitted';

    public const ACTION_APPLIED = 'applied';

    public const ACTION_REJECTED = 'rejected';

    public const ACTION_CANCELLED = 'cancelled';

    protected $connection = 'run';

    protected $table = 'coop_loan_skip_actions';

    protected $fillable = ['skip_id', 'action', 'note', 'actor_user_id', 'actor_name'];

    protected $casts = [
        'skip_id' => 'integer',
        'actor_user_id' => 'integer',
    ];

    public function skip(): BelongsTo
    {
        return $this->belongsTo(CooperativeLoanSkip::class, 'skip_id');
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            self::ACTION_SUBMITTED => 'Diajukan',
            self::ACTION_APPLIED => 'Disetujui & Diterapkan',
            self::ACTION_REJECTED => 'Ditolak',
            self::ACTION_CANCELLED => 'Dibatalkan',
            default => ucfirst($this->action),
        };
    }
}
