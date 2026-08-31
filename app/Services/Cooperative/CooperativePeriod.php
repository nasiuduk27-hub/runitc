<?php

namespace App\Services\Cooperative;

use Carbon\CarbonImmutable;

/**
 * Utilitas periode format YYYYMM yang dipakai tabel existing icu%.
 *
 * Penambahan bulan harus kalender-benar: 202612 + 1 = 202701, bukan 202613.
 */
final class CooperativePeriod
{
    public const LENGTH = 6;

    public const MONTHS_SHORT = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    public const MONTHS_FULL = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    public static function current(): string
    {
        return CarbonImmutable::now()->format('Ym');
    }

    /**
     * Tambah bulan secara kalender-benar pada periode YYYYMM.
     */
    public static function addMonths(string $periode, int $months): string
    {
        if (! self::isValid($periode)) {
            return $periode;
        }

        return CarbonImmutable::createFromFormat('!Ym', $periode)
            ->startOfMonth()
            ->addMonthsNoOverflow($months)
            ->format('Ym');
    }

    /**
     * Label manusiawi: 202608 -> "Agu 2026". Nilai tidak valid dikembalikan apa adanya.
     */
    public static function label(string $periode): string
    {
        if (! self::isValid($periode)) {
            return $periode;
        }

        $date = CarbonImmutable::createFromFormat('!Ym', $periode);

        return self::MONTHS_SHORT[(int) $date->format('n')].' '.$date->format('Y');
    }

    /**
     * Label panjang: 202608 -> "Agustus 2026". Nilai tidak valid dikembalikan apa adanya.
     */
    public static function longLabel(string $periode): string
    {
        if (! self::isValid($periode)) {
            return $periode;
        }

        $date = CarbonImmutable::createFromFormat('!Ym', $periode);

        return self::MONTHS_FULL[(int) $date->format('n')].' '.$date->format('Y');
    }

    /**
     * Label pendek untuk sumbu grafik: 202608 -> "Agu".
     */
    public static function shortLabel(string $periode): string
    {
        if (! self::isValid($periode)) {
            return $periode;
        }

        return self::MONTHS_SHORT[(int) substr($periode, 4, 2)];
    }

    public static function isValid(string $periode): bool
    {
        return preg_match('/^\d{'.self::LENGTH.'}$/', $periode) === 1
            && (int) substr($periode, 4, 2) >= 1
            && (int) substr($periode, 4, 2) <= 12;
    }
}
