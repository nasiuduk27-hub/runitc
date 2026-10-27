<?php

namespace App\Services\CreditUnion;

use InvalidArgumentException;

/**
 * Helper laporan credit union — fungsi murni yang mudah diuji.
 */
class ReportService
{
    public const DUE_PAID = 'paid';

    public const DUE_OVERDUE = 'overdue';

    public const DUE_NOW = 'due_now';

    public const DUE_UPCOMING = 'upcoming';

    /** @var array<string, string> */
    public const DUE_LABELS = [
        self::DUE_PAID => 'Lunas',
        self::DUE_OVERDUE => 'Terlewat',
        self::DUE_NOW => 'Jatuh Tempo Kini',
        self::DUE_UPCOMING => 'Akan Datang',
    ];

    /**
     * Label flow LN sys_seqflow untuk icu_mloan.statrec.
     *
     * @var array<int, string>
     */
    public const LN_STATUS_LABELS = [
        0 => 'Draft',
        1 => 'Waiting Approval',
        2 => 'Transfer List',
        3 => 'Loan Submitted',
        4 => 'Partial Payment',
        5 => 'Loan Completed',
        6 => 'Loan Rejected',
    ];

    /**
     * Klasifikasi satu baris jadwal terhadap periode acuan (YYYYMM).
     *
     * @throws InvalidArgumentException bila format periode tidak valid
     */
    public function classifyInstallment(int $paidst, string $periode, string $asOfPeriod): string
    {
        if (! CreditUnionPeriod::isValid($periode) || ! CreditUnionPeriod::isValid($asOfPeriod)) {
            throw new InvalidArgumentException('Format periode harus YYYYMM.');
        }

        if ($paidst === 1) {
            return self::DUE_PAID;
        }

        return match (true) {
            $periode < $asOfPeriod => self::DUE_OVERDUE,
            $periode === $asOfPeriod => self::DUE_NOW,
            default => self::DUE_UPCOMING,
        };
    }

    public function dueLabel(string $classification): string
    {
        return self::DUE_LABELS[$classification] ?? ucfirst($classification);
    }

    public function lnStatusLabel(int|string|null $statrec): string
    {
        return self::LN_STATUS_LABELS[(int) $statrec] ?? 'Kode '.(string) $statrec;
    }

    /**
     * Rentang periode default: Januari tahun berjalan s.d. periode sekarang.
     *
     * @return array{from: string, to: string}
     */
    public function defaultSavingsRange(): array
    {
        $current = CreditUnionPeriod::current();

        return ['from' => substr($current, 0, 4).'01', 'to' => $current];
    }

    /**
     * Agregasi pendapatan bunga dari baris jadwal pinjaman.
     * Aturan:
     * - Hanya cicilan terbayar (paidst = 1)
     * - Hanya bunga (int_amt), others diabaikan
     * - Dikelompokkan per periode dalam rentang [from, to] urut naik
     *
     * @param  iterable<array<string, mixed>|object>  $rows
     * @return array{
     *     rows: list<array{periode: string, installment_count: int, interest_total: int}>,
     *     totals: array{installments: int, interest: int}
     * }
     */
    public function aggregateInterestRevenue(iterable $rows, ?string $from = null, ?string $to = null): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $item = (array) $row;
            if ((int) ($item['paidst'] ?? 0) !== 1) {
                continue;
            }

            $periode = (string) ($item['periode'] ?? '');
            if (! CreditUnionPeriod::isValid($periode)) {
                continue;
            }

            if ($from !== null && $from !== '' && $periode < $from) {
                continue;
            }
            if ($to !== null && $to !== '' && $periode > $to) {
                continue;
            }

            $interest = (int) ($item['int_amt'] ?? 0);

            if (! isset($grouped[$periode])) {
                $grouped[$periode] = [
                    'periode' => $periode,
                    'installment_count' => 0,
                    'interest_total' => 0,
                ];
            }

            $grouped[$periode]['installment_count']++;
            $grouped[$periode]['interest_total'] += $interest;
        }

        ksort($grouped);

        $resultRows = array_values($grouped);
        $totalInstallments = (int) array_sum(array_column($resultRows, 'installment_count'));
        $totalInterest = (int) array_sum(array_column($resultRows, 'interest_total'));

        return [
            'rows' => $resultRows,
            'totals' => [
                'installments' => $totalInstallments,
                'interest' => $totalInterest,
            ],
        ];
    }
}
