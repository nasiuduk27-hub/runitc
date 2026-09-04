<?php

namespace App\Models\Cooperative;

use Illuminate\Database\Eloquent\Model;

/**
 * Wrapper tabel existing icu_bank_trx (database itc_itconenew).
 *
 * trnno: nomor transaksi bank (pola legacy RCV-{YY}{huruf bulan}-{urut}).
 * req_frm_trxno: nomor referensi dari icu_mtrx2hrd (pola PMT-...).
 * dbocr: C = kredit, D = debit.
 *
 * @property int $rec_id
 * @property string $pprdk
 * @property string $trnno
 * @property string $trndt
 * @property string $req_frm_trxno
 * @property string $dbocr
 * @property int $icu_rec_id
 * @property string $descr
 * @property int $amount
 * @property int $statrec
 */
class CooperativeBankTrx extends Model
{
    public const DIRECTION_DEBIT = 'D';

    public const DIRECTION_CREDIT = 'C';

    /** @var array<string, string> */
    public const DIRECTION_LABELS = [
        self::DIRECTION_DEBIT => 'Debit',
        self::DIRECTION_CREDIT => 'Kredit',
    ];

    protected $connection = 'mysql';

    protected $table = 'icu_bank_trx';

    protected $primaryKey = 'rec_id';

    public $timestamps = false;

    protected $fillable = ['*'];

    protected $casts = [
        'icu_rec_id' => 'integer',
        'amount' => 'integer',
        'statrec' => 'integer',
        'edit_enable' => 'integer',
    ];

    public function directionLabel(): string
    {
        return self::DIRECTION_LABELS[$this->dbocr] ?? ucfirst(strtolower((string) $this->dbocr));
    }
}