<?php

namespace App\Services\Cooperative;

use App\Models\Cooperative\CooperativeLoanSkip;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Skip pokok / refinancing (bagian 29 dokumen + jawaban bagian 34 poin 8 & 10).
 *
 * Aturan bisnis:
 * - Baris dalam rentang skip tidak membayar pokok (amount=0) tetapi tetap
 *   dikenakan bunga bulanan flat (int_amt tetap).
 * - Pokok yang tertunda dipindahkan ke N baris baru di ekor jadwal sehingga
 *   tenor bertambah tepat sebesar jumlah bulan skip.
 * - Bunga bulan-bulan tambahan diperlakukan sebagai "Biaya perpanjang pinjaman":
 *   TIDAK menambah interamt maupun totalloan; cukup tercermin di detail icu_dloan.
 * - endper dan term ikut jadwal terakhir aktual.
 * - Butuh approval khusus: pengaju tidak dapat menyetujui sendiri.
 */
class LoanSkipService
{
    /** Mode refinancing: tunda pokok (tenor +N). */
    public const MODE_SKIP = 'skip';

    /** Mode refinancing: percepat / perpendek pembayaran (tenor -N). */
    public const MODE_ACCELERATE = 'accelerate';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_SUBMITTED => 'Menunggu Persetujuan',
        self::STATUS_APPLIED => 'Diterapkan ke Jadwal',
        self::STATUS_REJECTED => 'Ditolak',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        self::STATUS_SUBMITTED => [self::STATUS_APPLIED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        self::STATUS_APPLIED => [],
        self::STATUS_REJECTED => [],
        self::STATUS_CANCELLED => [],
    ];

    public const MIN_MONTHS = 1;

    public const MAX_MONTHS = 12;

    /** Label status baris jadwal pada tampilan sebelum/sesudah refinancing. */
    public const ROW_PAID = 'Terbayar';

    public const ROW_DUE = 'Jatuh Tempo';

    public const ROW_NOT_DUE = 'Belum Jatuh Tempo';

    public const ROW_SKIP = 'Ajukan Refinancing';

    public const ROW_NEW = 'Baru';

    /**
     * Susun rencana skip (murni, tanpa akses database).
     *
     * @param  list<array{rec_id: int, seqno: int, periode: string, amount: int, int_amt: int, others: int, paidst: int}>  $rows  seluruh baris jadwal, urut seqno
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public function plan(array $rows, string $startPeriod, int $months): array
    {
        if (! CooperativePeriod::isValid($startPeriod)) {
            throw new InvalidArgumentException('Periode mulai harus berformat YYYYMM.');
        }

        if ($months < self::MIN_MONTHS || $months > self::MAX_MONTHS) {
            throw new InvalidArgumentException('Lama skip harus antara '.self::MIN_MONTHS.' sampai '.self::MAX_MONTHS.' bulan.');
        }

        $unpaid = array_values(array_filter($rows, fn (array $row): bool => $row['paidst'] === 0));

        if ($unpaid === []) {
            throw new InvalidArgumentException('Tidak ada baris belum dibayar untuk diskip.');
        }

        $endWindow = CooperativePeriod::addMonths($startPeriod, $months - 1);

        $targets = array_values(array_filter(
            $unpaid,
            fn (array $row): bool => $row['periode'] >= $startPeriod && $row['periode'] <= $endWindow
        ));

        if ($targets === []) {
            throw new InvalidArgumentException(
                'Tidak ada baris belum dibayar pada rentang '.CooperativePeriod::label($startPeriod)
                .' s.d. '.CooperativePeriod::label($endWindow).'.'
            );
        }

        $monthlyInterest = (int) $unpaid[0]['int_amt'];
        $movedPrincipal = array_sum(array_column($targets, 'amount'));
        $lastPeriode = (string) end($rows)['periode'];

        // N baris baru: dasar = intdiv, sisa didistribusikan ke baris-baris awal
        // sehingga jumlah pokok tepat dan tidak ada nilai negatif.
        $newRows = [];
        $base = intdiv($movedPrincipal, $months);
        $remainder = $movedPrincipal % $months;
        for ($index = 0; $index < $months; $index++) {
            $newRows[] = [
                'periode' => CooperativePeriod::addMonths($lastPeriode, $index + 1),
                'amount' => $base + ($index < $remainder ? 1 : 0),
                'int_amt' => $monthlyInterest,
            ];
        }

        return [
            'start_period' => $startPeriod,
            'months' => $months,
            'window_end' => $endWindow,
            'target_rec_ids' => array_column($targets, 'rec_id'),
            'skipped_rows' => count($targets),
            'moved_principal' => $movedPrincipal,
            'extra_interest' => $months * $monthlyInterest,
            'monthly_interest' => $monthlyInterest,
            'current_rows' => count($rows),
            'new_term' => count($rows) + $months,
            'new_last_periode' => (string) end($newRows)['periode'],
            'new_rows' => $newRows,
        ];
    }

    /**
     * Susun rencana percepatan (perpendek) pembayaran (murni, tanpa akses database).
     *
     * Bunga tetap ditagih dan dikalkulasi ulang: total pokok dan total bunga dari
     * seluruh baris belum dibayar dipertahankan, tetapi dibagi rata ke lebih sedikit
     * baris sehingga tenor berkurang tepat N bulan.
     *
     * @param  list<array{rec_id: int, seqno: int, periode: string, amount: int, int_amt: int, others: int, paidst: int}>  $rows  seluruh baris jadwal, urut seqno
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public function acceleratePlan(array $rows, int $months): array
    {
        if ($months < self::MIN_MONTHS || $months > self::MAX_MONTHS) {
            throw new InvalidArgumentException('Lama percepatan harus antara '.self::MIN_MONTHS.' sampai '.self::MAX_MONTHS.' bulan.');
        }

        $unpaid = array_values(array_filter($rows, fn (array $row): bool => $row['paidst'] === 0));

        if ($unpaid === []) {
            throw new InvalidArgumentException('Tidak ada baris belum dibayar untuk dipercepat.');
        }

        // Baris skip (refinancing sebelumnya) adalah baris unpaid dengan pokok 0
        // (hanya bunga). Baris ini tidak boleh diubah; percepat hanya memadatkan
        // baris angsuran NORMAL (pokok > 0) di bawahnya.
        $payable = array_values(array_filter($unpaid, fn (array $row): bool => (int) $row['amount'] > 0));

        if ($payable === []) {
            throw new InvalidArgumentException('Tidak ada angsuran normal untuk dipercepat (semua baris belum dibayar berstatus refinancing/skip).');
        }

        if (count($payable) <= $months) {
            throw new InvalidArgumentException('Lama percepatan melebihi sisa angsuran normal yang belum dibayar.');
        }

        $removed = array_slice($payable, -$months);
        $remainingPayable = array_slice($payable, 0, -$months);
        $remainingCount = count($remainingPayable);

        $totalPrincipal = array_sum(array_column($payable, 'amount'));
        $totalInterest = array_sum(array_column($payable, 'int_amt'));

        $principalSplit = $this->splitEvenly($totalPrincipal, $remainingCount);
        $interestSplit = $this->splitEvenly($totalInterest, $remainingCount);

        $remainingRows = [];
        foreach ($remainingPayable as $index => $row) {
            $remainingRows[] = [
                'rec_id' => $row['rec_id'],
                'seqno' => $row['seqno'],
                'periode' => $row['periode'],
                'amount' => $principalSplit[$index],
                'int_amt' => $interestSplit[$index],
            ];
        }

        $removedRecIds = array_column($removed, 'rec_id');
        $keptRows = array_values(array_filter(
            $rows,
            fn (array $row): bool => ! in_array($row['rec_id'], $removedRecIds, true)
        ));
        $lastKeptRow = end($keptRows);

        return [
            'mode' => self::MODE_ACCELERATE,
            'months' => $months,
            'removed_rec_ids' => $removedRecIds,
            'removed_rows' => count($removed),
            'moved_principal' => $totalPrincipal,
            'retained_interest' => $totalInterest,
            'monthly_interest' => (int) $unpaid[0]['int_amt'],
            'current_rows' => count($rows),
            'new_term' => count($keptRows),
            'new_last_periode' => (string) $lastKeptRow['periode'],
            'remaining_rows' => $remainingRows,
            'window_end' => (string) $lastKeptRow['periode'],
        ];
    }

    /**
     * Baris jadwal saat ini, dilengkapi status pembayaran/jatuh tempo untuk tampilan.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function scheduleWithStatus(array $rows): array
    {
        $current = CooperativePeriod::current();
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->rowDisplay($row, $current);
        }

        return $out;
    }

    /**
     * Jadwal "sesudah" refinancing: baris terdampak diberi label status khusus
     * (skip / baru / dkalkulasi ulang) sesuai mode dan rencana.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $plan
     * @return list<array<string, mixed>>
     */
    public function afterSchedule(array $rows, array $plan, string $mode): array
    {
        $current = CooperativePeriod::current();

        return match ($mode) {
            self::MODE_ACCELERATE => $this->accelerateAfterRows($rows, $plan, $current),
            default => $this->skipAfterRows($rows, $plan, $current),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $plan
     * @return list<array<string, mixed>>
     */
    private function skipAfterRows(array $rows, array $plan, string $current): array
    {
        $targetSet = array_flip($plan['target_rec_ids'] ?? []);
        $out = [];

        foreach ($rows as $row) {
            if (isset($targetSet[$row['rec_id']])) {
                $out[] = $this->rowDisplay(
                    array_replace($row, ['amount' => 0, 'payno' => '']),
                    $current,
                    self::ROW_SKIP
                );
            } else {
                $out[] = $this->rowDisplay($row, $current);
            }
        }

        foreach ($plan['new_rows'] ?? [] as $newRow) {
            $out[] = [
                'rec_id' => null,
                'seqno' => count($out) + 1,
                'periode' => (string) $newRow['periode'],
                'amount' => (int) $newRow['amount'],
                'int_amt' => (int) $newRow['int_amt'],
                'others' => 0,
                'paidst' => 0,
                'status' => self::ROW_NEW,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $plan
     * @return list<array<string, mixed>>
     */
    private function accelerateAfterRows(array $rows, array $plan, string $current): array
    {
        $removedSet = array_flip($plan['removed_rec_ids'] ?? []);
        $recalc = [];
        foreach ($plan['remaining_rows'] ?? [] as $row) {
            $recalc[$row['rec_id']] = $row;
        }

        $out = [];
        foreach ($rows as $row) {
            if (isset($removedSet[$row['rec_id']])) {
                continue;
            }

            // Baris unpaid dengan pokok 0 = bulan refinancing/skip; dipertahankan.
            if ((int) $row['paidst'] === 0 && (int) $row['amount'] === 0) {
                $out[] = $this->rowDisplay($row, $current, self::ROW_SKIP);

                continue;
            }

            $adjusted = $row;
            if (isset($recalc[$row['rec_id']])) {
                $adjusted['amount'] = $recalc[$row['rec_id']]['amount'];
                $adjusted['int_amt'] = $recalc[$row['rec_id']]['int_amt'];
            }

            $out[] = $this->rowDisplay($adjusted, $current);
        }

        return $out;
    }

    /**
     * Bangun baris tampilan: jumlah asli + label status.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function rowDisplay(array $row, string $current, ?string $status = null): array
    {
        return [
            'rec_id' => $row['rec_id'] ?? null,
            'seqno' => (int) ($row['seqno'] ?? 0),
            'periode' => (string) ($row['periode'] ?? ''),
            'amount' => (int) ($row['amount'] ?? 0),
            'int_amt' => (int) ($row['int_amt'] ?? 0),
            'others' => (int) ($row['others'] ?? 0),
            'paidst' => (int) ($row['paidst'] ?? 0),
            'status' => $status ?? $this->installmentStatus($row, $current),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function installmentStatus(array $row, string $current): string
    {
        if ((int) ($row['paidst'] ?? 0) !== 0 || trim((string) ($row['payno'] ?? '')) !== '') {
            return self::ROW_PAID;
        }

        if ((int) ($row['amount'] ?? 0) === 0) {
            return self::ROW_SKIP;
        }

        if (isset($row['periode']) && (string) $row['periode'] <= $current) {
            return self::ROW_DUE;
        }

        return self::ROW_NOT_DUE;
    }

    /**
     * Bagi nominal secara merata ke n bagian; sisa didistribusikan ke bagian awal
     * sehingga total tepat dan tidak ada nilai negatif.
     *
     * @return list<int>
     */
    private function splitEvenly(int $total, int $parts): array
    {
        $base = intdiv($total, $parts);
        $remainder = $total % $parts;
        $result = [];
        for ($index = 0; $index < $parts; $index++) {
            $result[] = $base + ($index < $remainder ? 1 : 0);
        }

        return $result;
    }

    /**
     * Terapkan rencana skip yang sudah disetujui ke tabel legacy.
     * Mengembalikan ringkasan penerapan.
     *
     * @throws InvalidArgumentException
     */
    public function apply(CooperativeLoanSkip $skip, int $actorUserId): array
    {
        if ($skip->status !== self::STATUS_SUBMITTED) {
            throw new InvalidArgumentException('Hanya pengajuan skip berstatus Menunggu Persetujuan yang dapat diterapkan.');
        }

        if (! $this->canDecide($skip->maker_user_id, $actorUserId)) {
            throw new InvalidArgumentException('Pengaju tidak dapat menyetujui skip pokoknya sendiri.');
        }

        $claimed = CooperativeLoanSkip::query()
            ->whereKey($skip->id)
            ->where('status', self::STATUS_SUBMITTED)
            ->update(['status' => self::STATUS_APPLIED]);

        if ($claimed === 0) {
            throw new InvalidArgumentException('Pengajuan skip tidak lagi berstatus Menunggu Persetujuan.');
        }

        try {
            $summary = match ($skip->mode) {
                self::MODE_ACCELERATE => $this->applyAccelerate($skip),
                default => $this->applySkip($skip),
            };
        } catch (\Throwable $exception) {
            CooperativeLoanSkip::query()
                ->whereKey($skip->id)
                ->where('status', self::STATUS_APPLIED)
                ->update(['status' => self::STATUS_SUBMITTED]);

            throw new InvalidArgumentException('Penerapan skip gagal: '.$exception->getMessage());
        }

        return $summary;
    }

    /**
     * Terapkan mode skip pokok: baris target amount=0 (bunga tetap), sisip N baris
     * baru di ekor, dan tenor bertambah N.
     */
    private function applySkip(CooperativeLoanSkip $skip): array
    {
        return DB::connection('mysql')->transaction(function () use ($skip): array {
            $rows = DB::connection('mysql')->table('icu_dloan')
                ->where('mst_rec_id', $skip->loan_rec_id)
                ->orderBy('seqno')
                ->lockForUpdate()
                ->get()
                ->map(fn ($row): array => [
                    'rec_id' => (int) $row->rec_id,
                    'seqno' => (int) $row->seqno,
                    'periode' => (string) $row->periode,
                    'amount' => (int) $row->amount,
                    'int_amt' => (int) $row->int_amt,
                    'others' => (int) $row->others,
                    'descr' => (string) $row->descr,
                    'remarks' => (string) ($row->remarks ?? ''),
                    'outstand' => (int) $row->outstand,
                    'paidst' => (int) $row->paidst,
                ])
                ->all();

            $plan = $this->plan($rows, $skip->start_period, $skip->months_count);

            $this->assertNoActiveAllocations($plan['target_rec_ids']);

            $now = now();

            // Tandai baris target sebagai skip: pokok nol, bunga tetap.
            DB::connection('mysql')->table('icu_dloan')
                ->whereIn('rec_id', $plan['target_rec_ids'])
                ->where('paidst', 0)
                ->update(['amount' => 0, 'remarks' => 'Skip Principal', 'lupd' => $now]);

            // Sinkronkan salinan lokal agar rewalk outstand memakai nilai terbaru.
            $targetSet = array_flip($plan['target_rec_ids']);
            foreach ($rows as $index => $row) {
                if (isset($targetSet[$row['rec_id']])) {
                    $rows[$index]['amount'] = 0;
                }
            }

            // Sisipkan baris baru di ekor jadwal.
            $loanDescr = (string) DB::connection('mysql')->table('icu_mloan')
                ->where('rec_id', $skip->loan_rec_id)->value('descr');
            $totalRows = count($rows) + $skip->months_count;
            $principal = (int) DB::connection('mysql')->table('icu_mloan')
                ->where('rec_id', $skip->loan_rec_id)->value('principle');

            $running = $principal;
            $prepared = [];
            foreach ($rows as $row) {
                $running -= $row['amount'];
                $prepared[] = ['existing_rec_id' => $row['rec_id'], 'amount' => $row['amount'], 'outstand' => max(0, $running)];
            }
            foreach ($plan['new_rows'] as $newRow) {
                $running -= (int) $newRow['amount'];
                $prepared[] = ['existing_rec_id' => null, 'amount' => (int) $newRow['amount'], 'outstand' => max(0, $running), 'new_row' => $newRow];
            }

            $totalCount = count($prepared);
            $inserts = [];
            foreach ($prepared as $index => $row) {
                $seqno = $index + 1;

                if ($row['existing_rec_id'] === null) {
                    $inserts[] = [
                        'mst_rec_id' => $skip->loan_rec_id,
                        'periode' => $row['new_row']['periode'],
                        'seqno' => $seqno,
                        'totseqno' => $totalCount,
                        'descr' => mb_substr($loanDescr, 0, 30).' ('.str_pad((string) $seqno, 2, '0', STR_PAD_LEFT).'/'.str_pad((string) $totalCount, 2, '0', STR_PAD_LEFT).')',
                        'amount' => $row['amount'],
                        'rnd_amt' => 0,
                        'int_amt' => (int) $row['new_row']['int_amt'],
                        'rnd_int' => 0,
                        'others' => 0,
                        'outstand' => $row['outstand'],
                        'remarks' => 'Skip Principal',
                        'dseqno' => $totalCount - $seqno + 1,
                        'paidst' => 0,
                        'payno' => '',
                        'lupd' => $now,
                    ];

                    continue;
                }

                DB::connection('mysql')->table('icu_dloan')
                    ->where('rec_id', $row['existing_rec_id'])
                    ->update([
                        'totseqno' => $totalCount,
                        'dseqno' => $totalCount - $seqno + 1,
                        'outstand' => $row['outstand'],
                        'lupd' => $now,
                    ]);
            }

            DB::connection('mysql')->table('icu_dloan')->insert($inserts);

            // Term dan endper mengikuti jadwal terakhir aktual (bagian 31).
            // interamt/totalloan tidak disentuh: bunga tambahan = Biaya perpanjang pinjaman.
            DB::connection('mysql')->table('icu_mloan')
                ->where('rec_id', $skip->loan_rec_id)
                ->update(['term' => $totalRows, 'endper' => $plan['new_last_periode'], 'lupd' => $now]);

            return [
                'member_icuno' => $skip->member_icuno,
                'rows_skipped' => $plan['skipped_rows'],
                'rows_added' => $skip->months_count,
                'moved_principal' => $plan['moved_principal'],
                'extra_interest' => $plan['extra_interest'],
                'new_term' => $totalRows,
                'new_last_periode' => $plan['new_last_periode'],
            ];
        });
    }

    /**
     * Terapkan mode percepatan: N baris ekor dihapus, pokok + bunga seluruh baris
     * belum dibayar dikalkulasi ulang ke lebih sedikit baris, tenor berkurang N.
     */
    private function applyAccelerate(CooperativeLoanSkip $skip): array
    {
        return DB::connection('mysql')->transaction(function () use ($skip): array {
            $rows = DB::connection('mysql')->table('icu_dloan')
                ->where('mst_rec_id', $skip->loan_rec_id)
                ->orderBy('seqno')
                ->lockForUpdate()
                ->get()
                ->map(fn ($row): array => [
                    'rec_id' => (int) $row->rec_id,
                    'seqno' => (int) $row->seqno,
                    'periode' => (string) $row->periode,
                    'amount' => (int) $row->amount,
                    'int_amt' => (int) $row->int_amt,
                    'others' => (int) $row->others,
                    'descr' => (string) $row->descr,
                    'outstand' => (int) $row->outstand,
                    'paidst' => (int) $row->paidst,
                ])
                ->all();

            $plan = $this->acceleratePlan($rows, $skip->months_count);

            $this->assertNoActiveAllocations($plan['removed_rec_ids']);

            $now = now();

            $recalc = [];
            foreach ($plan['remaining_rows'] as $row) {
                $recalc[$row['rec_id']] = ['amount' => $row['amount'], 'int_amt' => $row['int_amt']];
            }
            $removedSet = array_flip($plan['removed_rec_ids']);

            $kept = [];
            foreach ($rows as $row) {
                if (isset($removedSet[$row['rec_id']])) {
                    continue;
                }

                $amount = $row['amount'];
                $intAmt = $row['int_amt'];
                $recalculated = isset($recalc[$row['rec_id']]);
                if ($recalculated) {
                    $amount = $recalc[$row['rec_id']]['amount'];
                    $intAmt = $recalc[$row['rec_id']]['int_amt'];
                }

                $kept[] = [
                    'rec_id' => $row['rec_id'],
                    'amount' => $amount,
                    'int_amt' => $intAmt,
                    'recalculated' => $recalculated,
                    'remarks' => $row['remarks'] ?? '',
                ];
            }

            $principal = (int) DB::connection('mysql')->table('icu_mloan')
                ->where('rec_id', $skip->loan_rec_id)->value('principle');
            $running = $principal;
            $totalCount = count($kept);

            foreach ($kept as $index => $item) {
                $running -= $item['amount'];
                $seqno = $index + 1;

                DB::connection('mysql')->table('icu_dloan')
                    ->where('rec_id', $item['rec_id'])
                    ->update([
                        'amount' => $item['amount'],
                        'int_amt' => $item['int_amt'],
                        'outstand' => max(0, $running),
                        'totseqno' => $totalCount,
                        'dseqno' => $totalCount - $seqno + 1,
                        'remarks' => $item['recalculated'] ? 'Percepatan' : $item['remarks'],
                        'lupd' => $now,
                    ]);
            }

            DB::connection('mysql')->table('icu_dloan')
                ->whereIn('rec_id', $plan['removed_rec_ids'])
                ->delete();

            DB::connection('mysql')->table('icu_mloan')
                ->where('rec_id', $skip->loan_rec_id)
                ->update(['term' => $totalCount, 'endper' => $plan['new_last_periode'], 'lupd' => $now]);

            return [
                'member_icuno' => $skip->member_icuno,
                'rows_removed' => $plan['removed_rows'],
                'rows_remaining' => $totalCount,
                'moved_principal' => $plan['moved_principal'],
                'retained_interest' => $plan['retained_interest'],
                'new_term' => $totalCount,
                'new_last_periode' => $plan['new_last_periode'],
            ];
        });
    }

    public function canDecide(int $makerUserId, int $actorUserId): bool
    {
        return $makerUserId !== $actorUserId;
    }

    public function assertTransition(string $currentStatus, string $targetStatus): void
    {
        if (! isset(self::TRANSITIONS[$currentStatus])) {
            throw new InvalidArgumentException("Status saat ini tidak dikenal: {$currentStatus}.");
        }

        if (! in_array($targetStatus, self::TRANSITIONS[$currentStatus], true)) {
            throw new InvalidArgumentException(
                'Perubahan status dari '.$this->statusLabel($currentStatus).' ke '.$this->statusLabel($targetStatus).' tidak diizinkan.'
            );
        }
    }

    public function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? ucfirst($status);
    }

    public function statusBadgeClass(string $status): string
    {
        return match ($status) {
            self::STATUS_SUBMITTED => 'bg-amber-50 text-amber-700 border-amber-200',
            self::STATUS_APPLIED => 'bg-blue-50 text-blue-700 border-blue-200',
            self::STATUS_REJECTED => 'bg-red-50 text-red-700 border-red-200',
            self::STATUS_CANCELLED => 'bg-gray-100 text-gray-600 border-gray-200',
            default => 'bg-gray-100 text-gray-600 border-gray-200',
        };
    }

    /**
     * Baris dengan alokasi pembayaran aktif tidak boleh diskip.
     *
     * @param  list<int>  $dloanRecIds
     */
    private function assertNoActiveAllocations(array $dloanRecIds): void
    {
        if ($dloanRecIds === []) {
            return;
        }

        $count = DB::connection('run')->table('coop_loan_payment_allocations as a')
            ->join('coop_loan_payments as p', 'p.id', '=', 'a.payment_id')
            ->whereIn('p.status', [LoanPaymentService::STATUS_SUBMITTED, LoanPaymentService::STATUS_VERIFIED])
            ->whereIn('a.dloan_rec_id', $dloanRecIds)
            ->count();

        if ($count > 0) {
            throw new InvalidArgumentException('Baris target memiliki alokasi pembayaran aktif. Selesaikan atau batalkan pembayaran tersebut dahulu.');
        }
    }
}
