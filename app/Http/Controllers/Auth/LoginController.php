<?php

namespace App\Http\Controllers\Auth;

use App\Auth\LegacyUser;
use App\Http\Controllers\Controller;
use App\Services\AuthService;
use App\Services\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly MailService $mailService,
    ) {}

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function submitForgotPassword(Request $request): RedirectResponse
    {
        $loginInput = trim((string) $request->input('login_input'));

        if ($loginInput === '') {
            return back()->withErrors(['login_input' => 'Silakan masukkan Account ID atau Email.']);
        }

        $result = $this->authService->requestForgotPassword($loginInput);

        if (! $result['success']) {
            return back()->withErrors(['login_input' => $result['message']]);
        }

        $request->session()->put([
            'verify_account_id' => $result['account_id'],
            'verify_purpose' => 'forgot_password',
            'verify_timeout' => time() + 300,
        ]);

        $subject = 'Reset ITCONE Password';
        $body = 'Hi '.ucwords(strtolower((string) $result['name'])).",\n\n"
            ."Berikut adalah 5 digit kode OTP untuk memulihkan password Anda:\n\n"
            .'OTP CODE = '.$result['token']."\n\n"
            ."Kode ini hanya berlaku selama 5 menit. Jangan berikan kode ini kepada siapapun.\n\nBest Regards,\nITCONE";

        $this->mailService->send((string) $result['email'], (string) $result['name'], $subject, $body);
        $this->authService->insertBotNotification((string) $result['smart_token'], $subject, $body);

        return redirect()->route('verify-otp')->with('success_msg', 'Kode OTP pemulihan telah dikirim ke email Anda.');
    }

    public function showRegister(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('user_id')) {
            return $this->redirectAfterLogin((int) auth_user_id());
        }

        return view('auth.register');
    }

    public function clientSearch(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');

        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }

        return response()->json($this->authService->searchClients($query));
    }

    public function submitRegister(Request $request): RedirectResponse
    {
        $isIndividu = $request->input('is_individu') ? 1 : 0;
        $institution = trim((string) $request->input('instansi', ''));
        $employeeNo = trim((string) $request->input('emp_no', ''));
        $data = $request->all();

        if ($isIndividu === 0 && $this->isEmployeeNoRequiredInstitution($institution)) {
            $employee = $this->authService->findEmployee($employeeNo);

            if ($employee === null) {
                return redirect()->route('register')
                    ->with('error_msg', 'Employee No tidak ditemukan di database. Silakan periksa kembali atau hubungi Administrator/HR.')
                    ->withInput();
            }

            $data['hrd_rec_id'] = $employee['rec_id'];
        }

        $result = $this->authService->register($data);

        if (! $result['success']) {
            return redirect()->route('register')
                ->with('error_msg', $result['message'])
                ->withInput();
        }

        $subject = 'Verifikasi Pendaftaran - ITCONE';
        $body = 'Halo '.ucwords(strtolower((string) $result['name'])).' dari '.ucwords(strtolower((string) $result['institution'])).",\n\n"
            ."Terima kasih telah mendaftar di Portal ITC.\nBerikut adalah 5 digit kode OTP Anda:\n\n"
            .'OTP CODE = '.$result['token']."\n\nSilakan masukkan kode ini di halaman verifikasi.\n\nSalam,\nTim ITCONE";

        $this->mailService->send((string) $result['email'], (string) $result['name'], $subject, $body);
        $this->authService->insertBotNotification((string) $result['email'], $subject, $body);

        $request->session()->put([
            'verify_account_id' => $result['account_id'],
            'verify_purpose' => 'register',
            'verify_timeout' => time() + 300,
        ]);

        return redirect()->route('verify-otp')->with('success_msg', 'Pendaftaran berhasil! Kode OTP telah dikirim ke email Anda.');
    }

    private function normalizeInstitutionName(string $institution): string
    {
        $value = strtolower(trim($institution));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }

    private function isEmployeeNoRequiredInstitution(string $institution): bool
    {
        return in_array($this->normalizeInstitutionName($institution), [
            'pt international test center',
            'international test center',
            'pt itc',
        ], true);
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('verify_account_id')) {
            return redirect()->route('register')->with('error_msg', 'Akses ditolak. Sesi verifikasi tidak ditemukan atau telah kedaluwarsa.');
        }

        $timeout = (int) $request->session()->get('verify_timeout', 0);

        if ($timeout <= 0 || time() > $timeout) {
            $request->session()->forget(['verify_account_id', 'verify_purpose', 'verify_timeout']);

            return redirect()->route('login')->with('error_msg', 'Kode OTP telah kedaluwarsa (lebih dari 5 menit). Silakan ulangi proses kembali.');
        }

        return view('auth.verify-otp', ['remainingSeconds' => max(0, $timeout - time())]);
    }

    public function submitVerify(Request $request): RedirectResponse
    {
        if (! $request->session()->has('verify_account_id')) {
            return redirect()->route('login')->with('error_msg', 'Sesi verifikasi tidak ditemukan. Silakan ulangi.');
        }

        $accountId = (string) $request->session()->get('verify_account_id');
        $token = trim((string) $request->input('full_token'));

        if ($token === '') {
            return back()->withErrors(['full_token' => 'Silakan masukkan kode OTP.']);
        }

        $result = $this->authService->verifyOtp($accountId, $token);

        if (! $result['success']) {
            return back()->withErrors(['full_token' => $result['message']]);
        }

        $request->session()->forget('verify_timeout');

        if ($request->session()->get('verify_purpose') === 'forgot_password') {
            $request->session()->put('reset_account_id', $accountId);
            $request->session()->forget('verify_account_id');

            return redirect()->route('reset-password')->with('success_msg', 'Token valid! Silakan buat password baru Anda.');
        }

        $request->session()->forget('verify_account_id');

        return redirect()->route('login')->with('success_msg', 'Verifikasi berhasil! Akun Anda sudah aktif dan siap digunakan.');
    }

    public function showResetPassword(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('reset_account_id')) {
            return redirect()->route('login')->with('error_msg', 'Sesi reset password tidak valid.');
        }

        return view('auth.reset-password');
    }

    public function submitResetPassword(Request $request): RedirectResponse
    {
        if (! $request->session()->has('reset_account_id')) {
            return redirect()->route('login')->with('error_msg', 'Sesi reset password tidak valid.');
        }

        $accountId = (string) $request->session()->get('reset_account_id');
        $password = (string) $request->input('password_id');
        $retype = (string) $request->input('retype_password');

        $result = $this->authService->resetPassword($accountId, $password, $retype);

        if (! $result['success']) {
            return back()->withErrors(['password_id' => $result['message']]);
        }

        $request->session()->forget('reset_account_id');

        return redirect()->route('login')->with('success_msg', $result['message'].' Silakan login dengan password baru Anda.');
    }

    public function showLoginForm(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('user_id')) {
            return $this->redirectAfterLogin((int) auth_user_id());
        }

        $data = ['carousels' => $this->authService->getActiveCarousels()];

        if ($request->query('otp_expired') === '1') {
            $data['error_msg'] = 'Waktu verifikasi telah habis. Silakan ulangi proses kembali.';
            $request->session()->flash('error_msg', $data['error_msg']);
        }

        return view('auth.login', $data);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'account_id' => ['required', 'string'],
            'passwd' => ['required', 'string'],
        ]);

        $user = $this->authService->authenticate($credentials['account_id'], $credentials['passwd']);

        if (! $user) {
            $this->authService->logAuthAudit('LOGIN_FAILED', 'sysitc_login', 0, [
                'account_id' => $credentials['account_id'],
                'reason' => 'invalid_credentials',
            ], 0);

            return back()
                ->withInput(['account_id' => $credentials['account_id']])
                ->withErrors(['account_id' => 'Account ID atau Password salah.']);
        }

        return $this->handleUserStatus($request, $user);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('legacy')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function handleUserStatus(Request $request, array $user): RedirectResponse
    {
        $status = (int) ($user['status'] ?? -1);

        if ($status !== 1) {
            $action = match ($status) {
                0 => 'LOGIN_FAILED_INACTIVE',
                2 => 'LOGIN_FAILED_BLOCKED',
                default => 'LOGIN_FAILED_UNKNOWN_STATUS',
            };

            $message = match ($status) {
                0 => 'Akun Anda belum aktif. Silakan hubungi admin.',
                2 => 'Akun Anda diblokir.',
                default => 'Status akun tidak dikenal.',
            };

            $this->authService->logAuthAudit($action, 'sysitc_login', (int) ($user['login_rec_id'] ?? 0), [
                'account_id' => $user['account_id'] ?? '',
                'account_nm' => $user['account_nm'] ?? '',
                'auth_db' => $user['auth_db'] ?? 'run',
                'status' => $status,
            ], (int) ($user['user_rec_id'] ?? 0));

            return back()
                ->withInput(['account_id' => $user['account_id'] ?? ''])
                ->withErrors(['account_id' => $message]);
        }

        $request->session()->regenerate();
        $request->session()->put([
            'user_id' => $user['user_rec_id'],
            'user_rec_id' => $user['user_rec_id'],
            'account_id' => $user['account_id'],
            'user_name' => $user['account_nm'],
            'account_nm' => $user['account_nm'],
            'auth_db' => $user['auth_db'] ?? 'run',
        ]);

        Auth::guard('legacy')->login(new LegacyUser(
            (int) $user['user_rec_id'],
            (string) ($user['account_nm'] ?? ''),
            (string) ($user['account_id'] ?? ''),
            (string) $user['user_rec_id'],
            (string) ($user['auth_db'] ?? 'run'),
        ));

        $this->authService->updateLoginSession((int) $user['login_rec_id'], $user['auth_db'] ?? 'run');
        $this->authService->logAuthAudit('LOGIN_SUCCESS', 'sysitc_login', (int) ($user['login_rec_id'] ?? 0), [
            'account_id' => $user['account_id'] ?? '',
            'account_nm' => $user['account_nm'] ?? '',
            'auth_db' => $user['auth_db'] ?? 'run',
            'status' => $status,
        ], (int) ($user['user_rec_id'] ?? 0));

        return $this->redirectAfterLogin((int) $user['user_rec_id']);
    }

    private function redirectAfterLogin(int $userId): RedirectResponse
    {
        if ($this->authService->isSuperadmin($userId)) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('dashboard');
    }
}
