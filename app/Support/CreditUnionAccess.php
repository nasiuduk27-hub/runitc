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
}
