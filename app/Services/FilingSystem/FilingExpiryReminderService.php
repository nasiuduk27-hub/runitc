<?php

namespace App\Services\FilingSystem;

use App\Repositories\FilingSystem\FileSystemDrive;
use App\Services\MailService;
use App\Support\Legacy\Notification;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

class FilingExpiryReminderService
{
    private PDO $db;

    private MailService $mail;

    private Notification $notification;

    public function __construct(PDO $pdoRun, ?MailService $mail = null, ?Notification $notification = null)
    {
        $this->db = $pdoRun;
        $this->mail = $mail ?? new MailService;
        $this->notification = $notification ?? new Notification($pdoRun);
    }

    private function usesNewTables(): bool
    {
        return FileSystemDrive::isEnabled();
    }

    /**
     * Nama tabel aktif: file_system (tabel baru) atau sys_filing (legacy).
     */
    private function table(): string
    {
        return $this->usesNewTables() ? 'file_system' : 'sys_filing';
    }

    private function driver(): string
    {
        try {
            return (string) $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable) {
            return 'mysql';
        }
    }

    /**
     * Mencari file aktif yang telah tiba warningdt-nya dan belum pernah diingatkan.
     * Mode legacy (sys_filing) tidak memiliki sistem pengingat, sehingga melaporkan array kosong secara anggun.
     */
    public function findReminderCandidates(int $limit = 200): array
    {
        if (! $this->usesNewTables()) {
            return [];
        }

        $table = $this->table();
        $nowExpr = $this->driver() === 'sqlite' ? 'datetime("now")' : 'NOW()';

        $stmt = $this->db->prepare("
            SELECT *
            FROM {$table}
            WHERE status = 'active'
              AND expired_at IS NOT NULL
              AND expired_processed_at IS NULL
              AND reminded_at IS NULL
              AND warningdt IS NOT NULL
              AND warningdt <= {$nowExpr}
            ORDER BY warningdt ASC, rec_id ASC
            LIMIT ?
        ");

        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mencatat audit pengingat dikirim.
     */
    public function logReminderAudit(int $filingId, string $notes): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO sys_filing_audit (
                    filing_id, user_id, action, ip_address, user_agent, notes
                ) VALUES (?, NULL, 'expiry_reminder_sent', NULL, 'cron', ?)
            ");
            $stmt->execute([$filingId, $notes]);
        } catch (Throwable) {
            // Audit log failure is non-fatal
        }
    }

    /**
     * Resolusi email pemilik mengikuti logika persis CreditUnionNotificationService::emailForUser.
     */
    public function emailForUser(int $userId): string
    {
        try {
            $user = DB::connection('run')->table('sysitc_users as u')
                ->leftJoin('sysitc_login as l', 'l.rec_id', '=', 'u.login_rec_id')
                ->leftJoin('sysitc_usermail as um', fn ($join) => $join
                    ->on('um.user_recid', '=', 'u.rec_id')
                    ->where('um.asdefault', '=', 1))
                ->where('u.rec_id', $userId)
                ->first(['l.email_id', 'um.email']);

            $email = trim((string) ($user->email_id ?? ''));
            if ($email === '') {
                $email = trim((string) ($user->email ?? ''));
            }

            return $email;
        } catch (Throwable) {
            return '';
        }
    }

    public function userName(int $userId): string
    {
        try {
            return (string) (DB::connection('run')->table('sysitc_users')
                ->where('rec_id', $userId)
                ->value('account_nm') ?: 'User-'.$userId);
        } catch (Throwable) {
            return 'User-'.$userId;
        }
    }

    /**
     * Format tanggal Indonesia: d Bulan YYYY (misal 22 Oktober 2026).
     */
    public function formatDateIndonesian(?string $dateTime): string
    {
        if (empty($dateTime)) {
            return '-';
        }

        $ts = strtotime($dateTime);
        if ($ts === false) {
            return (string) $dateTime;
        }

        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $day = date('j', $ts);
        $month = $months[(int) date('n', $ts)] ?? date('F', $ts);
        $year = date('Y', $ts);

        return "{$day} {$month} {$year}";
    }

    /**
     * Label aksi saat kedaluwarsa.
     */
    public function actionLabel(?string $action): string
    {
        return match (strtolower((string) $action)) {
            'archive' => 'diarsipkan',
            'none' => 'diubah statusnya menjadi kedaluwarsa',
            default => 'dipindahkan ke Sampah', // trash, delete, or other
        };
    }

    /**
     * Kirim seluruh pengingat kedaluwarsa berdasar batas limit.
     *
     * @return array{
     *     total_candidates: int,
     *     total_owners: int,
     *     bells_created: int,
     *     emails_sent: int,
     *     emails_skipped: int,
     *     failed: int
     * }
     */
    public function sendReminders(int $limit = 200): array
    {
        $stats = [
            'total_candidates' => 0,
            'total_owners' => 0,
            'bells_created' => 0,
            'emails_sent' => 0,
            'emails_skipped' => 0,
            'failed' => 0,
        ];

        $candidates = $this->findReminderCandidates($limit);
        $stats['total_candidates'] = count($candidates);

        if (empty($candidates)) {
            return $stats;
        }

        // Kelompokkan per pemilik (file_system.userid)
        $grouped = [];
        foreach ($candidates as $file) {
            $ownerId = (int) ($file['userid'] ?? 0);
            $grouped[$ownerId][] = $file;
        }

        $stats['total_owners'] = count($grouped);
        $targetUrl = '/filing-system';

        foreach ($grouped as $ownerId => $files) {
            if ($ownerId <= 0 || empty($files)) {
                $stats['failed']++;

                continue;
            }

            $firstFile = $files[0];
            $fileCount = count($files);

            // Buat konten teks pesan & email
            $title = 'Pengingat Kedaluwarsa Berkas Filing System';
            $fileLines = [];
            foreach ($files as $f) {
                $fName = $f['file_name'] ?? 'Berkas';
                $fExp = $this->formatDateIndonesian($f['expired_at'] ?? null);
                $fAct = $this->actionLabel($f['expired_action'] ?? null);
                $fileLines[] = "- {$fName} (kedaluwarsa: {$fExp}, aksi: {$fAct})";
            }

            $message = "Halo, terdapat {$fileCount} berkas Anda yang akan segera kedaluwarsa:\n".implode("\n", $fileLines);

            // 1. Kanal Lonceng (catatan acuan utama)
            $bellSuccess = false;
            try {
                $this->notification->create(
                    $ownerId,
                    null, // sender = cron (system)
                    'filing_expiry_reminder',
                    $title,
                    $message,
                    $targetUrl,
                    'file_system',
                    (int) ($firstFile['rec_id'] ?? 0)
                );
                $bellSuccess = true;
                $stats['bells_created']++;
            } catch (Throwable) {
                $bellSuccess = false;
                $stats['failed']++;
            }

            // Jika lonceng gagal, berkas TIDAK ditandai (tetap kandidat pada jalan berikutnya)
            if (! $bellSuccess) {
                continue;
            }

            // 2. Kanal Email (best-effort, kegagalan tidak membatalkan penandaan)
            $email = $this->emailForUser($ownerId);
            if ($email === '') {
                $stats['emails_skipped']++;
            } else {
                try {
                    $ownerName = $this->userName($ownerId);
                    $emailSubject = "[Pengingat] {$fileCount} Berkas Filing System Akan Segera Kedaluwarsa";
                    $emailBody = "Yth. {$ownerName},\n\n".$message."\n\nSilakan periksa berkas Anda di sistem bila ingin memperpanjang masa aktif.";

                    $sent = $this->mail->send($email, $ownerName, $emailSubject, $emailBody, false);
                    if ($sent) {
                        $stats['emails_sent']++;
                    } else {
                        $stats['emails_skipped']++;
                    }
                } catch (Throwable) {
                    $stats['emails_skipped']++;
                }
            }

            // 3. Tandai berkas: reminded_at = NOW() & tulis audit
            $this->markRemindedAndAudit($files);
        }

        return $stats;
    }

    /**
     * Tandai berkas dalam kelompok yang berhasil dibuatkan lonceng.
     */
    private function markRemindedAndAudit(array $files): void
    {
        $table = $this->table();
        $nowExpr = $this->driver() === 'sqlite' ? 'datetime("now")' : 'NOW()';

        $stmt = $this->db->prepare("
            UPDATE {$table}
            SET reminded_at = {$nowExpr}
            WHERE rec_id = ?
        ");

        foreach ($files as $file) {
            $fid = (int) $file['rec_id'];
            $stmt->execute([$fid]);
            $this->logReminderAudit($fid, 'Pengingat kedaluwarsa berhasil dikirim via lonceng/email.');
        }
    }
}
