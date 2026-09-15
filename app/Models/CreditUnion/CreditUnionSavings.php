<?php

namespace App\Models\Cooperative;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Setoran simpanan bulanan anggota — tabel RUNITC sendiri (koneksi run).
 *
 * Batch entry bulanan: setoran langsung diposting (status posted) ke
 * icu_transaction (trncd 19, dbocr D). Audit jejak ada di coop_savings_actions.
 *
 * @property int $id
 * @property int $member_rec_id
 * @property string $member_icuno
 * @property string $member_name
 * @property string $pprd
 * @property int $amount
 * @property string $method
 * @property string|null $notes
 * @property string $status
 * @property string|null $savings_trnno
 * @property int $maker_user_id
 */
class CooperativeSavings extends Model
{
    protected $connection = 'run';

    protected $table = 'coop_savings';

    protected $fillable = [
        'member_rec_id', 'member_icuno', 'member_name', 'pprd', 'amount',
        'method', 'notes', 'status', 'savings_trnno', 'maker_user_id',
    ];

    protected $casts = [
        'member_rec_id' => 'integer',
        'amount' => 'integer',
        'maker_user_id' => 'integer',
    ];

    public function actions(): HasMany
    {
        return $this->hasMany(CooperativeSavingsAction::class, 'savings_id')
            ->orderBy('created_at')
            ->orderBy('id');
    }
}
