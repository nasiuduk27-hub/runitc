<?php

namespace App\Services\FilingSystem;

use DateTimeInterface;
use Exception;
use PDO;

class FilingFolderAllocationService
{
    private const LOCK_NAME = 'filing_folder_allocation_lock';

    private const LOCK_TIMEOUT_SECONDS = 15;

    private static array $monthNames = [
        1 => 'JANUARI',
        2 => 'FEBRUARI',
        3 => 'MARET',
        4 => 'APRIL',
        5 => 'MEI',
        6 => 'JUNI',
        7 => 'JULI',
        8 => 'AGUSTUS',
        9 => 'SEPTEMBER',
        10 => 'OKTOBER',
        11 => 'NOVEMBER',
        12 => 'DESEMBER',
    ];

    public function __construct(
        private PDO $db,
        private ?FilingStorageService $storage = null
    ) {}

    /**
     * Resolusi divisi penyimpanan pengunggah:
     * Divisi dari assignment grpaccess='03' terawal menurut sysitc_usracc.rec_id yang div_cd-nya terisi.
     * Group ber-div_cd NULL dilewati. Bila tidak ada kandidat: 'UMUM'.
     */
    public function resolveUserDivision(int $userId): string
    {
        if ($userId <= 0) {
            return 'UMUM';
        }

        try {
            $stmt = $this->db->prepare("
                SELECT g.div_cd
                FROM sysitc_usracc ua
                JOIN sysitc_grpacc g ON g.grpaccess = ua.access_code AND g.grpacc = ua.access_account
                WHERE ua.user_rec_id = ?
                  AND g.grpaccess = '03'
                  AND g.div_cd IS NOT NULL
                  AND g.div_cd <> ''
                ORDER BY ua.rec_id ASC
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $divCd = $stmt->fetchColumn();

            if ($divCd !== false && trim((string) $divCd) !== '') {
                return strtoupper(trim((string) $divCd));
            }
        } catch (\Throwable $e) {
            // Jika kolom div_cd belum siap di database, fallback ke UMUM
        }

        return 'UMUM';
    }

    /**
     * Nama folder datar: <kode huruf kecil><YY><huruf bulan a-l>
     * Contoh: HRD bulan Oktober 2026 => hrd26j, UMUM => umum26j
     */
    public function computeFolderName(string $divCode, ?DateTimeInterface $date = null): string
    {
        $date = $date ?? new \DateTimeImmutable();
        $divClean = strtolower(trim($divCode)) ?: 'umum';
        $yy = $date->format('y');
        $monthNum = (int) $date->format('n');
        $monthLetter = chr(ord('a') + max(1, min(12, $monthNum)) - 1);

        return $divClean.$yy.$monthLetter;
    }

    /**
     * Kelanjutan deret dua huruf sesudah nilai maksimum gabungan
     * sys_msttable.AddiNotes (tbl_code='81') dan file_system.folder_loc.
     * Nilai yang pernah dirujuk TIDAK boleh dipakai ulang.
     */
    public function calculateNextAddiNotes(): string
    {
        $usedCodes = [];

        try {
            $stmtMst = $this->db->query("SELECT DISTINCT AddiNotes FROM sys_msttable WHERE tbl_code = '81' AND AddiNotes IS NOT NULL AND AddiNotes <> ''");
            if ($stmtMst) {
                while ($row = $stmtMst->fetch(PDO::FETCH_ASSOC)) {
                    $val = strtoupper(trim((string) ($row['AddiNotes'] ?? '')));
                    if ($val !== '') {
                        $usedCodes[$val] = true;
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        try {
            $stmtFs = $this->db->query("SELECT DISTINCT folder_loc FROM file_system WHERE folder_loc IS NOT NULL AND folder_loc <> ''");
            if ($stmtFs) {
                while ($row = $stmtFs->fetch(PDO::FETCH_ASSOC)) {
                    $val = strtoupper(trim((string) ($row['folder_loc'] ?? '')));
                    if ($val !== '') {
                        $usedCodes[$val] = true;
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        if (empty($usedCodes)) {
            return 'AA';
        }

        $keys = array_keys($usedCodes);
        sort($keys, SORT_STRING);
        $maxCode = end($keys);

        $nextCode = ++$maxCode;
        while (isset($usedCodes[$nextCode])) {
            $nextCode = ++$nextCode;
        }

        return $nextCode;
    }

    /**
     * Dapatkan folder aktif bulan ini untuk divisi tertentu atau buat malas jika belum ada.
     * Mekanisme kunci GET_LOCK menjamin keamanan balapan (race-safe) pada MyISAM.
     *
     * @return array{rec_id: int, tbl_code: string, code: string, urutan: int, descr: string, notes: string, AddiNotes: string, statrec: int}
     */
    public function getOrCreateMonthlyFolder(string $divCode, ?DateTimeInterface $date = null): array
    {
        $date = $date ?? new \DateTimeImmutable();
        $divCode = strtoupper(trim($divCode)) ?: 'UMUM';
        $folderName = $this->computeFolderName($divCode, $date);
        $folderNotes = '/filesys/'.$folderName.'/';

        $driverName = '';
        try {
            $driverName = (string) $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (\Throwable $e) {
        }

        $isMysql = strtolower($driverName) === 'mysql';
        $lockAcquired = false;

        if ($isMysql) {
            $stmt = $this->db->prepare('SELECT GET_LOCK(?, ?)');
            $stmt->execute([self::LOCK_NAME, self::LOCK_TIMEOUT_SECONDS]);
            $res = $stmt->fetchColumn();
            if ((int) $res !== 1) {
                throw new Exception('Gagal mendapatkan kunci alokasi folder filing (timeout). Silakan coba kembali.');
            }
            $lockAcquired = true;
        }

        try {
            // 1. Cek dulu apakah baris folder sudah ada
            $stmtCheck = $this->db->prepare("SELECT * FROM sys_msttable WHERE tbl_code = '81' AND notes = ? LIMIT 1");
            $stmtCheck->execute([$folderNotes]);
            $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                return $existing;
            }

            // 2. Hitung urutan dan AddiNotes berikutnya
            $stmtUrutan = $this->db->prepare("SELECT COALESCE(MAX(urutan), 0) + 1 FROM sys_msttable WHERE tbl_code = '81'");
            $stmtUrutan->execute();
            $nextUrutan = (int) $stmtUrutan->fetchColumn();

            $nextAddiNotes = $this->calculateNextAddiNotes();
            $descr = $this->formatFolderDescription($divCode, $date);

            // 3. Buat direktori pada storage
            if ($this->storage) {
                $this->storage->ensureStorageDirectory($folderNotes);
            }

            // 4. Sisipkan baris 81 baru
            $now = date('Y-m-d H:i:s');
            $stmtInsert = $this->db->prepare("
                INSERT INTO sys_msttable (tbl_code, code, urutan, descr, notes, AddiNotes, statrec, lupdt)
                VALUES ('81', ?, ?, ?, ?, ?, 1, ?)
            ");
            $stmtInsert->execute([
                $divCode,
                $nextUrutan,
                $descr,
                $folderNotes,
                $nextAddiNotes,
                $now,
            ]);

            $recId = (int) $this->db->lastInsertId();

            return [
                'rec_id' => $recId,
                'tbl_code' => '81',
                'code' => $divCode,
                'urutan' => $nextUrutan,
                'descr' => $descr,
                'notes' => $folderNotes,
                'AddiNotes' => $nextAddiNotes,
                'statrec' => 1,
            ];
        } finally {
            if ($isMysql && $lockAcquired) {
                try {
                    $stmtRel = $this->db->prepare('SELECT RELEASE_LOCK(?)');
                    $stmtRel->execute([self::LOCK_NAME]);
                } catch (\Throwable $e) {
                }
            }
        }
    }

    private function formatFolderDescription(string $divCode, DateTimeInterface $date): string
    {
        $monthNum = (int) $date->format('n');
        $monthName = self::$monthNames[$monthNum] ?? $date->format('F');
        $year = $date->format('Y');

        $divName = '';
        if ($divCode !== 'UMUM') {
            try {
                $stmt = $this->db->prepare("SELECT descr FROM sys_msttable WHERE tbl_code = '55' AND code = ? AND statrec = 1 LIMIT 1");
                $stmt->execute([$divCode]);
                $divName = (string) $stmt->fetchColumn();
            } catch (\Throwable $e) {
            }
        }

        $divName = trim($divName);
        $base = $divName !== '' ? $divName : "DIVISI {$divCode}";

        return mb_substr("{$base} {$monthName} {$year}", 0, 50);
    }
}
