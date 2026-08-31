<?php

// File: classes/Csrf.php

// ============ CSRF TOKEN HELPER ============
// DEVELOPMENT: validation disabled. Flip to true before production.
// ===========================================
if (! defined('CSRF_ENABLED')) {
    define('CSRF_ENABLED', false); // TODO: Set true before production
}

class Csrf
{
    /**
     * Get or generate the CSRF token.
     */
    public static function getToken(): string
    {
        Session::start();

        if (! Session::has('csrf_token')) {
            Session::set('csrf_token', bin2hex(random_bytes(32)));
        }

        return Session::get('csrf_token');
    }

    /**
     * Generate HTML for hidden CSRF input field.
     */
    public static function html(): string
    {
        $token = self::getToken();

        return '<input type="hidden" name="csrf_token" value="'.
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8').
            '">';
    }

    /**
     * Validate token without stopping execution.
     * Useful for endpoint/API/manual checking.
     */
    public static function validate(?string $token = null): bool
    {
        // DEVELOPMENT: skip CSRF validation
        if (! CSRF_ENABLED) {
            return true;
        }

        $sessionToken = self::getToken();

        if ($token === null || $token === '') {
            $token = self::getRequestToken();
        }

        return ! empty($token) && hash_equals($sessionToken, $token);
    }

    /**
     * Alias for older code that may call Csrf::check().
     */
    public static function check(?string $token = null): bool
    {
        return self::validate($token);
    }

    /**
     * Verify token for POST requests.
     * If invalid, stop execution and return JSON for AJAX/fetch requests.
     */
    public static function verify(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        if (self::validate()) {
            return;
        }

        http_response_code(403);

        if (self::isAjaxOrJsonRequest()) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            header('Content-Type: application/json; charset=utf-8');

            echo json_encode([
                'success' => false,
                'status' => 'error',
                'msg' => 'CSRF token tidak valid atau session expired. Silakan refresh halaman dan coba lagi.',
                'message' => 'CSRF token tidak valid atau session expired. Silakan refresh halaman dan coba lagi.',
            ]);
            exit;
        }

        exit('<h1>403 Forbidden</h1><p>Invalid CSRF token. Parameter keamanan tidak sesuai. Silakan refresh halaman dan coba lagi.</p>');
    }

    /**
     * Read CSRF token from normal POST, AJAX header, or JSON body.
     */
    private static function getRequestToken(): string
    {
        if (! empty($_POST['csrf_token'])) {
            return (string) $_POST['csrf_token'];
        }

        if (! empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
        }

        if (! empty($_SERVER['HTTP_X_CSRF'])) {
            return (string) $_SERVER['HTTP_X_CSRF'];
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);

            if (is_array($data) && ! empty($data['csrf_token'])) {
                return (string) $data['csrf_token'];
            }
        }

        return '';
    }

    /**
     * Detect request that expects JSON.
     */
    private static function isAjaxOrJsonRequest(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

        return strtolower($requestedWith) === 'xmlhttprequest'
            || stripos($accept, 'application/json') !== false
            || stripos($contentType, 'application/json') !== false
            || isset($_POST['file_action'])
            || isset($_POST['action'])
            || isset($_POST['user_id'])
            || isset($_POST['admin']);
    }
}
