<?php

namespace App\Models\Cooperative;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wrapper read-only tabel existing icu_transaction (database itc_itconenew).
 *
 * dbocr: D = debit (misal setoran simpanan), C = kredit (misal penarikan).
 * Model ini tidak boleh melakukan INSERT/UPDATE/DELETE.
 *
 * @property int $rec_id
 * @property string $trnno
 * @property string|null $trndt
 * @property int $icu_rec_id
 * @property string|null $descr
 * @property string $dbocr
 * @property int $basic_amt
 * @property int $int_amt
 * @property int $amount
 */
class CooperativeTransaction extends Model
{
    public const DIRECTION_DEBIT = 'D';

    public const DIRECTION_CREDIT = 'C';

    /** @var array<string, string> */
    public const DIRECTION_LABELS = [
        self::DIRECTION_DEBIT => 'Debit',
        self::DIRECTION_CREDIT => 'Kredit',
    ];

    protected $connection = 'mysql';

    protected $table = 'icu_transaction';

    protected $primaryKey = 'rec_id';

    public $timestamps = false;

    protected $fillable = ['*'];

    protected $casts = [
        'icu_rec_id' => 'integer',
        'basic_amt' => 'integer',
        'int_amt' => 'integer',
        'amount' => 'integer',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(CooperativeMember::class, 'icu_rec_id', 'rec_id');
    }

    public function directionLabel(): string
    {
        return self::DIRECTION_LABELS[$this->dbocr] ?? ucfirst(strtolower((string) $this->dbocr));
    }
}
