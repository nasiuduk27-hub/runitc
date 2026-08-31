<?php

namespace App\Services\Cooperative;

use App\Models\Cooperative\CooperativeLoanPayment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pembayaran angsuran dengan maker-checker.
 *
 * Aturan MVP (konfirmasi bagian 34 dokumen):
 * - Alokasi hanya cicilan UTUH berurutan dari tertua; nominal harus tepat
 *   sama dengan jumlah N cicilan pertama yang belum dibayar.
 * - dbocr transaksi = D; trncd = 20; statrec mengikuti pola existing (=1).
 * - icu_mloan.statrec: 4 (Partial Payment) selama belum lunas, 5 (Loan Completed) saat lunas.
 * - icu_member.outstanding berkurang sebesar porsi pokok.
 */
class LoanPaymentService
{
    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_SUBMITTED => 'Menunggu Verifikasi',
        self::STATUS_VERIFIED => 'Terverifikasi & Diposting',
        self::STATUS_REJECTED => 'Ditolak',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        self::STATUS_SUBMITTED => [self::STATUS_VERIFIED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        self::STATUS_VERIFIED => [],
        self::STATUS_REJECTED => [],
        self::STATUS_CANCELLED => [],
    ];

    public const METHOD_TUNAI = 'tunai';

    public const METHOD_TRANSFER = 'transfer';

    public const METHOD_POTONG_GAJI = 'potong_gaji';

    /** @var array<string, string> */
    public const METHODS = [
        self::METHOD_TUNAI => 'Tunai',
        self::METHOD_TRANSFER => 'Transfer Bank',
        self::METHOD_POTONG_GAJI => 'Potong Gaji',
    ];

    private const TRNCD_INSTALLMENT = '20';

    public static function getInstallmentTrncd(): string
    {
        return self::TRNCD_INSTALLMENT;
    }

    /**
     * Alokasi nominal bebas ke baris jadwal tertunggak, berurutan dari tertua.
     *
     * Baris dianggap lunas hanya bila alokasi menutup sisa tagihannya penuh;
     * sisanya dicatat sebagai parsial pada tabel alokasi RUNITC.
     * Di dalam satu baris, dana dialokasikan mengikuti urutan dokumen bagian 8:
     * bunga -> biaya lain -> pokok.
     *
     * @param  list<array{rec_id: int, seqno: int, due: int, principal: int, interest: int, others: int, remaining: int}>  $unpaidRows  terurut seqno menaik
     * @return list<array{dloan_rec_id: int, seqno: int, amount_applied: int, covers_full: bool, principal_applied: int, interest_applied: int}>
     *
     * @throws InvalidArgumentException bila nominal tidak valid atau melebihi total sisa
     */
    public function allocate(int $amount, array $unpaidRows): array
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Nominal pembayaran harus lebih dari nol.');
        }

        $totalRemaining = array_sum(array_column($unpaidRows, 'remaining'));

        if ($totalRemaining <= 0) {
            throw new InvalidArgumentException('Pinjaman ini sudah tidak memiliki cicilan tertunggak.');
        }

        if ($amount > $totalRemaining) {
            throw new InvalidArgumentException(
                'Nominal melebihi total sisa tagihan ('.$totalRemaining.').'
            );
        }

        $allocations = [];
        $left = $amount;

        foreach ($unpaidRows as $row) {
            if ($left <= 0) {
                break;
            }

            if ($row['remaining'] <= 0) {
                continue;
            }

            $applied = min($row['remaining'], $left);
            $left -= $applied;

            [$interestApplied, $othersApplied, $principalApplied] = $this->splitByComponentOrder($applied, $row);

            $allocations[] = [
                'dloan_rec_id' => $row['rec_id'],
                'seqno' => $row['seqno'],
                'amount_applied' => $applied,
                'covers_full' => $applied === $row['remaining'],
                'principal_applied' => $principalApplied,
                'interest_applied' => $interestApplied + $othersApplied,
            ];
        }

        return $allocations;
    }

    /**
     * Urutan komponen dalam satu baris: bunga -> biaya lain -> pokok (bagian 8 dokumen).
     *
     * @return array{0: int, 1: int, 2: int} [bunga, biaya_lain, pokok]
     */
    private function splitByComponentOrder(int $applied, array $row): array
    {
        $interest = min($applied, max(0, (int) ($row['interest'] ?? 0)));
        $left = $applied - $interest;

        $others = min($left, max(0, (int) ($row['others'] ?? 0)));
        $left -= $others;

        return [$interest, $others, $left];
    }

    /**
     * statrec icu_mloan setelah pembayaran, mengikuti flow LN sys_seqflow:
     * masih ada baris tertunggak -> 4 (Partial Payment); lunas semua -> 5 (Loan Completed).
     */
    public function statrecAfterPayment(int $remainingUnpaidRows): int
    {
        return $remainingUnpaidRows > 0 ? 4 : 5;
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

    /**
     * Maker tidak boleh memverifikasi pembayarannya sendiri.
     */
    public function canVerify(int $makerUserId, int $actorUserId): bool
    {
        return $makerUserId !== $actorUserId;
    }

    /**
     * Nomor transaksi pembayaran PMT-{YY}{huruf bulan}-{urut}, melanjutkan urutan
     * historis terbesar pada icu_transaction maupun referensi req_frm_trxno bank_trx.
     */
    public function generatePaymentTrnno(?CarbonImmutable $now = null): string
    {
        $now ??= CarbonImmutable::now();

        $sequence = max(
            LoanPostingService::nextSequence('icu_transaction', 'trnno', 'PMT-%'),
            LoanPostingService::nextSequence('icu_bank_trx', 'req_frm_trxno', 'PMT-%'),
        );

        return LoanPostingService::formatLegacyTrnno('PMT', $now, $sequence);
    }

    /**
     * Baris jadwal belum dibayar milik satu pinjaman beserta sisa tagihannya,
     * memperhitungkan alokasi pembayaran yang masih berlaku (submitted/verified).
     *
     * @return list<array{rec_id: int, seqno: int, periode: string, due: int, principal: int, interest: int, others: int, remaining: int}>
     */
    public function rowsWithRemaining(int $loanRecId): array
    {
        $rows = DB::connection('mysql')->table('icu_dloan')
            ->where('mst_rec_id', $loanRecId)
            ->where('paidst', 0)
            ->orderBy('seqno')
            ->get(['rec_id', 'seqno', 'periode', 'amount', 'int_amt', 'others'])
            ->map(fn ($row): array => [
                'rec_id' => (int) $row->rec_id,
                'seqno' => (int) $row->seqno,
                'periode' => (string) $row->periode,
                'due' => (int) $row->amount + (int) $row->int_amt + (int) $row->others,
                'principal' => (int) $row->amount,
                'interest' => (int) $row->int_amt,
                'others' => (int) $row->others,
                'remaining' => 0,
            ])
            ->all();

        if ($rows === []) {
            return [];
        }

        $appliedByRow = DB::connection('run')->table('coop_loan_payment_allocations as a')
            ->join('coop_loan_payments as p', 'p.id', '=', 'a.payment_id')
            ->whereIn('p.status', [self::STATUS_SUBMITTED, self::STATUS_VERIFIED])
            ->where('p.loan_rec_id', $loanRecId)
            ->groupBy('a.dloan_rec_id')
            ->get(['a.dloan_rec_id', DB::raw('SUM(a.amount_applied) AS applied')])
            ->keyBy('dloan_rec_id');

        foreach ($rows as $index => $row) {
            $rows[$index]['remaining'] = max(
                0,
                $row['due'] - (int) ($appliedByRow[$row['rec_id']]->applied ?? 0)
            );
        }

        return $rows;
    }

    /**
     * Posting pembayaran ke tabel legacy dalam satu transaksi mysql.
     * Alur admin-only: posting dilakukan otomatis oleh pembuat (tanpa maker-checker).
     * Mengembalikan nomor transaksi PMT yang dibuat.
     *
     * @throws InvalidArgumentException ketika status pembayaran tidak sah
     */
    public function post(CooperativeLoanPayment $payment, int $actorUserId, ?string $pprd = null): string
    {
        if ($payment->status !== self::STATUS_SUBMITTED) {
            throw new InvalidArgumentException('Hanya pembayaran berstatus Menunggu Verifikasi yang dapat diposting.');
        }

        $allocations = $payment->allocations()->get();
        if ($allocations->isEmpty()) {
            throw new InvalidArgumentException('Alokasi pembayaran kosong.');
        }

        // Klaim atomik: submitted -> verified (anti double-posting).
        $claimed = CooperativeLoanPayment::query()
            ->whereKey($payment->id)
            ->where('status', self::STATUS_SUBMITTED)
            ->update(['status' => self::STATUS_VERIFIED]);

        if ($claimed === 0) {
            throw new InvalidArgumentException('Pembayaran tidak lagi berstatus Menunggu Verifikasi.');
        }

        try {
            $trnno = DB::connection('mysql')->transaction(function () use ($payment, $allocations, $pprd): string {
                $trnno = $this->generatePaymentTrnno();
                $memberRefno = (string) DB::connection('mysql')->table('icu_member')
                    ->where('rec_id', $payment->member_rec_id)->value('refno');
                $loanTrnno = (string) DB::connection('mysql')->table('icu_mloan')
                    ->where('rec_id', $payment->loan_rec_id)->value('trnno');

                DB::connection('mysql')->table('icu_transaction')->insert([
                    // Periode target default = periode berjalan, dapat dioverride batch bulanan.
                    'pprd' => $pprd ?? CooperativePeriod::current(),
                    'trncd' => self::TRNCD_INSTALLMENT,
                    'trnno' => $trnno,
                    'trndt' => $payment->payment_date->toDateString(),
                    'icu_rec_id' => $payment->member_rec_id,
                    'empno' => $memberRefno,
                    'descr' => mb_substr('Angsuran '.$loanTrnno.' '.$payment->member_icuno, 0, 50),
                    'dbocr' => 'D',
                    'basic_amt' => $payment->principal_portion,
                    'int_amt' => $payment->interest_portion,
                    'amount' => $payment->amount,
                    'notes' => mb_substr((string) ($payment->notes ?? ''), 0, 200),
                    'entdt' => now(),
                    'lupd' => now(),
                    'entusr' => 'RUN',
                    'refno' => '',
                    'statrec' => 1,
                    'statrec2' => 0,
                ]);

                foreach ($allocations as $allocation) {
                    // Partial payment tidak menyentuh icu_dloan; baris ditandai
                    // lunas hanya ketika alokasi menutup tagihannya penuh.
                    if (! $allocation->covers_full) {
                        continue;
                    }

                    $updated = DB::connection('mysql')->table('icu_dloan')
                        ->where('rec_id', $allocation->dloan_rec_id)
                        ->where('paidst', 0)
                        ->update(['paidst' => 1, 'payno' => $trnno, 'lupd' => now()]);

                    if ($updated === 0) {
                        throw new \RuntimeException('Baris jadwal rec_id '.$allocation->dloan_rec_id.' sudah ditandai dibayar.');
                    }
                }

                $remainingUnpaid = (int) DB::connection('mysql')->table('icu_dloan')
                    ->where('mst_rec_id', $payment->loan_rec_id)
                    ->where('paidst', 0)
                    ->count();

                DB::connection('mysql')->table('icu_mloan')
                    ->where('rec_id', $payment->loan_rec_id)
                    ->increment('paid', $payment->amount, [
                        'statrec' => $this->statrecAfterPayment($remainingUnpaid),
                        'lupd' => now(),
                    ]);

                DB::connection('mysql')->table('icu_member')
                    ->where('rec_id', $payment->member_rec_id)
                    ->decrement('outstanding', $payment->principal_portion);

                return $trnno;
            });
        } catch (\Throwable $exception) {
            CooperativeLoanPayment::query()
                ->whereKey($payment->id)
                ->where('status', self::STATUS_VERIFIED)
                ->whereNull('icu_trnno')
                ->update(['status' => self::STATUS_SUBMITTED]);

            throw new InvalidArgumentException('Posting pembayaran gagal: '.$exception->getMessage());
        }

        $payment->forceFill(['icu_trnno' => $trnno])->save();

        return $trnno;
    }

    public function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? ucfirst($status);
    }

    public function statusBadgeClass(string $status): string
    {
        return match ($status) {
            self::STATUS_SUBMITTED => 'bg-amber-50 text-amber-700 border-amber-200',
            self::STATUS_VERIFIED => 'bg-green-50 text-green-700 border-green-200',
            self::STATUS_REJECTED => 'bg-red-50 text-red-700 border-red-200',
            self::STATUS_CANCELLED => 'bg-gray-100 text-gray-600 border-gray-200',
            default => 'bg-gray-100 text-gray-600 border-gray-200',
        };
    }
}
