<?php

namespace App\Models\Cooperative;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Wrapper read-only tabel existing icu_mloan (database itc_itconenew).
 *
 * Catatan dari analisis (bagian 34): arti statrec belum dikonfirmasi,
 * jadi status "lunas" dihitung secara indikatif dari paid >= totalloan.
 * Model ini tidak boleh melakukan INSERT/UPDATE/DELETE.
 *
 * @property int $rec_id
 * @property string $trnno
 * @property string|null $trndt
 * @property int $icu_rec_id
 * @property string|null $descr
 * @property int $principle
 * @property int $interamt
 * @property string|null $interest
 * @property int $totalloan
 * @property int $paid
 * @property int $monthly
 * @property int $term
 * @property string|null $startper
 * @property string|null $endper
 */
class CooperativeLoan extends Model
{
    protected $connection = 'mysql';

    protected $table = 'icu_mloan';

    protected $primaryKey = 'rec_id';

    public $timestamps = false;

    protected $fillable = ['*'];

    protected $casts = [
        'icu_rec_id' => 'integer',
        'principle' => 'integer',
        'interamt' => 'integer',
        'int_overdue' => 'integer',
        'bnk_charge' => 'integer',
        'totalloan' => 'integer',
        'paid' => 'integer',
        'avgmon' => 'integer',
        'avgint' => 'integer',
        'monthly' => 'integer',
        'term' => 'integer',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(CooperativeMember::class, 'icu_rec_id', 'rec_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(CooperativeLoanSchedule::class, 'mst_rec_id', 'rec_id');
    }

    /**
     * Cari pinjaman berdasarkan nomor transaksi, keterangan, atau nama anggota.
     */
    public function scopeSearch(Builder $query, ?string $keyword): Builder
    {
        if ($keyword === null || trim($keyword) === '') {
            return $query;
        }

        $keyword = '%'.str_replace('%', '\%', trim($keyword)).'%';

        return $query->where(function (Builder $inner) use ($keyword): void {
            $inner->where('trnno', 'like', $keyword)
                ->orWhere('descr', 'like', $keyword)
                ->orWhereHas('member', fn (Builder $memberQuery) => $memberQuery->search($keyword));
        });
    }

    /**
     * Filter status indikatif: settled = paid >= totalloan.
     */
    public function scopeStatusIndicative(Builder $query, int|string|null $status): Builder
    {
        return match ((string) $status) {
            'settled' => $query->where('totalloan', '>', 0)->whereColumn('paid', '>=', 'totalloan'),
            'running' => $query->where(fn (Builder $inner) => $inner
                ->where('totalloan', '=', 0)
                ->orWhereColumn('paid', '<', 'totalloan')),
            default => $query,
        };
    }

    /**
     * Filter berdasarkan anggota.
     */
    public function scopeForMember(Builder $query, int|string|null $memberId): Builder
    {
        if ($memberId === null || (int) $memberId <= 0) {
            return $query;
        }

        return $query->where('icu_rec_id', (int) $memberId);
    }

    /**
     * Indikasi lunas berdasarkan field existing (belum ada konfirmasi bisnis).
     */
    public function isSettledIndicative(): bool
    {
        return $this->totalloan > 0 && $this->paid >= $this->totalloan;
    }

    public function statusLabel(): string
    {
        return $this->isSettledIndicative() ? 'Lunas (indikatif)' : 'Berjalan';
    }

    public function statusBadgeClass(): string
    {
        return $this->isSettledIndicative()
            ? 'bg-gray-100 text-gray-600 border-gray-200'
            : 'bg-blue-50 text-blue-700 border-blue-200';
    }
}
