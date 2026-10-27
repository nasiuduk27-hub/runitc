<?php

namespace App\Services\CreditUnion;

use App\Models\CreditUnion\CreditUnionLoanApplication;
use App\Services\MailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     * Kirim notifikasi pengajuan baru: lonceng individual ke admin CU
     * + SATU email kolektif (To: admin terdaftar, CC: hod_email, Reply-To: admin terdaftar).
     */
    public function notifyLoanApplicationSubmitted(
        CreditUnionLoanApplication $application,
        int $senderUserId,
        string $targetUrl,
        ?string $bankName = null
    ): void {
        $adminIds = $this->adminUserIds();
        $title = 'Pengajuan Pinjaman Baru';
        $message = 'Anggota '.$application->member_name.' ('.$application->member_icuno.') mengajukan pinjaman Rp '.number_format((int) $application->principal_amount, 0, ',', '.').' selama '.$application->tenor_months.' bulan.';
        $refTable = 'cu_loan_application';
        $refId = (int) $application->id;
        $type = 'cu.loan_application.submitted';

        // 1. Lonceng internal per admin (kecuali pengaju sendiri)
        foreach ($adminIds as $adminId) {
            if ($adminId === $senderUserId) {
                continue;
            }

            $this->insertBell($adminId, $senderUserId, $type, $title, $message, $targetUrl, $refTable, $refId);
        }

        // 2. Email kolektif ke admin terdaftar, CC HOD, Reply-To admin terdaftar
        try {
            $adminEmails = [];
            foreach ($adminIds as $adminId) {
                $email = $this->emailForUser($adminId);
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $adminEmails[] = $email;
                }
            }
            $adminEmails = array_values(array_unique($adminEmails));

            $cc = [];
            $hodEmail = trim((string) $application->hod_email);
            if ($hodEmail !== '' && filter_var($hodEmail, FILTER_VALIDATE_EMAIL)) {
                $cc[] = $hodEmail;
            }

            $netDisbursement = $application->admin_fee_type === 'include'
                ? max(0, (int) $application->principal_amount - (int) $application->admin_fee)
                : (int) $application->principal_amount;

            $accNo = (string) $application->bank_accno;
            $last4 = substr($accNo, -4);
            $maskedAcc = $last4 !== '' ? '••••'.$last4 : '-';
            $resolvedBankName = $bankName ?: ($application->bank_bnkcd ?: '-');
            $bankInfo = trim("{$resolvedBankName} — {$application->bank_accnm} ({$maskedAcc})");

            $subject = "Pengajuan Pinjaman Credit Union: {$application->member_name} ({$application->member_icuno})";

            $body = "Pemberitahuan Pengajuan Pinjaman Credit Union\n\n"
                ."Berikut ringkasan pengajuan pinjaman anggota:\n"
                ."• Nama Anggota: {$application->member_name}\n"
                ."• No. Anggota: {$application->member_icuno}\n"
                ."• Jumlah Pinjaman: Rp ".number_format((int) $application->principal_amount, 0, ',', '.')."\n"
                ."• Tenor: {$application->tenor_months} bulan\n"
                ."• Bunga per Tahun: ".number_format((float) $application->annual_rate_percent, 2, ',', '.')."%\n"
                ."• Keperluan: {$application->descr}\n"
                ."• Biaya Admin: Rp ".number_format((int) $application->admin_fee, 0, ',', '.')." ({$application->admin_fee_type})\n"
                ."• Estimasi Dana Diterima: Rp ".number_format($netDisbursement, 0, ',', '.')."\n"
                .($application->fund_release_method === 'transfer' && $accNo !== '' ? "• Rekening Pencairan: {$bankInfo}\n" : '')
                ."\n"
                ."PETUNJUK KEPALA DIVISI (HOD):\n"
                ."Mohon balas email ini (Balas / Balas Semua) untuk menyatakan persetujuan atau penolakan atas pengajuan pinjaman di atas.\n"
                ."Balasan email Anda akan diterima langsung oleh tim admin Credit Union sebagai dasar verifikasi pengajuan.\n\n"
                ."Tautan pengajuan di sistem:\n{$targetUrl}\n";

            if (! empty($adminEmails)) {
                $sent = $this->mail->send(
                    $adminEmails,
                    'Admin Credit Union',
                    $subject,
                    $body,
                    false,
                    [],
                    $cc,
                    $adminEmails
                );

                if (! $sent) {
                    Log::warning('Pengiriman email pengajuan pinjaman ke admin/HOD mengembalikan status gagal.', [
                        'application_id' => $application->id,
                        'hod_email' => $application->hod_email,
                    ]);
                }
            } else {
                Log::warning('Tidak ada alamat email admin CU yang ter-resolusi untuk pengajuan pinjaman.', [
                    'application_id' => $application->id,
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Exception saat mengirim email pengajuan pinjaman ke admin/HOD: '.$e->getMessage(), [
                'application_id' => $application->id,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Kirim notifikasi (bell + email) ke satu pengguna.
     *
     * @param array<int, string|array{path: string, name?: string, as?: string, mime?: string}> $attachments
     */
    public function notifyUser(
        int $recipientUserId,
        int $senderUserId,
        string $type,
        string $title,
        string $message,
        string $targetUrl,
        ?string $refTable = null,
        ?int $refId = null,
        array $attachments = []
    ): void {
        if ($recipientUserId <= 0 || $recipientUserId === $senderUserId) {
            return;
        }

        $this->dispatch($recipientUserId, $senderUserId, $type, $title, $message, $targetUrl, $refTable, $refId, $attachments);
    }

    /**
     * Kirim notifikasi ke anggota tertaut dari member_rec_id (fallback ke userId).
     *
     * @param array<int, string|array{path: string, name?: string, as?: string, mime?: string}> $attachments
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
        ?int $refId = null,
        array $attachments = []
    ): void {
        $recipientId = $this->userIdForMember($memberRecId) ?: $fallbackUserId;

        $this->notifyUser($recipientId, $senderUserId, $type, $title, $message, $targetUrl, $refTable, $refId, $attachments);
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
        ?int $refId,
        array $attachments = []
    ): void {
        $this->insertBell($recipientUserId, $senderUserId, $type, $title, $message, $targetUrl, $refTable, $refId);
        $this->sendEmail($recipientUserId, $title, $message, $attachments);
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

    /**
     * @param array<int, string|array{path: string, name?: string, as?: string, mime?: string}> $attachments
     */
    private function sendEmail(int $recipientUserId, string $subject, string $body, array $attachments = []): void
    {
        $email = $this->emailForUser($recipientUserId);

        if ($email === '') {
            return;
        }

        try {
            $sent = $this->mail->send($email, $this->userName($recipientUserId), $subject, $body, false, $attachments);
            if (! $sent && ! empty($attachments)) {
                \Illuminate\Support\Facades\Log::warning('Pengiriman email pencairan dengan lampiran bukti transfer mengembalikan status gagal.', [
                    'recipient_user_id' => $recipientUserId,
                    'attachments_count' => count($attachments),
                ]);
            }
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Exception saat mengirim email credit union: '.$e->getMessage(), [
                'recipient_user_id' => $recipientUserId,
                'has_attachments' => ! empty($attachments),
                'exception' => $e,
            ]);
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
