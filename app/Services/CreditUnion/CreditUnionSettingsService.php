<?php

namespace App\Services\CreditUnion;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pengaturan modul credit union yang dikelola admin (superadmin / CU Admin).
 *
 * Nilai disimpan di tabel system_settings (koneksi run) agar satu sumber
 * antara halaman System Settings superadmin dan halaman pengaturan credit union.
 */
final class CreditUnionSettingsService
{
    public const KEY_DEFAULT_RATE = 'cu_default_loan_rate';

    public const KEY_DEFAULT_METHOD = 'cu_default_loan_method';

    public const KEY_DEFAULT_ADMIN_FEE = 'cu_default_admin_fee';

    public const KEY_MINIMUM_SAVINGS_BALANCE = 'cu_minimum_savings_balance';

    public const KEY_BANK_ACCOUNT_BANK = 'cu_bank_account_bank';

    public const KEY_BANK_ACCOUNT_NO = 'cu_bank_account_no';

    public const KEY_BANK_ACCOUNT_NAME = 'cu_bank_account_name';

    public const DEFAULT_RATE = 6.0;

    public const DEFAULT_METHOD = LoanSimulationService::METHOD_FLAT;

    public const DEFAULT_ADMIN_FEE = 0;

    public const DEFAULT_MINIMUM_SAVINGS_BALANCE = 0;

    public static function defaultRate(): float
    {
        return (float) self::value(self::KEY_DEFAULT_RATE, (string) self::DEFAULT_RATE);
    }

    public static function defaultMethod(): string
    {
        $value = self::value(self::KEY_DEFAULT_METHOD, self::DEFAULT_METHOD);
        $method = trim((string) $value);

        return isset(LoanSimulationService::METHODS[$method]) ? $method : self::DEFAULT_METHOD;
    }

    public static function defaultAdminFee(): int
    {
        return max(0, (int) self::value(self::KEY_DEFAULT_ADMIN_FEE, (string) self::DEFAULT_ADMIN_FEE));
    }

    public static function minimumSavingsBalance(): int
    {
        return max(0, (int) self::value(self::KEY_MINIMUM_SAVINGS_BALANCE, (string) self::DEFAULT_MINIMUM_SAVINGS_BALANCE));
    }

    public static function bankAccountBank(): string
    {
        return trim(self::value(self::KEY_BANK_ACCOUNT_BANK, ''));
    }

    public static function bankAccountNo(): string
    {
        return trim(self::value(self::KEY_BANK_ACCOUNT_NO, ''));
    }

    public static function bankAccountName(): string
    {
        return trim(self::value(self::KEY_BANK_ACCOUNT_NAME, ''));
    }

    /**
     * Rekening koperasi untuk penerimaan transfer anggota; null bila belum diisi.
     *
     * @return array{bank: string, account_no: string, account_name: string}|null
     */
    public static function bankAccount(): ?array
    {
        $bank = self::bankAccountBank();
        $accountNo = self::bankAccountNo();
        $accountName = self::bankAccountName();

        if ($bank === '' && $accountNo === '' && $accountName === '') {
            return null;
        }

        return ['bank' => $bank, 'account_no' => $accountNo, 'account_name' => $accountName];
    }

    public static function ensureDefaults(): void
    {
        self::ensure(self::KEY_DEFAULT_RATE, (string) self::DEFAULT_RATE, 'string', 'Bunga pinjaman default (%) untuk pengajuan baru.');
        self::ensure(self::KEY_DEFAULT_METHOD, self::DEFAULT_METHOD, 'string', 'Metode perhitungan default untuk pengajuan baru.');
        self::ensure(self::KEY_DEFAULT_ADMIN_FEE, (string) self::DEFAULT_ADMIN_FEE, 'int', 'Biaya admin default untuk pengajuan baru.');
        self::ensure(self::KEY_MINIMUM_SAVINGS_BALANCE, (string) self::DEFAULT_MINIMUM_SAVINGS_BALANCE, 'int', 'Saldo minimum simpanan yang wajib mengendap dan tidak dapat ditarik.');
        self::ensure(self::KEY_BANK_ACCOUNT_BANK, '', 'string', 'Nama bank rekening penerima transfer koperasi (refinancing mode transfer).');
        self::ensure(self::KEY_BANK_ACCOUNT_NO, '', 'string', 'Nomor rekening penerima transfer koperasi (refinancing mode transfer).');
        self::ensure(self::KEY_BANK_ACCOUNT_NAME, '', 'string', 'Nama pemilik rekening penerima transfer koperasi (refinancing mode transfer).');
    }

    public static function saveDefaultRate(float $rate, ?int $userId = null): void
    {
        self::save(self::KEY_DEFAULT_RATE, (string) $rate, 'string', $userId);
    }

    public static function saveDefaultMethod(string $method, ?int $userId = null): void
    {
        self::save(self::KEY_DEFAULT_METHOD, $method, 'string', $userId);
    }

    public static function saveDefaultAdminFee(int $fee, ?int $userId = null): void
    {
        self::save(self::KEY_DEFAULT_ADMIN_FEE, (string) $fee, 'int', $userId);
    }

    public static function saveMinimumSavingsBalance(int $balance, ?int $userId = null): void
    {
        self::save(self::KEY_MINIMUM_SAVINGS_BALANCE, (string) max(0, $balance), 'int', $userId);
    }

    public static function saveBankAccount(string $bank, string $accountNo, string $accountName, ?int $userId = null): void
    {
        self::save(self::KEY_BANK_ACCOUNT_BANK, trim($bank), 'string', $userId);
        self::save(self::KEY_BANK_ACCOUNT_NO, trim($accountNo), 'string', $userId);
        self::save(self::KEY_BANK_ACCOUNT_NAME, trim($accountName), 'string', $userId);
    }

    private static function value(string $key, string $fallback): string
    {
        try {
            $value = DB::connection('run')->table('system_settings')->where('setting_key', $key)->value('setting_value');

            return $value !== null && $value !== '' ? (string) $value : $fallback;
        } catch (Throwable) {
            return $fallback;
        }
    }

    private static function ensure(string $key, string $value, string $type, string $description): void
    {
        try {
            $exists = DB::connection('run')->table('system_settings')->where('setting_key', $key)->exists();

            if (! $exists) {
                DB::connection('run')->table('system_settings')->insert([
                    'setting_key' => $key,
                    'setting_value' => $value,
                    'setting_type' => $type,
                    'description' => $description,
                ]);
            }
        } catch (Throwable) {
            // Pengaturan tidak boleh menghalangi proses utama.
        }
    }

    private static function save(string $key, string $value, string $type, ?int $userId): void
    {
        $oldValue = null;

        try {
            $oldValue = DB::connection('run')->table('system_settings')->where('setting_key', $key)->value('setting_value');
        } catch (Throwable) {
            // continue
        }

        DB::connection('run')->table('system_settings')->updateOrInsert(
            ['setting_key' => $key],
            ['setting_value' => $value, 'setting_type' => $type, 'updated_by' => $userId, 'updated_at' => now()]
        );

        try {
            DB::connection('run')->table('sys_audit_log')->insert([
                'actor_user_id' => $userId,
                'action' => 'cu.setting_updated',
                'target_type' => 'system_settings',
                'target_id' => 0,
                'metadata_json' => json_encode([
                    'key' => $key,
                    'old_value' => $oldValue,
                    'new_value' => $value,
                ], JSON_UNESCAPED_UNICODE),
                'ip_address' => (string) request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Audit logging must not block settings updates.
        }
    }
}
