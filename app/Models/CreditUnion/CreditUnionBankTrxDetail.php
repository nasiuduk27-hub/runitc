<?php

namespace App\Models\CreditUnion;

use Illuminate\Database\Eloquent\Model;

/**
 * Detail tambahan transaksi bank (koneksi run).
 *
 * Menyimpan rincian kategori (biaya admin bank atau bunga bank),
 * snapshot rekening bank koperasi, dan rincian bruto/pajak/neto
 * yang tidak tertampung di skema legacy icu_bank_trx.
 *
 * @property int $id
 * @property int $bank_trx_id
 * @property string $category
 * @property string|null $bank_account_bank
 * @property string|null $bank_account_no
 * @property string|null $bank_account_name
 * @property int $gross_amount
 * @property int $tax_amount
 * @property int $net_amount
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class CreditUnionBankTrxDetail extends Model
{
    public const CATEGORY_ADMIN_FEE = 'admin_fee';

    public const CATEGORY_INTEREST = 'interest';

    public const CATEGORIES = [
        self::CATEGORY_ADMIN_FEE,
        self::CATEGORY_INTEREST,
    ];

    public const CATEGORY_LABELS = [
        self::CATEGORY_ADMIN_FEE => 'Biaya Admin Bank',
        self::CATEGORY_INTEREST => 'Pendapatan Bunga Bank',
    ];

    protected $connection = 'run';

    protected $table = 'cu_bank_transaction_details';

    protected $guarded = ['id'];

    protected $casts = [
        'bank_trx_id' => 'integer',
        'gross_amount' => 'integer',
        'tax_amount' => 'integer',
        'net_amount' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function categoryLabel(): string
    {
        return self::CATEGORY_LABELS[$this->category] ?? $this->category;
    }

    public function bankAccountDisplay(): string
    {
        $parts = array_filter([
            $this->bank_account_bank,
            $this->bank_account_no,
            $this->bank_account_name ? 'a.n. '.$this->bank_account_name : null,
        ]);

        return implode(' - ', $parts) ?: '-';
    }
}
