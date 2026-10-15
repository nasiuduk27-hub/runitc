<?php

namespace App\Support\FilingSystem;

use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

class FilingFileType
{
    public const TBL_CODE = '82';

    /**
     * Mengambil daftar tipe file aktif untuk dropdown upload & edit baru.
     * Fail-soft: jika tabel belum ada atau error database, kembalikan array kosong.
     *
     * @return array<int, array{code: string, descr: string, urutan: int}>
     */
    public static function active(?PDO $pdo = null): array
    {
        try {
            $db = self::pdo($pdo);
            $stmt = $db->prepare("
                SELECT code, descr, urutan
                FROM sys_msttable
                WHERE tbl_code = ? AND statrec = 1
                ORDER BY urutan ASC, descr ASC
            ");
            $stmt->execute([self::TBL_CODE]);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(static fn (array $row): array => [
                'code' => (string) ($row['code'] ?? ''),
                'descr' => (string) ($row['descr'] ?? ''),
                'urutan' => (int) ($row['urutan'] ?? 0),
            ], $rows);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Mengambil kamus seluruh tipe [code => descr], termasuk baris nonaktif (statrec = 0)
     * untuk keperluan resolver nama file lama.
     * Fail-soft: mengembalikan array kosong bila query gagal.
     *
     * @return array<string, string>
     */
    public static function allMap(?PDO $pdo = null): array
    {
        try {
            $db = self::pdo($pdo);
            $stmt = $db->prepare("
                SELECT code, descr
                FROM sys_msttable
                WHERE tbl_code = ?
                ORDER BY urutan ASC, descr ASC
            ");
            $stmt->execute([self::TBL_CODE]);

            $map = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $code = trim((string) ($row['code'] ?? ''));
                if ($code !== '') {
                    $map[$code] = (string) ($row['descr'] ?? $code);
                }
            }

            return $map;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Resolusi kode tipe ke nama tampilan (descr).
     * Fail-soft: mengembalikan '-' jika kode kosong, tidak ditemukan, atau DB error.
     */
    public static function resolveName(?string $code, ?PDO $pdo = null): string
    {
        $clean = trim((string) $code);
        if ($clean === '') {
            return '-';
        }

        try {
            $map = self::allMap($pdo);

            return $map[$clean] ?? $clean;
        } catch (Throwable) {
            return '-';
        }
    }

    /**
     * Validasi ketat pada jalur tulis (upload & edit).
     * TIDAK fail-soft: query harus berhasil dan mengonfirmasi bahwa baris ada dengan statrec = 1.
     */
    public static function isValidActive(?string $code, ?PDO $pdo = null): bool
    {
        $clean = strtoupper(trim((string) $code));
        if ($clean === '') {
            return false;
        }

        $db = self::pdo($pdo);
        $stmt = $db->prepare("
            SELECT 1 FROM sys_msttable
            WHERE tbl_code = ? AND code = ? AND statrec = 1
            LIMIT 1
        ");
        $stmt->execute([self::TBL_CODE, $clean]);

        return (bool) $stmt->fetchColumn();
    }

    private static function pdo(?PDO $pdo = null): PDO
    {
        return $pdo ?? DB::connection('run')->getPdo();
    }
}
