<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LegacyModuleController extends Controller
{
    public function __invoke(Request $request, string $path = ''): Response|RedirectResponse
    {
        $relativePath = trim(str_replace('\\', '/', $path), '/');

        if ($relativePath === '') {
            abort(404);
        }

        if (! str_ends_with(strtolower($relativePath), '.php')) {
            $relativePath .= '.php';
        }

        $target = realpath(base_path('modules/'.$relativePath));
        $modulesRoot = realpath(base_path('modules'));

        if (! $target || ! $modulesRoot || ! str_starts_with($target, $modulesRoot.DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        $this->syncLaravelSessionToNative($request);

        $previousCwd = getcwd();
        chdir(dirname($target));

        ob_start();

        try {
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($target, true);
            }

            require $target;
            $content = ob_get_clean();
        } finally {
            if (is_string($previousCwd)) {
                chdir($previousCwd);
            }
        }

        $this->syncNativeSessionToLaravel($request);

        foreach (headers_list() as $header) {
            if (stripos($header, 'Location:') !== 0) {
                continue;
            }

            header_remove('Location');

            return redirect($this->normalizeLegacyLocation(trim(substr($header, 9)), dirname($relativePath)));
        }

        return response($content);
    }

    private function syncLaravelSessionToNative(Request $request): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        foreach (['user_id', 'user_rec_id', 'account_id', 'user_name', 'account_nm', 'auth_db'] as $key) {
            if ($request->session()->has($key)) {
                $_SESSION[$key] = $request->session()->get($key);
            }
        }
    }

    private function syncNativeSessionToLaravel(Request $request): void
    {
        foreach (['user_id', 'user_rec_id', 'account_id', 'user_name', 'account_nm', 'auth_db', 'success_msg', 'error_msg', 'change_email_new', 'change_email_old', 'change_email_otp', 'change_email_exp'] as $key) {
            if (array_key_exists($key, $_SESSION)) {
                $request->session()->put($key, $_SESSION[$key]);
            }
        }
    }

    private function normalizeLegacyLocation(string $location, string $currentDir): string
    {
        if ($location === '') {
            return url('/modules/'.$currentDir.'/index.php');
        }

        if (preg_match('/^https?:\/\//i', $location) || str_starts_with($location, '/')) {
            return $location;
        }

        $parts = explode('?', $location, 2);
        $path = $parts[0];
        $query = isset($parts[1]) ? '?'.$parts[1] : '';

        if (! str_ends_with(strtolower($path), '.php')) {
            $path .= '.php';
        }

        return url('/modules/'.trim($currentDir.'/'.$path, '/')).$query;
    }
}
