<?php

namespace App\Services\Cooperative;

use InvalidArgumentException;

/**
 * Helper laporan koperasi — fungsi murni yang mudah diuji.
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
        if (! CooperativePeriod::isValid($periode) || ! CooperativePeriod::isValid($asOfPeriod)) {
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
        $current = CooperativePeriod::current();

        return ['from' => substr($current, 0, 4).'01', 'to' => $current];
    }
}
