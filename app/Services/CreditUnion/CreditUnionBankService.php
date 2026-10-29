<?php

namespace App\Services\CreditUnion;

final class CreditUnionBankService
{
    public const MANDIRI_NOTE = 'Bank Mandiri, tanpa biaya admin';

    public const MANDIRI_FREE_FEE_LABEL = 'Rp 0 — Bank Mandiri, tanpa biaya admin';

    /**
     * Kode master bank Mandiri yang dikenal sistem.
     * MAN: kode resmi di sys_msttable (tbl_code=51).
     * MANDIRI: kode warisan di config/bank_list.php.
     */
    private const MANDIRI_CODES = ['MAN', 'MANDIRI'];

    /**
     * Nama resmi Bank Mandiri ternormalisasi (huruf kapital).
     */
    private const MANDIRI_NAMES = [
        'BANK MANDIRI',
        'MANDIRI',
        'MANDIRI - BANK MANDIRI',
        'PT BANK MANDIRI (PERSERO) TBK',
        'PT. BANK MANDIRI (PERSERO) TBK',
    ];

    /**
     * Tentukan apakah bank adalah Bank Mandiri.
     *
     * Pemeriksaan utama: kecocokan persis pada bank_bnkcd ('MAN' atau 'MANDIRI').
     * Jalur evaluasi teks hanya dipakai bila kode kosong/tidak tersedia,
     * dengan pengecualian mutlak untuk SYARIAH dan TASPEN.
     */
    public static function isMandiri(?string $bankCode, ?string $bankName = null): bool
    {
        $code = strtoupper(trim((string) $bankCode));

        if ($code !== '') {
            return in_array($code, self::MANDIRI_CODES, true);
        }

        $name = strtoupper(trim((string) $bankName));

        if ($name === '') {
            return false;
        }

        // Pengecualian mutlak: bank lain yang memuat kata 'MANDIRI' bukan Bank Mandiri
        if (str_contains($name, 'SYARIAH') || str_contains($name, 'TASPEN')) {
            return false;
        }

        return in_array($name, self::MANDIRI_NAMES, true);
    }

    /**
     * Biaya admin untuk bank yang dipilih: Rp0 bila Mandiri, default setting bila bukan.
     */
    public static function resolveAdminFee(?string $bankCode, ?string $bankName = null): int
    {
        if (self::isMandiri($bankCode, $bankName)) {
            return 0;
        }

        return CreditUnionSettingsService::defaultAdminFee();
    }

    /**
     * True bila pengajuan menggunakan rekening Bank Mandiri dan biaya tersimpan bernilai 0.
     */
    public static function isMandiriFreeFee(int $adminFee, ?string $bankCode, ?string $bankName = null): bool
    {
        return $adminFee === 0 && self::isMandiri($bankCode, $bankName);
    }

    /**
     * Format tampilan biaya admin untuk permukaan tampilan (form create, detail).
     */
    public static function formatAdminFeeDisplay(int $adminFee, ?string $bankCode, ?string $bankName = null): string
    {
        if (self::isMandiriFreeFee($adminFee, $bankCode, $bankName)) {
            return self::MANDIRI_FREE_FEE_LABEL;
        }

        return 'Rp '.number_format($adminFee, 0, ',', '.');
    }

    /**
     * Format baris biaya admin untuk teks email (email kolektif & email pencairan).
     * Pada kasus Mandiri + fee 0, sufiks tipe (include/exclude) digantikan keterangan Mandiri.
     */
    public static function formatAdminFeeEmailLine(int $adminFee, string $feeType, ?string $bankCode, ?string $bankName = null): string
    {
        if (self::isMandiriFreeFee($adminFee, $bankCode, $bankName)) {
            return self::MANDIRI_FREE_FEE_LABEL;
        }

        return 'Rp '.number_format($adminFee, 0, ',', '.')." ({$feeType})";
    }
}
