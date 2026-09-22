<?php

namespace App\Services\CreditUnion;

use App\Services\MailService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Notifikasi modul credit union: bell internal (sys_notifications) + email.
 *
 * Semua operasi dibungkus try/catch agar kegagalan notifikasi tidak
 * menggagalkan transaksi bisnis (pengajuan pinjaman / penarikan).
 */
class CreditUnionNotificationService
{
    public function __construct(private readonly MailService $mail) {}

    /**
     * ID semua admin credit union (Super Admin ATAU role "%CU%ADMIN%").
     * Kriteria sama persis dengan CreditUnionAccess::isAdmin.
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
     * Kirim notifikasi (bell + email) ke semua admin credit union.
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

    /**
     * Kirim notifikasi ke anggota tertaut dari member_rec_id (fallback ke userId).
     */
    public function notifyMember(
        int $memberRecId,
        int $fallbackUserId,
        int $senderUserId,
        string $type,
        string $title,
        string $message,
        string $targetUrl,
        ?string $refTable = null,
        ?int $refId = null
    ): void {
        $recipientId = $this->userIdForMember($memberRecId) ?: $fallbackUserId;

        $this->notifyUser($recipientId, $senderUserId, $type, $title, $message, $targetUrl, $refTable, $refId);
    }

    /**
     * Kirim notifikasi keputusan (setujui/tolak) ke anggota tertaut + admin lain.
     *
     * Penerima anggota ditentukan dari member_rec_id (icu_member.itc_user_id);
     * bila tidak tertaut, jatuh ke pengaju (fallback). Pengirim (checker) dikecualikan.
     */
    public function notifyDecision(
        int $memberRecId,
        int $fallbackUserId,
        int $actorUserId,
        string $type,
        string $title,
        string $message,
        string $targetUrl,
        ?string $refTable = null,
        ?int $refId = null
    ): void {
        $recipients = self::decisionRecipients(
            $this->adminUserIds(),
            $this->userIdForMember($memberRecId),
            $fallbackUserId,
            $actorUserId
        );

        foreach ($recipients as $recipientId) {
            $this->dispatch($recipientId, $actorUserId, $type, $title, $message, $targetUrl, $refTable, $refId);
        }
    }

    /**
     * Susun daftar unik penerima notifikasi keputusan: admin + anggota tertaut
     * (fallback ke pengaju), tanpa id kosong dan tanpa pengirim.
     *
     * @param  list<int>  $adminIds
     * @return list<int>
     */
    public static function decisionRecipients(array $adminIds, int $memberUserId, int $fallbackUserId, int $actorUserId): array
    {
        $memberUserId = $memberUserId > 0 ? $memberUserId : $fallbackUserId;
        $adminIds[] = $memberUserId;

        return array_values(array_unique(array_filter(
            array_map('intval', $adminIds),
            fn (int $id): bool => $id > 0 && $id !== $actorUserId
        )));
    }

    /**
     * User RUNITC yang tertaut ke record anggota (icu_member.itc_user_id).
     */
    private function userIdForMember(int $memberRecId): int
    {
        if ($memberRecId <= 0) {
            return 0;
        }

        try {
            return (int) (DB::connection('mysql')->table('icu_member')
                ->where('rec_id', $memberRecId)
                ->value('itc_user_id') ?? 0);
        } catch (Throwable) {
            return 0;
        }
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
