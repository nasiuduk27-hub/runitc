<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class AuthService
{
    public function authenticate(string $accountId, string $password): array|false
    {
        $user = $this->authenticateFromConnection('run', $accountId, $password);
        if ($user) {
            $user['auth_db'] = 'run';

            return $user;
        }

        if ($this->accountExistsInConnection('run', $accountId)) {
            return false;
        }

        $user = $this->authenticateFromConnection('mysql', $accountId, $password);
        if ($user) {
            $user['auth_db'] = 'main';

            return $user;
        }

        return false;
    }

    public function updateLoginSession(int $loginRecId, string $authDb = 'run'): void
    {
        $connection = $authDb === 'main' ? 'mysql' : 'run';

        DB::connection($connection)->update(
            'UPDATE sysitc_login SET token = ?, lastlogin = NOW() WHERE rec_id = ?',
            ['', $loginRecId]
        );
    }

    public function isSuperadmin(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        try {
            return DB::connection('run')
                ->table('sysitc_usracc as ua')
                ->join('sysitc_grpacc as g', function ($join): void {
                    $join->on('g.grpaccess', '=', 'ua.access_code')
                        ->on('g.grpacc', '=', 'ua.access_account');
                })
                ->where('ua.user_rec_id', $userId)
                ->where('g.grpaccess', '03')
                ->where('g.grpacc', '999')
                ->where('g.grpdesc', 'like', '%SUPER%ADMIN%')
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    public function getActiveCarousels(): array
    {
        try {
            return DB::connection('mysql')
                ->table('bis_media')
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    public function requestForgotPassword(string $loginInput): array
    {
        $loginInput = trim($loginInput);

        if ($loginInput === '') {
            return ['success' => false, 'message' => 'Silakan masukkan Account ID atau Email.'];
        }

        try {
            $user = DB::connection('run')
                ->table('sysitc_login as log')
                ->join('sysitc_users as usr', 'log.rec_id', '=', 'usr.login_rec_id')
                ->where(function ($query) use ($loginInput): void {
                    $query->where('log.email_id', $loginInput)
                        ->orWhere('log.account_id', $loginInput);
                })
                ->select('log.rec_id', 'log.account_id', 'log.email_id', 'usr.account_nm', 'usr.token_smart')
                ->first();
        } catch (Throwable) {
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem. Silakan coba lagi.'];
        }

        if (! $user) {
            return ['success' => false, 'message' => 'Account ID / Email tidak ditemukan.'];
        }

        $token = random_int(10000, 99999);
        $tokenExpiredAt = now()->addMinutes(10)->format('Y-m-d H:i:s');

        try {
            DB::connection('run')->table('sysitc_login')
                ->where('rec_id', $user->rec_id)
                ->update([
                    'token' => $token,
                    'token_exp' => $tokenExpiredAt,
                    'token_used' => null,
                ]);
        } catch (Throwable) {
            return ['success' => false, 'message' => 'Gagal menyimpan kode OTP. Silakan coba lagi.'];
        }

        return [
            'success' => true,
            'message' => 'OTP berhasil dibuat.',
            'account_id' => $user->account_id,
            'email' => $user->email_id,
            'name' => $user->account_nm,
            'token' => $token,
            'smart_token' => $user->token_smart ?: $user->email_id,
        ];
    }

    public function insertBotNotification(string $token, string $subject, string $message): void
    {
        if (trim($token) === '') {
            return;
        }

        try {
            DB::connection('bot')->table('bot_nsmartcart')->insert([
                'token' => $token,
                'notif_subject' => $subject,
                'notif_msg' => $message,
            ]);
        } catch (Throwable) {
        }
    }

    public function register(array $data): array
    {
        $accountId = trim((string) ($data['account_id'] ?? ''));
        $email = trim((string) ($data['email_id'] ?? ''));
        $password = (string) ($data['password_id'] ?? '');
        $retype = (string) ($data['retype_password'] ?? '');

        $instansi = trim((string) ($data['instansi'] ?? ''));
        $isIndividu = ! empty($data['is_individu']) ? 1 : 0;
        $accountNm = trim((string) ($data['account_nm'] ?? ''));
        $alias = trim((string) ($data['alias'] ?? ''));
        $dobPost = trim((string) ($data['dob'] ?? ''));
        $sexmf = trim((string) ($data['sexmf'] ?? ''));
        $waCode = trim((string) ($data['wa_code'] ?? ''));
        $waNumber = ltrim(trim((string) ($data['wa_number'] ?? '')), '0');
        $whatsapp = $waNumber !== '' ? $waCode.$waNumber : '';
        $empNo = trim((string) ($data['emp_no'] ?? ''));
        $hrdRecId = isset($data['hrd_rec_id']) ? (int) $data['hrd_rec_id'] : null;

        if ($accountId === '' || strlen($accountId) < 6) {
            return ['success' => false, 'message' => 'Account ID minimal harus 6 karakter!'];
        }

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Format email tidak valid.'];
        }

        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password minimal harus 6 karakter!'];
        }

        if ($password !== $retype) {
            return ['success' => false, 'message' => 'Password dan konfirmasi password tidak cocok.'];
        }

        if ($accountNm === '') {
            return ['success' => false, 'message' => 'Nama lengkap wajib diisi.'];
        }

        if ($isIndividu === 0 && $instansi === '') {
            return ['success' => false, 'message' => 'Instansi / Sekolah wajib diisi atau centang Individu.'];
        }

        $dob = null;
        if ($dobPost !== '') {
            $dobObj = \DateTime::createFromFormat('Y-m-d', $dobPost)
                ?: \DateTime::createFromFormat('d/m/Y', $dobPost);

            if (! $dobObj) {
                return ['success' => false, 'message' => 'Format tanggal lahir tidak valid. Gunakan format dd/mm/yyyy.'];
            }

            $dob = $dobObj->format('Y-m-d');
            $age = (new \DateTime)->diff(new \DateTime($dob))->y;

            if ($age < 17 || $age > 100) {
                return ['success' => false, 'message' => 'Pendaftaran ditolak. Usia Anda harus antara 17 hingga 100 tahun.'];
            }
        }

        $sisterCompanies = [
            'pt. international test center',
            'international test center',
            'pt. itc',
        ];

        $isSisterCompany = $isIndividu === 0 && in_array(strtolower($instansi), $sisterCompanies, true);

        if ($isSisterCompany && $empNo === '') {
            return ['success' => false, 'message' => 'Employee No. wajib diisi untuk pendaftaran internal!'];
        }

        try {
            $exists = DB::connection('run')->table('sysitc_login')->where('account_id', $accountId)->exists();

            if ($exists) {
                return ['success' => false, 'message' => "Maaf, Account ID <b>{$accountId}</b> sudah digunakan. Silakan cari ID lain."];
            }

            if ($isSisterCompany && $hrdRecId <= 0) {
                $emp = DB::connection('mysql')->table('hrd_employee')->where('empno', $empNo)->first();

                if (! $emp) {
                    return ['success' => false, 'message' => 'Employee No. Tidak Sesuai. Silahkan input kembali atau hubungi Administrator/HR.'];
                }

                $hrdRecId = (int) $emp->rec_id;
            }

            $token = random_int(10000, 99999);
            $tokenExpiredAt = now()->addMinutes(10)->format('Y-m-d H:i:s');
            $cmpcd = 0;

            DB::connection('run')->beginTransaction();
            DB::connection('mysql')->beginTransaction();

            try {
                if ($isIndividu === 0 && ! $isSisterCompany) {
                    $client = DB::connection('mysql')->table('sys_mstclient')->where('clientnm', $instansi)->first();

                    if ($client) {
                        $cmpcd = (int) $client->rec_id;
                    } else {
                        $cmpcd = DB::connection('mysql')->table('sys_mstclient')->insertGetId([
                            'clienttype' => '',
                            'clientnm' => $instansi,
                            'entusr' => '',
                        ]);
                    }
                }

                $loginRecId = $this->insertLoginRecord('run', $accountId, $password, $token, $tokenExpiredAt, $email, $whatsapp, $accountNm);
                $userRecId = $this->insertUserRecord('run', $loginRecId, $accountNm, $alias, $dob, $whatsapp, $sexmf, $email, $cmpcd);
                DB::connection('run')->table('sysitc_usermail')->insert([
                    'user_recid' => $userRecId,
                    'email' => $email,
                    'asdefault' => 1,
                ]);

                if ($isSisterCompany) {
                    $loginRecIdMain = $this->insertLoginRecord('mysql', $accountId, $password, $token, $tokenExpiredAt, $email, $whatsapp, $accountNm);
                    $userRecIdMain = $this->insertUserRecord('mysql', $loginRecIdMain, $accountNm, $alias, $dob, $whatsapp, $sexmf, $email, $cmpcd);
                    DB::connection('mysql')->table('sysitc_usermail')->insert([
                        'user_recid' => $userRecIdMain,
                        'email' => $email,
                        'asdefault' => 1,
                    ]);

                    if ($hrdRecId > 0) {
                        DB::connection('mysql')->table('hrd_employee')
                            ->where('rec_id', $hrdRecId)
                            ->update(['itc_user_id' => $userRecIdMain]);
                    }
                }

                DB::connection('run')->commit();
                DB::connection('mysql')->commit();
            } catch (Throwable $e) {
                if (DB::connection('run')->transactionLevel() > 0) {
                    DB::connection('run')->rollBack();
                }
                if (DB::connection('mysql')->transactionLevel() > 0) {
                    DB::connection('mysql')->rollBack();
                }

                throw $e;
            }

            return [
                'success' => true,
                'message' => 'Pendaftaran berhasil.',
                'account_id' => $accountId,
                'email' => $email,
                'token' => $token,
                'name' => $accountNm,
                'institution' => $isIndividu === 1 ? 'Individu' : $instansi,
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function findEmployee(string $employeeNo): ?array
    {
        $employeeNo = trim($employeeNo);

        if ($employeeNo === '') {
            return null;
        }

        try {
            $row = DB::connection('mysql')->table('hrd_employee')
                ->select('rec_id', 'empno')
                ->whereRaw('TRIM(empno) = ?', [$employeeNo])
                ->first();

            return $row ? (array) $row : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function searchClients(string $query): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        try {
            return DB::connection('mysql')->table('sys_mstclient')
                ->select('rec_id', 'clientnm')
                ->where('clientnm', 'like', '%'.$query.'%')
                ->orderBy('clientnm')
                ->limit(10)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function insertLoginRecord(string $connection, string $accountId, string $password, int $token, string $tokenExpiredAt, string $email, string $whatsapp, string $accountNm): int
    {
        return DB::connection($connection)->table('sysitc_login')->insertGetId([
            'account_id' => $accountId,
            'password_id' => md5($password),
            'token' => $token,
            'token_exp' => $tokenExpiredAt,
            'email_id' => $email,
            'entdt' => now()->toDateString(),
            'whatsapp' => $whatsapp,
            'account_nm' => $accountNm,
            'lastlogin' => now(),
        ]);
    }

    private function insertUserRecord(string $connection, int $loginRecId, string $accountNm, string $alias, ?string $dob, string $whatsapp, string $sexmf, string $email, int $cmpcd): int
    {
        return DB::connection($connection)->table('sysitc_users')->insertGetId([
            'login_rec_id' => $loginRecId,
            'account_nm' => $accountNm,
            'alias_nm' => $alias,
            'device_id' => 0,
            'address' => '',
            'prov_cd' => '',
            'kotakabupaten' => '',
            'dob' => $dob,
            'whatsapp' => $whatsapp,
            'sexmf' => $sexmf,
            'status' => 1,
            'token_smart' => $email,
            'cmpcd' => $cmpcd,
        ]);
    }

    public function verifyOtp(string $accountId, string $token): array
    {
        $accountId = trim($accountId);
        $token = trim($token);

        if ($accountId === '' || $token === '') {
            return ['success' => false, 'message' => 'Account ID dan OTP wajib diisi.'];
        }

        try {
            $data = DB::connection('run')
                ->table('sysitc_login')
                ->where('account_id', $accountId)
                ->select('token', 'token_used', 'token_exp')
                ->first();
        } catch (Throwable) {
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem saat verifikasi.'];
        }

        if (! $data) {
            return ['success' => false, 'message' => 'Akun tidak ditemukan.'];
        }

        if ($data->token === null || $data->token === '') {
            return ['success' => false, 'message' => 'Tidak ada kode OTP aktif untuk akun ini. Silakan minta kode baru.'];
        }

        if ((string) $data->token !== $token) {
            return ['success' => false, 'message' => 'Kode OTP yang Anda masukkan salah.'];
        }

        if ($data->token_used !== null) {
            return ['success' => false, 'message' => 'Kode OTP ini sudah pernah digunakan.'];
        }

        if ($data->token_exp !== null && $data->token_exp !== '') {
            $expiry = strtotime((string) $data->token_exp);

            if ($expiry !== false && $expiry < (time() - 3600)) {
                return ['success' => false, 'message' => 'Kode OTP sudah kedaluwarsa. Silakan minta kode baru.'];
            }
        }

        try {
            $consumed = DB::connection('run')->table('sysitc_login')
                ->where('account_id', $accountId)
                ->where('token', $token)
                ->update([
                    'token_used' => now(),
                    'token' => null,
                    'token_exp' => null,
                ]);
        } catch (Throwable) {
            return ['success' => false, 'message' => 'Gagal memverifikasi OTP. Silakan coba lagi.'];
        }

        if ($consumed <= 0) {
            return ['success' => false, 'message' => 'Gagal memverifikasi OTP. Silakan coba lagi.'];
        }

        try {
            DB::connection('run')->statement(
                'UPDATE sysitc_users usr
                 INNER JOIN sysitc_login log ON log.rec_id = usr.login_rec_id
                 SET usr.status = 1
                 WHERE log.account_id = ?',
                [$accountId]
            );
        } catch (Throwable) {
        }

        return [
            'success' => true,
            'message' => 'OTP valid.',
            'account_id' => $accountId,
        ];
    }

    public function resetPassword(string $accountId, string $password, string $retype): array
    {
        $accountId = trim($accountId);

        if ($accountId === '') {
            return ['success' => false, 'message' => 'Session reset password tidak valid.'];
        }

        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password minimal 6 karakter.'];
        }

        if ($password !== $retype) {
            return ['success' => false, 'message' => 'Password dan Re-Type Password tidak sama.'];
        }

        try {
            DB::connection('run')->table('sysitc_login')
                ->where('account_id', $accountId)
                ->update(['password_id' => md5($password)]);

            try {
                DB::connection('mysql')->table('sysitc_login')
                    ->where('account_id', $accountId)
                    ->update(['password_id' => md5($password)]);
            } catch (Throwable) {
            }
        } catch (Throwable) {
            return ['success' => false, 'message' => 'Terjadi kesalahan saat menyimpan password baru.'];
        }

        return [
            'success' => true,
            'message' => 'Password berhasil diubah.',
        ];
    }

    public function logAuthAudit(string $action, string $targetType, ?int $targetId, array $metadata, int $actorUserId): void
    {
        $allowSystemActor = str_starts_with($action, 'LOGIN_FAILED');
        if ($actorUserId <= 0 && ! $allowSystemActor) {
            return;
        }

        try {
            $this->ensureAuditTable();

            DB::connection('run')->table('sys_audit_log')->insert([
                'actor_user_id' => $actorUserId,
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'metadata_json' => ! empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
                'ip_address' => request()->ip() ?: '127.0.0.1',
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function authenticateFromConnection(string $connection, string $accountId, string $password): array|false
    {
        try {
            $row = DB::connection($connection)->selectOne(
                'SELECT log.rec_id AS login_rec_id, log.account_id,
                        usr.rec_id AS user_rec_id, usr.status,
                        usr.account_nm, usr.alias_nm, usr.acc3chrnm
                 FROM sysitc_login log
                 INNER JOIN sysitc_users usr ON log.rec_id = usr.login_rec_id
                 WHERE log.account_id = ?
                   AND log.password_id = MD5(?)
                 LIMIT 1',
                [$accountId, $password]
            );
        } catch (Throwable) {
            return false;
        }

        return $row ? (array) $row : false;
    }

    private function accountExistsInConnection(string $connection, string $accountId): bool
    {
        try {
            return DB::connection($connection)
                ->table('sysitc_login')
                ->where('account_id', $accountId)
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function ensureAuditTable(): void
    {
        DB::connection('run')->statement(
            'CREATE TABLE IF NOT EXISTS sys_audit_log (
                rec_id INT AUTO_INCREMENT PRIMARY KEY,
                actor_user_id INT NOT NULL,
                action VARCHAR(100) NOT NULL,
                target_type VARCHAR(80) NULL,
                target_id INT NULL,
                metadata_json TEXT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(500) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_actor (actor_user_id),
                INDEX idx_action (action),
                INDEX idx_created_at (created_at),
                INDEX idx_target (target_type, target_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
