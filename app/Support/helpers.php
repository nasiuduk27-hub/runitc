<?php

use Illuminate\Support\Facades\Auth;

if (! function_exists('auth_user_id')) {
    /**
     * ID user aktif: prefer Auth guard 'legacy', fallback session user_id.
     *
     * @return int|null
     */
    function auth_user_id()
    {
        $id = Auth::guard('legacy')->id();

        return $id !== null
            ? (int) $id
            : (session('user_id') !== null ? (int) session('user_id') : null);
    }
}
