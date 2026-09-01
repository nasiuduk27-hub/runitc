<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LegacyAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $authenticated = $request->session()->has('user_id') || Auth::guard('legacy')->check();

        if (! $authenticated) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
