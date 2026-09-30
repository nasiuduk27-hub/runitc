<?php

namespace App\Support;

use App\Models\CreditUnion\CreditUnionMember;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Helper otorisasi modul credit union.
 *
 * Admin credit union = Super Admin ATAU role yang mengandung "CU Admin"
 * (mis. Group CU Admin 03/410).
 */
final class CreditUnionAccess
{
    /**
     * Apakah user benar-benar memiliki role admin credit union (tanpa
     * memperhitungkan mode tampilan). Dipakai untuk memutuskan ketersediaan
     * tombol ganti mode dan agar admin selalu lolos middleware status anggota.
     */
    public static function hasAdminRole(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        try {
            return (int) DB::connection('run')->selectOne(
                "SELECT COUNT(*) AS aggregate
                 FROM sysitc_usracc ua
                 JOIN sysitc_grpacc g
                   ON g.grpaccess = ua.access_code
                  AND g.grpacc = ua.access_account
                 WHERE ua.user_rec_id = ?
                   AND (
                        (g.grpaccess = '03' AND g.grpacc = '999' AND g.grpdesc LIKE '%SUPER%ADMIN%')
                        OR UPPER(g.grpdesc) LIKE '%CU%ADMIN%'
                   )",
                [$userId]
            )->aggregate > 0;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Admin yang sedang beralih ke tampilan anggota. Hanya menurunkan hak
     * akses (tidak pernah menaikkan), jadi aman meski berasal dari session.
     */
    public static function isActingAsMember(int $userId): bool
    {
        return self::resolveActingAsMember(
            session('cu_view_mode'),
            self::hasAdminRole($userId),
            self::memberForUser($userId) !== null,
        );
    }

    /**
     * Logika murni penentu mode agar dapat diuji tanpa database/session.
     */
    public static function resolveActingAsMember(?string $mode, bool $hasAdminRole, bool $hasMember): bool
    {
        return $mode === 'member' && $hasAdminRole && $hasMember;
    }

    /**
     * Admin efektif: memiliki role admin DAN tidak sedang dalam mode anggota.
     * Semua percabangan tampilan/baca memakai ini, sehingga admin yang juga
     * anggota otomatis melihat versi anggota saat mode aktif.
     */
    public static function isAdmin(int $userId): bool
    {
        return self::hasAdminRole($userId) && ! self::isActingAsMember($userId);
    }

    public static function memberForUser(int $userId): ?CreditUnionMember
    {
        if ($userId <= 0) {
            return null;
        }

        return CreditUnionMember::query()->where('itc_user_id', $userId)->first();
    }

    /**
     * Memeriksa apakah suatu transaksi/pengajuan terkait langsung dengan user
     * (baik sebagai pembuat/maker maupun sebagai pemilik rekening anggota).
     */
    public static function isOwnerOrMaker(int $userId, ?int $makerUserId, ?int $memberRecId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if ($makerUserId !== null && $makerUserId === $userId) {
            return true;
        }

        if ($memberRecId !== null && $memberRecId > 0) {
            $member = self::memberForUser($userId);
            if ($member !== null && (int) $member->rec_id === (int) $memberRecId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Memeriksa apakah user adalah admin CU yang berhak menyetujui (maker-checker):
     * Wajib admin dan BUKAN pembuat / bukan anggota pemilik pengajuan.
     */
    public static function canApproveAsAdmin(int $userId, ?int $makerUserId, ?int $memberRecId): bool
    {
        return self::isAdmin($userId) && ! self::isOwnerOrMaker($userId, $makerUserId, $memberRecId);
    }
}
