<?php

namespace App\Repositories\FilingSystem;

use PDO;
use RuntimeException;

class FileSystemDrive
{
    private PDO $db;

    public function __construct(PDO $pdoRun)
    {
        $this->db = $pdoRun;
    }

    public static function isEnabled(): bool
    {
        return filter_var(getenv('FILING_USE_NEW_TABLES') ?: false, FILTER_VALIDATE_BOOLEAN);
    }

    // ==========================================
    // Folder master (sys_msttable)
    // ==========================================

    public function getActiveFolder(): ?array
    {
        $stmt = $this->db->prepare("
            SELECT rec_id, code, notes, AddiNotes, statrec
            FROM sys_msttable
            WHERE tbl_code = '81'
              AND statrec = 1
            ORDER BY urutan ASC, rec_id ASC
            LIMIT 1
        ");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getFolderByLocation(string $folderLoc): ?array
    {
        if ($folderLoc === '') {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT rec_id, code, notes, AddiNotes, statrec
            FROM sys_msttable
            WHERE tbl_code = '81'
              AND AddiNotes = ?
            LIMIT 1
        ");
        $stmt->execute([$folderLoc]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    // ==========================================
    // Nomor file (sysitc_serialno, pola GetSernoWeb)
    // ==========================================

    /**
     * Membentuk nomor sesuai pola function FoxPro GetSernoWeb:
     * pre_serno + sparerator + mid_serno + sparerator + str_pad(last_number, length_last_no, '0', LEFT)
     *
     * Contoh: FSY + - + 25L + - + 00017 => FSY-25L-00017
     */
    public function formatSerialNumber(array $serial): string
    {
        $pre = (string) ($serial['pre_serno'] ?? '');
        $mid = (string) ($serial['mid_serno'] ?? '');
        $sep = (string) ($serial['sparerator'] ?? '');
        $last = (int) ($serial['last_number'] ?? 0);
        $len = max(1, (int) ($serial['length_last_no'] ?? 0));

        $num = str_pad((string) $last, $len, '0', STR_PAD_LEFT);

        return $pre.$sep.$mid.$sep.$num;
    }

    /**
     * Mengambil serial untuk Filing System (key_code='80').
     */
    public function getSerialRow(): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM sysitc_serialno
            WHERE key_code = '80' AND owner = 'FSY'
            LIMIT 1
        ");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Generate nomor file berikutnya dengan row lock + increment di dalam transaction.
     *
     * @return array{trxno: string, serial: array, folder: array|null}
     */
    public function acquireNextTrxNo(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM sysitc_serialno WHERE key_code = ? AND owner = ? FOR UPDATE');
        $stmt->execute(['80', 'FSY']);
        $serial = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $serial) {
            throw new RuntimeException('Konfigurasi serial Filing System (FSY/80) tidak ditemukan.');
        }

        $serial['last_number'] = ((int) ($serial['last_number'] ?? 0)) + 1;

        // Pengaman: pastikan nomor yang dihasilkan belum pernah dipakai di file_system.
        // Jika sudah ada (data lama/restore serial), naikkan sampai bebas bentrok.
        $checkStmt = $this->db->prepare('SELECT COUNT(*) FROM file_system WHERE trxno = ?');
        while (true) {
            $candidateNo = $serial['last_number'];
            $candidateSerial = $serial;
            $candidateSerial['last_number'] = $candidateNo;
            $candidateTrxno = $this->formatSerialNumber($candidateSerial);

            $checkStmt->execute([$candidateTrxno]);
            if ((int) $checkStmt->fetchColumn() === 0) {
                $serial['last_number'] = $candidateNo;
                break;
            }
            $serial['last_number'] = $candidateNo + 1;
        }

        $upd = $this->db->prepare('
            UPDATE sysitc_serialno
            SET last_number = ?, reclock_byID = ?, lupdt = NOW()
            WHERE key_code = ? AND owner = ?
        ');
        $upd->execute([
            $serial['last_number'],
            $userId,
            '80',
            'FSY',
        ]);

        $trxno = $this->formatSerialNumber($serial);

        return [
            'trxno' => $trxno,
            'serial' => $serial,
            'folder' => $this->getActiveFolder(),
        ];
    }

    // ==========================================
    // file_system
    // ==========================================

    public function getFileById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM file_system WHERE rec_id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getFilesByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("SELECT * FROM file_system WHERE rec_id IN ($placeholders)");
        $stmt->execute(array_values($ids));

        $dict = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dict[$row['rec_id']] = $row;
        }

        return $dict;
    }

    public function insertFile(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO file_system (
                trxno, trxdt, remind_exp, expiredt, warningdt,
                file_name, folder_loc, upload_flnm, file_type,
                client_nm, file_notes, file_size, file_zip_size,
                userid, depcd, userid_upd,
                status, security_level, access_mode,
                expired_at, expired_action, expired_processed_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $data['trxno'],
            $data['trxdt'] ?? date('Y-m-d'),
            ! empty($data['remind_exp']) ? 1 : 0,
            $this->normalizeDate($data['expiredt'] ?? null),
            $this->normalizeDate($data['warningdt'] ?? null),
            $data['file_name'],
            $data['folder_loc'],
            $data['upload_flnm'],
            $data['file_type'],
            $data['client_nm'] ?? '',
            $data['file_notes'] ?? '',
            (int) ($data['file_size'] ?? 0),
            (int) ($data['file_zip_size'] ?? 0),
            (int) $data['userid'],
            $data['depcd'] ?? '',
            (int) $data['userid'],
            $data['status'] ?? 'active',
            $data['security_level'] ?? 'normal',
            $data['access_mode'] ?? 'private',
            $data['expired_at'] ?? null,
            $data['expired_action'] ?? 'trash',
            $data['expired_processed_at'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Normalisasi kolom DATE agar tidak pernah mengirim '0000-00-00'
     * (ditolak MySQL strict mode). NULL atau tanggal valid saja.
     */
    private function normalizeDate($value): ?string
    {
        if ($value === null || $value === '' || $value === '0000-00-00') {
            return null;
        }

        $value = trim((string) $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d', $ts);
    }

    public function updateMetadata(int $id, array $data): bool
    {
        $map = [
            'file_name' => 'file_name',
            'file_type' => 'file_type',
            'file_notes' => 'file_notes',
            'security_level' => 'security_level',
            'access_mode' => 'access_mode',
            'client_nm' => 'client_nm',
            'expired_at' => 'expired_at',
            'expired_action' => 'expired_action',
            'expired_processed_at' => 'expired_processed_at',
            'status' => 'status',
            'trashed_at' => 'trashed_at',
            'deleted_at' => 'deleted_at',
        ];

        $fields = [];
        $params = [];

        foreach ($map as $input => $column) {
            if (array_key_exists($input, $data)) {
                $fields[] = "$column = ?";
                $params[] = $data[$input];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = 'UPDATE file_system SET '.implode(', ', $fields).', userid_upd = COALESCE(userid_upd, 0), lupdt = NOW() WHERE rec_id = ?';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    // ==========================================
    // file_shareto
    // ==========================================

    public function getSharesByFileId(int $filesysId): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM file_shareto
            WHERE filesys_id = ?
            ORDER BY share_cat ASC, othercode ASC
        ');
        $stmt->execute([$filesysId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function replaceShares(int $filesysId, array $shares, int $userId): void
    {
        $stmtDel = $this->db->prepare('DELETE FROM file_shareto WHERE filesys_id = ?');
        $stmtDel->execute([$filesysId]);

        if (empty($shares)) {
            return;
        }

        $stmtIns = $this->db->prepare('
            INSERT INTO file_shareto (filesys_id, share_cat, othercode, created_by)
            VALUES (?, ?, ?, ?)
        ');

        foreach ($shares as $share) {
            $stmtIns->execute([
                $filesysId,
                (int) ($share['share_cat'] ?? 0),
                (string) ($share['othercode'] ?? '0'),
                $userId,
            ]);
        }
    }

    /**
     * Ringkasan target divisi (share_cat=2) dan company (share_cat=3)
     * untuk banyak file sekaligus (dipakai badge list, hindari N+1).
     *
     * @return array<int, array{divisi: string[], company: string[]}>
     */
    public function getShareBadgesByFileIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("
            SELECT filesys_id, share_cat, othercode
            FROM file_shareto
            WHERE filesys_id IN ($placeholders)
              AND share_cat IN (2, 3)
            ORDER BY filesys_id ASC, share_cat ASC, othercode ASC
        ");
        $stmt->execute(array_values($ids));

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $fid = (int) $row['filesys_id'];
            $cat = (int) $row['share_cat'];
            $key = $cat === 2 ? 'divisi' : 'company';
            $map[$fid][$key][] = (string) $row['othercode'];
        }

        return $map;
    }

    // ==========================================
    // Helper pemetaan ke bentuk response lama (views tetap jalan)
    // ==========================================

    public function mapToLegacyShape(array $file): array
    {
        return [
            'rec_id' => (int) $file['rec_id'],
            'file_code' => $file['trxno'],
            'display_name' => $file['file_name'],
            'original_name' => $file['file_name'],
            'keywords' => $file['file_notes'],
            'notes' => $file['file_notes'],
            'declared_file_type' => $this->mapExtToType($file['file_type'] ?? ''),
            'detected_file_type' => $this->mapExtToType($file['file_type'] ?? ''),
            'file_type' => $file['file_type'],
            'security_level' => $file['security_level'] ?? 'normal',
            'access_mode' => $file['access_mode'] ?? 'private',
            'status' => $file['status'] ?? 'active',
            'uploaded_by' => (int) ($file['userid'] ?? 0),
            'zip_size' => (int) ($file['file_zip_size'] ?? 0),
            'total_uncompressed_size' => (int) ($file['file_size'] ?? 0),
            'file_count' => $file['file_type'] === 'ZIP' ? 1 : 1,
            'storage_path' => $this->buildStoragePath($file),
            'folder_loc' => $file['folder_loc'],
            'upload_flnm' => $file['upload_flnm'],
            'expired_at' => $file['expired_at'],
            'expired_action' => $file['expired_action'] ?? 'trash',
            'expired_processed_at' => $file['expired_processed_at'],
            'trashed_at' => $file['trashed_at'],
            'deleted_at' => $file['deleted_at'],
            'created_at' => $file['create_dt'],
            'updated_at' => $file['lupdt'],
        ];
    }

    public function buildStoragePath(array $file): string
    {
        $folder = $this->getFolderByLocation((string) ($file['folder_loc'] ?? ''));

        return ($folder['notes'] ?? '').($file['upload_flnm'] ?? '');
    }

    public function getOwnerInfo(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT rec_id, account_nm, alias_nm FROM sysitc_users WHERE rec_id = ?');
        $stmt->execute([$userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' bytes';
    }

    public function mapExtToType(string $ext): string
    {
        $map = [
            'pdf' => 'document',
            'doc' => 'document',
            'docx' => 'document',
            'odt' => 'document',
            'xls' => 'spreadsheet',
            'xlsx' => 'spreadsheet',
            'csv' => 'spreadsheet',
            'ods' => 'spreadsheet',
            'txt' => 'text',
            'jpg' => 'image',
            'jpeg' => 'image',
            'png' => 'image',
            'gif' => 'image',
            'webp' => 'image',
            'ppt' => 'presentation',
            'pptx' => 'presentation',
            'odp' => 'presentation',
            'zip' => 'archive',
            'rar' => 'archive',
            '7z' => 'archive',
        ];

        return $map[strtolower($ext)] ?? 'other';
    }
}
