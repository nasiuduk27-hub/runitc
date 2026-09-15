<?php

namespace App\Models\Cooperative;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alokasi pembayaran angsuran per baris jadwal — tabel RUNITC (koneksi run).
 *
 * Partial payment dicatat di sini; icu_dloan hanya ditandai lunas
 * ketika akumulasi alokasi menutup tagihan baris sepenuhnya.
 *
 * @property int $id
 * @property int $payment_id
 * @property int $dloan_rec_id
 * @property int $seqno
 * @property int $amount_applied
 * @property bool $covers_full
 */
class CooperativeLoanPaymentAllocation extends Model
{
    protected $connection = 'run';

    protected $table = 'coop_loan_payment_allocations';

    protected $fillable = ['payment_id', 'dloan_rec_id', 'seqno', 'amount_applied', 'covers_full'];

    protected $casts = [
        'payment_id' => 'integer',
        'dloan_rec_id' => 'integer',
        'seqno' => 'integer',
        'amount_applied' => 'integer',
        'covers_full' => 'boolean',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(CooperativeLoanPayment::class, 'payment_id');
    }
}
