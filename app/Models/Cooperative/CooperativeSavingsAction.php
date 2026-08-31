<?php

namespace App\Models\Cooperative;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak aksi setoran simpanan (audit trail per setoran).
 *
 * @property int $id
 * @property int $savings_id
 * @property string $action
 * @property string|null $note
 * @property int $actor_user_id
 * @property string $actor_name
 */
class CooperativeSavingsAction extends Model
{
    public const ACTION_POSTED = 'posted';

    public const ACTION_CANCELLED = 'cancelled';

    protected $connection = 'run';

    protected $table = 'coop_savings_actions';

    protected $fillable = ['savings_id', 'action', 'note', 'actor_user_id', 'actor_name'];

    protected $casts = [
        'savings_id' => 'integer',
        'actor_user_id' => 'integer',
    ];

    public function savings(): BelongsTo
    {
        return $this->belongsTo(CooperativeSavings::class, 'savings_id');
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            self::ACTION_POSTED => 'Diposting',
            self::ACTION_CANCELLED => 'Dibatalkan',
            default => ucfirst($this->action),
        };
    }
}
