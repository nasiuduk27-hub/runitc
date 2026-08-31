<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

/**
 * Membungkus setiap test di dalam transaksi pada semua koneksi legacy,
 * lalu me-rollback saat test selesai.
 *
 * Alasan: skema legacy (sysitc_*, sys_menus, file_system, t3sTt4keR5) tidak
 * ada di database/migrations, jadi test harus menumpang database asli.
 * Rollback memastikan test tidak meninggalkan jejak.
 *
 * CATATAN PENTING:
 * Trait ini hanya aman untuk request read-only (GET). Beberapa controller
 * memanggil $pdo->beginTransaction() langsung pada PDO mentah
 * (mis. TestPlanController.php:142, FilingSystemController.php:366).
 * MySQL tidak mendukung transaksi bersarang, jadi request POST yang menyentuh
 * kode tersebut akan gagal dengan "There is already an active transaction".
 * Untuk menguji POST, gunakan test terpisah tanpa trait ini.
 */
trait UsesLegacyDatabase
{
    /**
     * Koneksi yang dipakai kode aplikasi. Lihat config/database.php.
     *
     * @var list<string>
     */
    protected array $legacyConnections = ['mysql', 'run', 'bot', 'war', 'collector'];

    /**
     * @var list<string>
     */
    private array $openTransactions = [];

    protected function beginLegacyTransactions(): void
    {
        foreach ($this->legacyConnections as $name) {
            try {
                $connection = DB::connection($name);
                $connection->getPdo();
                $connection->beginTransaction();
                $this->openTransactions[] = $name;
            } catch (Throwable $e) {
                $this->markTestSkipped("Koneksi database '{$name}' tidak tersedia: ".$e->getMessage());
            }
        }
    }

    protected function rollbackLegacyTransactions(): void
    {
        foreach (array_reverse($this->openTransactions) as $name) {
            try {
                $connection = DB::connection($name);

                while ($connection->transactionLevel() > 0) {
                    $connection->rollBack();
                }
            } catch (Throwable) {
                // Koneksi mungkin sudah tertutup; tidak ada yang bisa di-rollback.
            }
        }

        $this->openTransactions = [];
    }

    /**
     * Ambil satu user superadmin aktif dari database untuk dipakai sebagai
     * identitas test. Tidak di-hardcode supaya tidak pecah antar environment.
     *
     * Definisi superadmin mengikuti includes/menu_guard.php:114-116.
     *
     * @return array{user_id: int, account_id: string, account_nm: string}
     */
    protected function resolveSuperadmin(): array
    {
        $row = DB::connection('run')
            ->table('sysitc_usracc as ua')
            ->join('sysitc_grpacc as g', function ($join): void {
                $join->on('g.grpaccess', '=', 'ua.access_code')
                    ->on('g.grpacc', '=', 'ua.access_account');
            })
            ->join('sysitc_users as u', 'u.rec_id', '=', 'ua.user_rec_id')
            ->join('sysitc_login as l', 'l.rec_id', '=', 'u.login_rec_id')
            ->where('g.grpaccess', '03')
            ->where('g.grpacc', '999')
            ->where('g.grpdesc', 'like', '%SUPER%ADMIN%')
            ->where('u.status', 1)
            ->select('u.rec_id', 'u.account_nm', 'l.account_id')
            ->orderBy('u.rec_id')
            ->first();

        if (! $row) {
            $this->markTestSkipped('Tidak ada superadmin aktif di database.');
        }

        return [
            'user_id' => (int) $row->rec_id,
            'account_id' => (string) $row->account_id,
            'account_nm' => (string) $row->account_nm,
        ];
    }

    /**
     * Ambil satu user aktif yang BUKAN superadmin, untuk menguji bahwa
     * guard 403 di controller admin benar-benar bekerja.
     *
     * @return array{user_id: int, account_id: string, account_nm: string}
     */
    protected function resolveNonSuperadmin(): array
    {
        $superadminIds = DB::connection('run')
            ->table('sysitc_usracc as ua')
            ->join('sysitc_grpacc as g', function ($join): void {
                $join->on('g.grpaccess', '=', 'ua.access_code')
                    ->on('g.grpacc', '=', 'ua.access_account');
            })
            ->where('g.grpaccess', '03')
            ->where('g.grpacc', '999')
            ->where('g.grpdesc', 'like', '%SUPER%ADMIN%')
            ->pluck('ua.user_rec_id')
            ->all();

        $row = DB::connection('run')
            ->table('sysitc_users as u')
            ->join('sysitc_login as l', 'l.rec_id', '=', 'u.login_rec_id')
            ->where('u.status', 1)
            ->whereNotIn('u.rec_id', $superadminIds ?: [0])
            ->select('u.rec_id', 'u.account_nm', 'l.account_id')
            ->orderBy('u.rec_id')
            ->first();

        if (! $row) {
            $this->markTestSkipped('Tidak ada user aktif non-superadmin di database.');
        }

        return [
            'user_id' => (int) $row->rec_id,
            'account_id' => (string) $row->account_id,
            'account_nm' => (string) $row->account_nm,
        ];
    }

    /**
     * Bentuk payload sesi yang sama seperti yang ditulis
     * LoginController::handleUserStatus() (app/Http/Controllers/Auth/LoginController.php:295).
     *
     * @return array<string, mixed>
     */
    protected function legacySessionFor(array $user): array
    {
        return [
            'user_id' => $user['user_id'],
            'user_rec_id' => $user['user_id'],
            'account_id' => $user['account_id'],
            'user_name' => $user['account_nm'],
            'account_nm' => $user['account_nm'],
            'auth_db' => 'run',
        ];
    }

    /**
     * Pastikan test benar-benar berjalan di database yang diharapkan,
     * bukan di sqlite in-memory dari phpunit.xml.
     */
    protected function assertLegacySchemaPresent(): void
    {
        $driver = DB::connection('run')->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);

        $this->assertSame('mysql', $driver, "Koneksi 'run' harus MySQL, bukan '{$driver}'.");
    }
}
