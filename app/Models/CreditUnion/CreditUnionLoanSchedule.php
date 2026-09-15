<?php

namespace App\Models\Cooperative;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wrapper read-only tabel existing icu_dloan (database itc_itconenew).
 *
 * Temuan inspeksi data (Agustus 2026):
 * - outstand = sisa POKOK pinjaman (menurun sebesar amount, bukan amount + int_amt).
 * - paidst = 0 pada seluruh baris existing; arti nilai selain 0 belum dikonfirmasi.
 * - remarks "Rounding" dipakai pada cicilan terakhir hasil pembulatan.
 *
 * @property int $rec_id
 * @property int $mst_rec_id
 * @property string|null $periode
 * @property int $seqno
 * @property int $totseqno
 * @property string|null $descr
 * @property int $amount
 * @property int $int_amt
 * @property int $others
 * @property int $outstand
 * @property string|null $remarks
 * @property int $dseqno
 * @property int $paidst
 * @property string|null $payno
 */
class CooperativeLoanSchedule extends Model
{
    public const PAIDST_UNPAID = 0;

    protected $connection = 'mysql';

    protected $table = 'icu_dloan';

    protected $primaryKey = 'rec_id';

    public $timestamps = false;

    protected $fillable = ['*'];

    protected $casts = [
        'mst_rec_id' => 'integer',
        'seqno' => 'integer',
        'totseqno' => 'integer',
        'amount' => 'integer',
        'rnd_amt' => 'integer',
        'int_amt' => 'integer',
        'rnd_int' => 'integer',
        'others' => 'integer',
        'outstand' => 'integer',
        'dseqno' => 'integer',
        'paidst' => 'integer',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(CooperativeLoan::class, 'mst_rec_id', 'rec_id');
    }

    public function installmentLabel(): string
    {
        if ($this->totseqno > 0) {
            return $this->seqno.'/'.$this->totseqno;
        }

        return (string) $this->seqno;
    }

    /**
     * Status pembayaran bersifat indikatif karena paidst=0 pada semua data existing.
     */
    public function paymentStatusLabel(): string
    {
        if ($this->paidst === self::PAIDST_UNPAID && trim((string) $this->payno) === '') {
            return 'Belum Bayar';
        }

        return match ($this->paidst) {
            self::PAIDST_UNPAID => 'Perlu Verifikasi',
            default => 'Kode '.$this->paidst,
        };
    }

    public function paymentStatusBadgeClass(): string
    {
        return $this->paymentStatusLabel() === 'Belum Bayar'
            ? 'bg-gray-100 text-gray-600 border-gray-200'
            : 'bg-amber-50 text-amber-700 border-amber-200';
    }

    public function isRoundingRow(): bool
    {
        return str_contains(strtolower((string) $this->remarks), 'rounding');
    }

    public function totalDue(): int
    {
        return $this->amount + $this->int_amt + $this->others;
    }
}
