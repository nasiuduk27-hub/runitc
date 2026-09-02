<?php

namespace App\Services\Cooperative;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pengaturan modul koperasi yang dikelola admin (superadmin / CU Admin).
 *
 * Nilai disimpan di tabel system_settings (koneksi run) agar satu sumber
 * antara halaman System Settings superadmin dan halaman pengaturan koperasi.
 */
final class CooperativeSettingsService
{
    public const KEY_DEFAULT_RATE = 'coop_default_loan_rate';

    public const KEY_DEFAULT_METHOD = 'coop_default_loan_method';

    public const KEY_DEFAULT_ADMIN_FEE = 'coop_default_admin_fee';

    public const DEFAULT_RATE = 6.0;

    public const DEFAULT_METHOD = LoanSimulationService::METHOD_FLAT;

    public const DEFAULT_ADMIN_FEE = 0;

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

    public static function ensureDefaults(): void
    {
        self::ensure(self::KEY_DEFAULT_RATE, (string) self::DEFAULT_RATE, 'string', 'Bunga pinjaman default (%) untuk pengajuan baru.');
        self::ensure(self::KEY_DEFAULT_METHOD, self::DEFAULT_METHOD, 'string', 'Metode perhitungan default untuk pengajuan baru.');
        self::ensure(self::KEY_DEFAULT_ADMIN_FEE, (string) self::DEFAULT_ADMIN_FEE, 'int', 'Biaya admin default untuk pengajuan baru.');
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
                'action' => 'cooperative.setting_updated',
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
