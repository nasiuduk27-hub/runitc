<?php

namespace App\Http\Middleware;

use App\Support\CreditUnionAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CreditUnionAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = (int) auth_user_id();

        abort_unless(
            CreditUnionAccess::isAdmin($userId),
            403,
            'Hanya admin credit union yang dapat mengakses halaman ini.'
        );

        return $next($request);
    }
}
