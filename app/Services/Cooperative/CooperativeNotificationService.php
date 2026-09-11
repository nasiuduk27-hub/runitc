<?php

namespace App\Services\Cooperative;

use App\Services\MailService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Notifikasi modul koperasi: bell internal (sys_notifications) + email.
 *
 * Semua operasi dibungkus try/catch agar kegagalan notifikasi tidak
 * menggagalkan transaksi bisnis (pengajuan pinjaman / penarikan).
 */
class CooperativeNotificationService
{
    public function __construct(private readonly MailService $mail)
    {
    }

    /**
     * ID semua admin koperasi (Super Admin ATAU role "%CU%ADMIN%").
     * Kriteria sama persis dengan CooperativeAccess::isAdmin.
     *
     * @return list<int>
     */
    public function adminUserIds(): array
    {
        try {
            $rows = DB::connection('run')->select(
                "SELECT DISTINCT ua.user_rec_id
                 FROM sysitc_usracc ua
                 JOIN sysitc_grpacc g
                   ON g.grpaccess = ua.access_code
                  AND g.grpacc = ua.access_account
                 WHERE (
                    (g.grpaccess = '03' AND g.grpacc = '999' AND g.grpdesc LIKE '%SUPER%ADMIN%')
                    OR UPPER(g.grpdesc) LIKE '%CU%ADMIN%'
                 )"
            );

            return array_values(array_unique(array_filter(
                array_map(fn ($row): int => (int) $row->user_rec_id, $rows),
                fn (int $id): bool => $id > 0
            )));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Kirim notifikasi (bell + email) ke semua admin koperasi.
     */
    public function notifyAdmins(
        int $senderUserId,
        string $type,
        string $title,
        string $message,
        string $targetUrl,
        ?string $refTable = null,
        ?int $refId = null
    ): void {
        $adminIds = $this->adminUserIds();

        foreach ($adminIds as $adminId) {
            if ($adminId === $senderUserId) {
                continue;
            }

            $this->dispatch($adminId, $senderUserId, $type, $title, $message, $targetUrl, $refTable, $refId);
        }
    }

    /**
     * Kirim notifikasi (bell + email) ke satu pengguna.
     */
    public function notifyUser(
        int $recipientUserId,
        int $senderUserId,
        string $type,
        string $title,
        string $message,
        string $targetUrl,
        ?string $refTable = null,
        ?int $refId = null
    ): void {
        if ($recipientUserId <= 0 || $recipientUserId === $senderUserId) {
            return;
        }

        $this->dispatch($recipientUserId, $senderUserId, $type, $title, $message, $targetUrl, $refTable, $refId);
    }

    private function dispatch(
        int $recipientUserId,
        int $senderUserId,
        string $type,
        string $title,
        string $message,
        string $targetUrl,
        ?string $refTable,
        ?int $refId
    ): void {
        $this->insertBell($recipientUserId, $senderUserId, $type, $title, $message, $targetUrl, $refTable, $refId);
        $this->sendEmail($recipientUserId, $title, $message);
    }

    private function insertBell(
        int $recipientUserId,
        int $senderUserId,
        string $type,
        string $title,
        string $message,
        string $targetUrl,
        ?string $refTable,
        ?int $refId
    ): void {
        try {
            DB::connection('run')->table('sys_notifications')->insert([
                'recipient_user_id' => $recipientUserId,
                'sender_user_id' => $senderUserId > 0 ? $senderUserId : null,
                'type' => $type,
                'title' => mb_substr($title, 0, 150),
                'message' => mb_substr($message, 0, 2000),
                'target_url' => mb_substr($targetUrl, 0, 255),
                'ref_table' => $refTable !== null ? mb_substr($refTable, 0, 80) : null,
                'ref_id' => $refId,
                'is_read' => 0,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Notifikasi tidak boleh menggagalkan pengajuan.
        }
    }

    private function sendEmail(int $recipientUserId, string $subject, string $body): void
    {
        $email = $this->emailForUser($recipientUserId);

        if ($email === '') {
            return;
        }

        try {
            $this->mail->send($email, $this->userName($recipientUserId), $subject, $body);
        } catch (Throwable) {
            // Email gagal tidak boleh menggagalkan pengajuan.
        }
    }

    /**
     * Email utama pengguna: prioritas sysitc_login.email_id,
     * fallback sysitc_usermail (asdefault=1).
     */
    private function emailForUser(int $userId): string
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

    private function userName(int $userId): string
    {
        try {
            return (string) (DB::connection('run')->table('sysitc_users')
                ->where('rec_id', $userId)
                ->value('account_nm') ?: 'User-'.$userId);
        } catch (Throwable) {
            return 'User-'.$userId;
        }
    }
}