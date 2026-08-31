<?php

namespace Tests\Smoke;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;

/**
 * Menguji harness-nya sendiri.
 *
 * Suite ini menumpang database produksi, jadi mekanisme rollback di
 * tests/Concerns/UsesLegacyDatabase.php adalah satu-satunya pengaman.
 * Kalau test di file ini gagal, JANGAN jalankan suite lainnya.
 */
#[Group('smoke')]
class HarnessSafetyTest extends SmokeTestCase
{
    public function test_all_legacy_connections_are_inside_a_transaction(): void
    {
        foreach ($this->legacyConnections as $name) {
            $this->assertGreaterThan(
                0,
                DB::connection($name)->transactionLevel(),
                "Koneksi '{$name}' tidak dibungkus transaksi. Perubahan bisa permanen."
            );
        }
    }

    public function test_writes_are_rolled_back_after_each_test(): void
    {
        $marker = 'SMOKE_TEST_MARKER_'.bin2hex(random_bytes(8));

        DB::connection('run')->table('sys_notifications')->insert([
            'recipient_user_id' => static::$superadmin['user_id'],
            'type' => 'system',
            'title' => $marker,
            'message' => 'Baris ini harus hilang setelah rollback.',
            'is_read' => 0,
            'created_at' => now(),
        ]);

        $this->assertSame(
            1,
            DB::connection('run')->table('sys_notifications')->where('title', $marker)->count(),
            'Insert di dalam transaksi tidak terlihat — harness tidak berfungsi.'
        );

        // Rollback manual, meniru apa yang dilakukan tearDown().
        $this->rollbackLegacyTransactions();

        $this->assertSame(
            0,
            DB::connection('run')->table('sys_notifications')->where('title', $marker)->count(),
            'Baris test masih ada setelah rollback. HARNESS TIDAK AMAN — '
            .'jangan jalankan suite ini terhadap database produksi.'
        );
    }

    public function test_superadmin_identity_is_resolved_from_database(): void
    {
        $this->assertGreaterThan(0, static::$superadmin['user_id']);
        $this->assertNotSame('', static::$superadmin['account_id']);
    }

    public function test_regular_user_is_not_a_superadmin(): void
    {
        $this->asRegularUser();

        $this->assertNotSame(
            static::$superadmin['user_id'],
            static::$regularUser['user_id'],
            'User pembanding untuk test 403 ternyata superadmin itu sendiri.'
        );
    }

    public function test_connections_point_to_mysql_not_sqlite(): void
    {
        foreach ($this->legacyConnections as $name) {
            $this->assertSame(
                'mysql',
                DB::connection($name)->getDriverName(),
                "Koneksi '{$name}' bukan MySQL. Skema legacy tidak akan ditemukan."
            );
        }
    }
}
