<?php

namespace App\Services\Cooperative;

use App\Models\Cooperative\CooperativeLoanApplication;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Memosting pengajuan berstatus approved menjadi pinjaman aktual di icu_mloan + icu_dloan.
 *
 * Ini satu-satunya titik yang menulis ke tabel legacy. Aturan penulisan mengikuti
 * pola data existing (pinjam.xlsx + sampel baris):
 * - bunga flat: interamt = principle x rate/12 x tenor
 * - avgmon = ROUND(principle/tenor), cicilan terakhir menyesuaikan sisa (remarks Rounding)
 * - outstand = sisa pokok, menurun sebesar amount, tepat 0 di akhir
 * - dseqno = urutan mundur (totseqno - seqno + 1)
 * - paid = 0, statrec = 0, paidst = 0 mengikuti kondisi existing
 */
class LoanPostingService
{
    private const TRNCD_LOAN = '21';

    public function __construct(private readonly LoanApplicationService $applications) {}

    /**
     * Posting pengajuan approved. Mengembalikan rec_id pinjaman yang dibuat.
     *
     * @throws InvalidArgumentException ketika status/pembuat tidak memenuhi syarat
     */
    public function post(CooperativeLoanApplication $application, int $actorUserId): int
    {
        if ($application->status !== LoanApplicationService::STATUS_APPROVED) {
            throw new InvalidArgumentException('Hanya pengajuan berstatus Disetujui yang dapat diposting.');
        }

        if (! $this->applications->canDecide($application->applicant_user_id, $actorUserId)) {
            throw new InvalidArgumentException('Pembuat pengajuan tidak dapat memposting sendiri.');
        }

        $schedule = $application->schedule();
        if ($schedule === []) {
            throw new InvalidArgumentException('Snapshot jadwal kosong, posting dibatalkan.');
        }

        // Langkah 1: klaim status secara atomik (gate anti double-posting).
        $claimed = CooperativeLoanApplication::query()
            ->whereKey($application->id)
            ->where('status', LoanApplicationService::STATUS_APPROVED)
            ->update(['status' => LoanApplicationService::STATUS_POSTED]);

        if ($claimed === 0) {
            throw new InvalidArgumentException('Pengajuan tidak lagi berstatus Disetujui.');
        }

        try {
            // Langkah 2: tulis ke tabel legacy dalam satu transaksi mysql.
            $loanRecId = (int) DB::connection('mysql')->transaction(function () use ($application, $schedule): int {
                $trnno = $this->generateTrnno();

                /** @var array<string, int|float|string|null> $mloanRow */
                $mloanRow = $this->buildMloanRow($application, $schedule, $trnno, CooperativePeriod::current());

                $mloanId = (int) DB::connection('mysql')->table('icu_mloan')->insertGetId($this->withoutNulls($mloanRow), 'rec_id');

                DB::connection('mysql')->table('icu_dloan')->insert(
                    array_map(fn (array $row): array => $this->withoutNulls($row), $this->buildDloanRows($mloanId, $application, $schedule))
                );

                // Outstanding anggota bertambah sebesar pokok (jawaban bagian 34 poin 12).
                DB::connection('mysql')->table('icu_member')
                    ->where('rec_id', $application->member_rec_id)
                    ->increment('outstanding', $application->principal_amount);

                return $mloanId;
            });
        } catch (\Throwable $exception) {
            // Langkah 3: lepas klaim agar posting dapat dicoba ulang.
            CooperativeLoanApplication::query()
                ->whereKey($application->id)
                ->where('status', LoanApplicationService::STATUS_POSTED)
                ->whereNull('posted_loan_rec_id')
                ->update(['status' => LoanApplicationService::STATUS_APPROVED]);

            throw new InvalidArgumentException('Posting ke tabel pinjaman gagal: '.$exception->getMessage());
        }

        $application->forceFill(['posted_loan_rec_id' => $loanRecId])->save();

        return $loanRecId;
    }

    /**
     * Format nomor transaksi legacy generik: {PREFIX}-{YY}{huruf bulan}-{urut 4 digit}.
     * Huruf bulan: A=Jan s.d. L=Dec.
     */
    public static function formatLegacyTrnno(string $prefix, CarbonImmutable $now, int $sequence): string
    {
        $monthLetter = chr(ord('A') + (int) $now->format('n') - 1);

        return $prefix.'-'.$now->format('y').$monthLetter.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public static function formatTrnno(CarbonImmutable $now, int $sequence): string
    {
        return self::formatLegacyTrnno('LON', $now, $sequence);
    }

    /**
     * Urutan berikutnya melanjutkan sufiks numerik nomor legacy pada tabel/kolom tertentu.
     */
    public static function nextSequence(string $table, string $column, string $likePattern): int
    {
        return (int) DB::connection('mysql')->table($table)
            ->where($column, 'like', $likePattern)
            ->selectRaw("COALESCE(MAX(CAST(SUBSTRING_INDEX({$column}, '-', -1) AS UNSIGNED)), 0) AS last_seq")
            ->value('last_seq') + 1;
    }

    /**
     * Nomor transaksi pinjaman melanjutkan urutan LON terbesar yang sudah ada.
     */
    public function generateTrnno(?CarbonImmutable $now = null): string
    {
        $now ??= CarbonImmutable::now();

        $lastSequence = (int) DB::connection('mysql')->table('icu_mloan')
            ->where('trnno', 'like', 'LON-%')
            ->selectRaw("COALESCE(MAX(CAST(SUBSTRING_INDEX(trnno, '-', -1) AS UNSIGNED)), 0) AS last_seq")
            ->value('last_seq');

        return self::formatTrnno($now, $lastSequence + 1);
    }

    /**
     * Baris header icu_mloan dari pengajuan dan snapshot jadwalnya.
     *
     * @param  list<array<string, int|bool|string>>  $schedule
     * @return array<string, int|float|string|null>
     */
    public function buildMloanRow(CooperativeLoanApplication $application, array $schedule, string $trnno, string $processPeriod): array
    {
        $principal = $application->principal_amount;
        $tenor = count($schedule);
        $monthlyInterest = (int) ($schedule[0]['int_amt'] ?? 0);
        $totalInterest = array_sum(array_column($schedule, 'int_amt'));
        $lastPeriode = end($schedule)['periode'];

        return [
            'pprd' => $processPeriod,
            'trncd' => self::TRNCD_LOAN,
            'trnno' => $trnno,
            'trndt' => now()->toDateString(),
            'icu_rec_id' => $application->member_rec_id,
            'descr' => $this->loanDescrLabel($application),
            'principle' => $principal,
            'interamt' => (int) $totalInterest,
            'interest' => $application->annual_rate_percent,
            'int_overdue' => 0,
            'bnk_charge' => $application->admin_fee,
            'bnktrx_no' => $application->fund_release_method === 'transfer' ? (string) $application->bank_accno : '',
            'totalloan' => $principal + (int) $totalInterest,
            'paid' => 0,
            'avgmon' => (int) ($schedule[0]['amount'] ?? 0),
            'avgint' => $monthlyInterest,
            'monthly' => (int) ($schedule[0]['total'] ?? 0),
            'term' => $tenor,
            'startper' => (string) ($schedule[0]['periode'] ?? ''),
            'endper' => (string) $lastPeriode,
            'remarks' => 'Posting '.$application->member_icuno,
            'statrec' => 0,
            'entdt' => now(),
            'lupd' => now(),
            'entusr' => 'RUN',
        ];
    }

    /**
     * Label pinjaman yang mengikuti pola existing (mis. "PINJAMAN <NAMA ANGGOTA>").
     * Keperluan pengajuan tidak dipakai — keperluan tetap tersimpan di tabel pengajuan.
     */
    private function loanDescrLabel(CooperativeLoanApplication $application): string
    {
        return mb_substr('PINJAMAN '.trim((string) $application->member_name), 0, 50);
    }

    /**
     * Baris jadwal icu_dloan dari snapshot simulasi.
     *
     * @param  list<array<string, int|bool|string>>  $schedule
     * @return list<array<string, int|float|string|null>>
     */
    public function buildDloanRows(int $mloanRecId, CooperativeLoanApplication $application, array $schedule): array
    {
        $tenor = count($schedule);
        $rows = [];
        $outstanding = $application->principal_amount;
        $label = $this->loanDescrLabel($application);

        foreach ($schedule as $index => $row) {
            $seqno = $index + 1;
            $principalDue = (int) $row['amount'];
            $outstanding -= $principalDue;

            $rows[] = [
                'mst_rec_id' => $mloanRecId,
                'periode' => (string) $row['periode'],
                'seqno' => $seqno,
                'totseqno' => $tenor,
                'descr' => mb_substr($label, 0, 30).' ('.str_pad((string) $seqno, 2, '0', STR_PAD_LEFT).'/'.str_pad((string) $tenor, 2, '0', STR_PAD_LEFT).')',
                'amount' => $principalDue,
                'rnd_amt' => 0,
                'int_amt' => (int) $row['int_amt'],
                'rnd_int' => 0,
                'others' => 0,
                'outstand' => max(0, $outstanding),
                'remarks' => ! empty($row['rounding']) ? 'Rounding' : '',
                'dseqno' => $tenor - $seqno + 1,
                'paidst' => 0,
                'payno' => '',
                'lupd' => now(),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function withoutNulls(array $row): array
    {
        return collect($row)->reject(fn ($value): bool => $value === null)->all();
    }
}
