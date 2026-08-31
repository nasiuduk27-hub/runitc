<?php

namespace App\Services\Cooperative;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class LoanSimulationService
{
    public const METHOD_FLAT = 'flat';

    public const METHOD_EFFECTIVE = 'effective';

    public const METHOD_ANNUITY = 'annuity';

    /** @var array<string, string> */
    public const METHODS = [
        self::METHOD_FLAT => 'Flat',
        self::METHOD_EFFECTIVE => 'Efektif',
        self::METHOD_ANNUITY => 'Anuitas',
    ];

    public const MIN_PRINCIPAL = 1;

    public const MAX_PRINCIPAL = 10_000_000_000;

    public const MIN_TENOR_MONTHS = 1;

    public const MAX_TENOR_MONTHS = 120;

    /**
     * Hitung simulasi pinjaman.
     *
     * Kolom jadwal mengikuti pola tabel icu_dloan:
     * periode (YYYYMM), seqno, amount (pokok), int_amt (bunga),
     * outstand (sisa pokok setelah angsuran).
     *
     * @return array{summary: array<string, int|float|string>, schedule: list<array<string, int|bool|string>>}
     */
    public function simulate(float|int $principalAmount, int $tenorMonths, float $annualRatePercent, string $method): array
    {
        $principal = (int) round($principalAmount);

        if ($principal < self::MIN_PRINCIPAL || $principal > self::MAX_PRINCIPAL) {
            throw new InvalidArgumentException('Jumlah kredit harus antara Rp 1 sampai Rp 10.000.000.000.');
        }

        if ($tenorMonths < self::MIN_TENOR_MONTHS || $tenorMonths > self::MAX_TENOR_MONTHS) {
            throw new InvalidArgumentException('Jangka waktu harus antara 1 sampai '.self::MAX_TENOR_MONTHS.' bulan.');
        }

        if ($annualRatePercent < 0 || $annualRatePercent > 100) {
            throw new InvalidArgumentException('Bunga per tahun harus antara 0 sampai 100 persen.');
        }

        if (! isset(self::METHODS[$method])) {
            throw new InvalidArgumentException('Jenis kredit tidak dikenali.');
        }

        // Flat: bunga bulanan konstan dan dihitung dari pokok awal,
        // bukan dari sisa pokok berjalan (sesuai pola pinjam.xlsx).
        $flatMonthlyInterest = fn (): int => $this->monthlyInterest($principal, $annualRatePercent);

        $schedule = match ($method) {
            self::METHOD_FLAT => $this->buildSchedule($principal, $this->principalDues($principal, $tenorMonths), $flatMonthlyInterest),
            self::METHOD_EFFECTIVE => $this->buildSchedule($principal, $this->principalDues($principal, $tenorMonths), fn (int $outstanding): int => $this->monthlyInterest($outstanding, $annualRatePercent)),
            self::METHOD_ANNUITY => $this->annuityRows($principal, $tenorMonths, $annualRatePercent),
            default => throw new InvalidArgumentException('Jenis kredit tidak dikenali.'),
        };

        return [
            'summary' => $this->buildSummary($method, $principal, $tenorMonths, $annualRatePercent, $schedule),
            'schedule' => $schedule,
        ];
    }

    /**
     * Pokok angsuran per bulan mengikuti pola sistem lama (pinjam.xlsx):
     * normal = ROUND(principal / tenor), dan cicilan terakhir menyesuaikan
     * sisa pokok sehingga outstanding akhir tepat 0.
     *
     * @return list<int>
     */
    private function principalDues(int $principal, int $tenor): array
    {
        if ($tenor === 1) {
            return [$principal];
        }

        $normal = (int) round($principal / $tenor);
        $cap = intdiv($principal, $tenor - 1);
        $normal = min($normal, $cap);

        $dues = array_fill(0, $tenor - 1, $normal);
        $dues[] = $principal - ($normal * ($tenor - 1));

        return $dues;
    }

    private function monthlyInterest(int $outstandingPrincipal, float $annualRatePercent): int
    {
        return (int) round($outstandingPrincipal * $annualRatePercent / 100 / 12);
    }

    /**
     * @param  list<int>  $principalDues
     * @param  callable(int): int  $interestFor
     * @return list<array<string, int|bool|string>>
     */
    private function buildSchedule(int $principal, array $principalDues, callable $interestFor): array
    {
        $tenor = count($principalDues);
        $normalDue = $tenor > 1 ? $principalDues[0] : $principal;
        $outstanding = $principal;
        $schedule = [];

        foreach ($principalDues as $index => $principalDue) {
            $seqno = $index + 1;
            $interestDue = $interestFor($outstanding);
            $outstanding -= $principalDue;

            $schedule[] = [
                'seqno' => $seqno,
                'periode' => $this->periode($seqno),
                'amount' => $principalDue,
                'int_amt' => $interestDue,
                'total' => $principalDue + $interestDue,
                'outstand' => $outstanding,
                'rounding' => $seqno === $tenor && $principalDue !== $normalDue,
            ];
        }

        return $schedule;
    }

    /**
     * @return list<array<string, int|bool|string>>
     */
    private function annuityRows(int $principal, int $tenor, float $annualRatePercent): array
    {
        $ratePerMonth = $annualRatePercent / 100 / 12;
        $installment = $ratePerMonth > 0
            ? (int) round($principal * $ratePerMonth / (1 - (1 + $ratePerMonth) ** (-$tenor)))
            : (int) ceil($principal / $tenor);

        $outstanding = $principal;
        $schedule = [];

        for ($seqno = 1; $seqno <= $tenor; $seqno++) {
            $interestDue = min($this->monthlyInterest($outstanding, $annualRatePercent), $installment);
            $principalDue = $installment - $interestDue;

            if ($seqno === $tenor || $principalDue >= $outstanding) {
                $principalDue = $outstanding;
            }

            $outstanding -= $principalDue;

            $schedule[] = [
                'seqno' => $seqno,
                'periode' => $this->periode($seqno),
                'amount' => $principalDue,
                'int_amt' => $interestDue,
                'total' => $principalDue + $interestDue,
                'outstand' => $outstanding,
                'rounding' => $seqno === $tenor && ($principalDue + $interestDue) !== $installment,
            ];
        }

        return $schedule;
    }

    /**
     * Periode format YYYYMM menggunakan penambahan bulan kalender
     * agar 202612 berikutnya menjadi 202701, bukan 202613.
     */
    private function periode(int $seqno): string
    {
        return CarbonImmutable::now()
            ->startOfMonth()
            ->addMonthsNoOverflow($seqno - 1)
            ->format('Ym');
    }

    /**
     * @param  list<array<string, int|bool|string>>  $schedule
     * @return array<string, int|float|string>
     */
    private function buildSummary(string $method, int $principal, int $tenor, float $annualRatePercent, array $schedule): array
    {
        $totalInterest = array_sum(array_column($schedule, 'int_amt'));

        return [
            'method' => $method,
            'method_label' => self::METHODS[$method],
            'principal' => $principal,
            'tenor_months' => $tenor,
            'annual_rate' => $annualRatePercent,
            'first_installment' => $schedule[0]['total'],
            'last_installment' => $schedule[$tenor - 1]['total'],
            'total_interest' => $totalInterest,
            'total_payment' => $principal + $totalInterest,
        ];
    }
}
