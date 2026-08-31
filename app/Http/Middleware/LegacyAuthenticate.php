<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LegacyAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('user_id')) {
            // Login lama (index.php/dashboard.php) menulis ke sesi native PHP
            // (cookie PHPSESSID). Sinkronkan ke sesi Laravel supaya route modern
            // seperti /admin, /cbt-ops, dan /modules/... ikut mengenali login.
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            foreach (['user_id', 'user_rec_id', 'account_id', 'user_name', 'account_nm', 'auth_db'] as $key) {
                if (isset($_SESSION[$key]) && $_SESSION[$key] !== '') {
                    $request->session()->put($key, $_SESSION[$key]);
                }
            }
        }

        if (! $request->session()->has('user_id')) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
