<?php
// File: controllers/AuthController.php

require_once BASE_PATH . '/includes/audit_helper.php';

class AuthController {
    private $pdoRun;
    private $pdoMain;
    private $pdoBot;
    private $auth;
    private $carouselModel;
    
    public function __construct(PDO $pdoRun, ?PDO $pdoMain = null, ?PDO $pdoBot = null) {
    $this->pdoRun = $pdoRun;
    $this->pdoMain = $pdoMain;
    $this->pdoBot = $pdoBot;

    $this->auth = new Auth($this->pdoRun, $this->pdoMain, $this->pdoBot);

    $this->carouselModel = new Carousel($this->pdoMain ?? $this->pdoRun);
}

    public function handleRequest() {
        Session::start();
        
        $error = '';
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $error = $this->processLogin();
        }

        return [
            'carousels' => $this->carouselModel->getActiveCarousels(),
            'error' => $error
        ];
    }

    private function processLogin(): string {
        $accountId = trim($_POST['account_id'] ?? '');
        $password  = trim($_POST['passwd'] ?? '');

        if ($accountId === '' || $password === '') {
            return "Account ID dan Password tidak boleh kosong.";
        }

        try {
            $user = $this->auth->authenticate($accountId, $password);
            
            if (!$user) {
                $this->logAuthAudit('LOGIN_FAILED', 'sysitc_login', 0, [
                    'account_id' => $accountId,
                    'reason' => 'invalid_credentials',
                ], 0);

                return "Account ID atau Password salah.";
            }

            return $this->handleUserStatus($user);

        } catch (Throwable $e) {
            error_log('Login error: ' . $e->getMessage());
            return "Terjadi kesalahan sistem. Silakan coba lagi nanti.";
        }
    }

    private function handleUserStatus(array $user): string {
    switch ((int) $user['status']) {
        case 1:
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }

            $this->setUserSession($user);
            $this->auth->updateLoginSession($user['login_rec_id'], $user['auth_db'] ?? 'run');

            $this->logAuthAudit('LOGIN_SUCCESS', 'sysitc_login', (int) ($user['login_rec_id'] ?? 0), [
                'account_id' => $user['account_id'] ?? '',
                'account_nm' => $user['account_nm'] ?? '',
                'auth_db' => $user['auth_db'] ?? 'run',
                'status' => (int) ($user['status'] ?? 0),
            ], (int) ($user['user_rec_id'] ?? 0));
            
            header("Location: " . BASE_URL . "dashboard.php");
            exit; 
        
        case 0:
            $this->logAuthAudit('LOGIN_FAILED_INACTIVE', 'sysitc_login', (int) ($user['login_rec_id'] ?? 0), [
                'account_id' => $user['account_id'] ?? '',
                'account_nm' => $user['account_nm'] ?? '',
                'auth_db' => $user['auth_db'] ?? 'run',
                'status' => 0,
            ], (int) ($user['user_rec_id'] ?? 0));

            return "Akun Anda belum aktif. Silakan hubungi admin.";

        case 2:
            $this->logAuthAudit('LOGIN_FAILED_BLOCKED', 'sysitc_login', (int) ($user['login_rec_id'] ?? 0), [
                'account_id' => $user['account_id'] ?? '',
                'account_nm' => $user['account_nm'] ?? '',
                'auth_db' => $user['auth_db'] ?? 'run',
                'status' => 2,
            ], (int) ($user['user_rec_id'] ?? 0));

            return "Akun Anda diblokir.";

        default:
            return "Status akun tidak dikenal.";
    }
}

    private function setUserSession(array $user): void {
        Session::set('user_id', $user['user_rec_id']);
        Session::set('user_rec_id', $user['user_rec_id']);
        Session::set('account_id', $user['account_id']);
        Session::set('user_name', $user['account_nm']);
        Session::set('account_nm', $user['account_nm']);
        Session::set('auth_db', $user['auth_db'] ?? 'run');
    }

    private function logAuthAudit(string $action, string $targetType, ?int $targetId, array $metadata, int $actorUserId): void {
        logAudit($this->pdoRun, $action, $targetType, $targetId, $metadata, $actorUserId);
    }
}
