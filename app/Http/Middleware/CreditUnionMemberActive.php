<?php

namespace App\Http\Middleware;

use App\Support\CreditUnionAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memblokir akses credit union bagi anggota berstatus Non-Active (st_aktif = 6).
 * Admin credit union / Super Admin selalu lolos.
 */
class CreditUnionMemberActive
{
    public function handle(Request $request, Closure $next): Response
    {
        // Verifikasi OTP sinkron akun tetap harus bisa diakses pemilik akun target.
        if ($request->routeIs('cu.members.sync.verify', 'cu.members.sync.verify.store')) {
            return $next($request);
        }

        $userId = (int) auth_user_id();

        if (CreditUnionAccess::isAdmin($userId)) {
            return $next($request);
        }

        $member = CreditUnionAccess::memberForUser($userId);

        if ($member !== null && ! $member->isActive()) {
            abort(403, 'Keanggotaan credit union Anda non-aktif. Hubungi admin credit union.');
        }

        return $next($request);
    }
}
