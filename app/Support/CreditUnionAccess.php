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
    public static function isAdmin(int $userId): bool
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
