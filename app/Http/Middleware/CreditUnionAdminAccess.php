<?php

namespace App\Http\Middleware;

use App\Support\CooperativeAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CooperativeAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = (int) auth_user_id();

        abort_unless(
            CooperativeAccess::isAdmin($userId),
            403,
            'Hanya admin koperasi yang dapat mengakses halaman ini.'
        );

        return $next($request);
    }
}
