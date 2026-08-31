<?php

namespace App\Models\Cooperative;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Wrapper read-only tabel existing icu_member (database itc_itconenew).
 *
 * Status anggota mengikuti master icu_msttable tbl_code 02.
 * Model ini tidak boleh melakukan INSERT/UPDATE/DELETE.
 *
 * @property int $rec_id
 * @property int $itc_user_id
 * @property string|null $pprdk
 * @property string $icuno
 * @property string $icunm
 * @property string|null $alias_nm
 * @property string|null $joindt
 * @property int $st_aktif
 * @property int $swajib
 * @property int $outstanding
 * @property string|null $refno
 */
class CooperativeMember extends Model
{
    public const STATUS_DRAFT = 0;

    public const STATUS_CU_ACCOUNT = 1;

    public const STATUS_REGULAR_MEMBER = 2;

    public const STATUS_REGULAR_NON_PAYROLL = 3;

    public const STATUS_IRREGULAR_MEMBER = 4;

    public const STATUS_OUTSTANDING_MEMBER = 5;

    public const STATUS_NON_ACTIVE = 6;

    /** @var array<int, string> */
    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_CU_ACCOUNT => 'CU Account',
        self::STATUS_REGULAR_MEMBER => 'Regular Member',
        self::STATUS_REGULAR_NON_PAYROLL => 'Regular Non Payroll',
        self::STATUS_IRREGULAR_MEMBER => 'Irregular Member',
        self::STATUS_OUTSTANDING_MEMBER => 'Outstanding Member',
        self::STATUS_NON_ACTIVE => 'Non-Active',
    ];

    protected $connection = 'mysql';

    protected $table = 'icu_member';

    protected $primaryKey = 'rec_id';

    public $timestamps = false;

    protected $fillable = ['*'];

    protected $casts = [
        'itc_user_id' => 'integer',
        'st_aktif' => 'integer',
        'temp_trx' => 'integer',
        'otvalue' => 'integer',
        'swajib' => 'integer',
        'outstanding' => 'integer',
        'stat_trx' => 'integer',
        'koreksi' => 'integer',
    ];

    public function loans(): HasMany
    {
        return $this->hasMany(CooperativeLoan::class, 'icu_rec_id', 'rec_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CooperativeTransaction::class, 'icu_rec_id', 'rec_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->st_aktif] ?? 'Tidak Dikenal ('.$this->st_aktif.')';
    }

    public function statusBadgeClass(): string
    {
        return match (true) {
            $this->st_aktif === self::STATUS_NON_ACTIVE => 'bg-red-50 text-red-700 border-red-200',
            $this->st_aktif === self::STATUS_REGULAR_MEMBER, $this->st_aktif === self::STATUS_REGULAR_NON_PAYROLL => 'bg-green-50 text-green-700 border-green-200',
            default => 'bg-amber-50 text-amber-700 border-amber-200',
        };
    }

    /**
     * Cari anggota berdasarkan keyword pada nomor, nama, atau referensi pegawai.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSearch($query, ?string $keyword)
    {
        if ($keyword === null || trim($keyword) === '') {
            return $query;
        }

        $keyword = '%'.str_replace('%', '\%', trim($keyword)).'%';

        return $query->where(function ($inner) use ($keyword): void {
            $inner->where('icuno', 'like', $keyword)
                ->orWhere('icunm', 'like', $keyword)
                ->orWhere('refno', 'like', $keyword);
        });
    }

    /**
     * Filter berdasarkan status keanggotaan.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeStatus($query, int|string|null $status)
    {
        if ($status === null || $status === '' || (int) $status < 0) {
            return $query;
        }

        return $query->where('st_aktif', (int) $status);
    }
}
