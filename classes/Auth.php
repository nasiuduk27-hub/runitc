<?php
// File: classes/Auth.php

class Auth
{
    private PDO $db;
    private ?PDO $mainDb;
    private ?PDO $botDb;

    public function __construct(PDO $pdoRun, ?PDO $pdoMain = null, ?PDO $pdoBot = null)
    {
        $this->db = $pdoRun;
        $this->mainDb = $pdoMain;
        $this->botDb = $pdoBot;
    }

    public function authenticate(string $accountId, string $password): array|false
{
    // 1. Cek database RUN-ITC dulu untuk akun baru
    $user = $this->authenticateFromDb($this->db, $accountId, $password);

    if ($user) {
        $user['auth_db'] = 'run';
        return $user;
    }

    // 2. Kalau tidak ketemu, cek database lama / itconenew
    if ($this->mainDb) {
        $user = $this->authenticateFromDb($this->mainDb, $accountId, $password);

        if ($user) {
            $user['auth_db'] = 'main';
            return $user;
        }
    }

    return false;
}

private function authenticateFromDb(PDO $pdo, string $accountId, string $password): array|false
{
    $sql = "SELECT log.rec_id AS login_rec_id, log.account_id,
                   usr.rec_id AS user_rec_id, usr.status,
                   usr.account_nm, usr.alias_nm, usr.acc3chrnm
            FROM sysitc_login log
            INNER JOIN sysitc_users usr ON log.rec_id = usr.login_rec_id
            WHERE log.account_id = ?
              AND log.password_id = MD5(?)
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$accountId, $password]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

    public function getUserGroups(int|string $userId): array
    {
        $sql = "SELECT grp.rec_id AS grpacc_id
                FROM sysitc_usracc usac
                LEFT JOIN sysitc_grpacc grp
                  ON usac.access_code = grp.grpaccess
                 AND usac.access_account = grp.grpacc
                WHERE grp.grpaccess BETWEEN '03' AND '04'
                  AND usac.user_rec_id = ?
                ORDER BY grp.grpaccess";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function updateLoginSession(int|string $loginRecId, string $authDb = 'run'): void
{
    $pdo = $this->db;

    if ($authDb === 'main' && $this->mainDb) {
        $pdo = $this->mainDb;
    }

    $sql = "UPDATE sysitc_login SET token = '', lastlogin = NOW() WHERE rec_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$loginRecId]);
}

    public function isAccountExists(string $accountId, string $email): bool
    {
        $sql = "SELECT rec_id FROM sysitc_login WHERE account_id = ? OR email_id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$accountId, $email]);

        // Mengembalikan nilai true jika data ditemukan, false jika kosong
        return $stmt->fetch(PDO::FETCH_ASSOC) ? true : false;
    }

    public function register(array $data): array
    {
        $accountId = trim($data['account_id'] ?? '');
        $email = trim($data['email_id'] ?? '');
        $password = (string)($data['password_id'] ?? '');
        $retype = (string)($data['retype_password'] ?? '');

        $instansi = trim($data['instansi'] ?? '');
        $isIndividu = isset($data['is_individu']) ? 1 : 0;
        $accountNm = trim($data['account_nm'] ?? '');
        $alias = trim($data['alias'] ?? '');
        $dobPost = trim($data['dob'] ?? '');
        $sexmf = trim($data['sexmf'] ?? '');
        $waCode = trim($data['wa_code'] ?? '');
        $waNumber = ltrim(trim($data['wa_number'] ?? ''), '0');
        $whatsapp = $waNumber !== '' ? $waCode . $waNumber : '';
        $empNo = trim($data['emp_no'] ?? '');

        if ($accountId === '' || strlen($accountId) < 6) {
            return $this->fail('Account ID minimal harus 6 karakter!');
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fail('Format email tidak valid.');
        }

        if (strlen($password) < 6) {
            return $this->fail('Password minimal harus 6 karakter!');
        }

        if ($password !== $retype) {
            return $this->fail('Password dan konfirmasi password tidak cocok.');
        }

        if ($accountNm === '') {
            return $this->fail('Nama lengkap wajib diisi.');
        }

        if ($isIndividu === 0 && $instansi === '') {
            return $this->fail('Instansi / Sekolah wajib diisi atau centang Individu.');
        }

        $dob = null;
        if ($dobPost !== '') {
            $dobObj = DateTime::createFromFormat('d/m/Y', $dobPost);
            if (!$dobObj) {
                return $this->fail('Format tanggal lahir tidak valid. Gunakan format dd/mm/yyyy.');
            }

            $dob = $dobObj->format('Y-m-d');
            $age = (new DateTime())->diff(new DateTime($dob))->y;
            if ($age < 17 || $age > 100) {
                return $this->fail('Pendaftaran ditolak. Usia Anda harus antara 17 hingga 100 tahun.');
            }
        }

        $sisterCompanies = [
            'pt. international test center',
            'international test center',
            'pt. itc',
        ];

        $isSisterCompany = $isIndividu === 0 && in_array(strtolower($instansi), $sisterCompanies, true);

        if ($isSisterCompany && $empNo === '') {
            return $this->fail('Employee No. wajib diisi untuk pendaftaran internal!');
        }

        try {
            $stmtCheck = $this->db->prepare("SELECT rec_id FROM sysitc_login WHERE account_id = ? LIMIT 1");
            $stmtCheck->execute([$accountId]);

            if ($stmtCheck->fetch(PDO::FETCH_ASSOC)) {
                return $this->fail("Maaf, Account ID <b>{$accountId}</b> sudah digunakan. Silakan cari ID lain.");
            }

            if (!$this->mainDb && ($isSisterCompany || ($isIndividu === 0 && !$isSisterCompany))) {
                return $this->fail('Koneksi database utama belum tersedia.');
            }

            $hrdRecId = null;
            if ($isSisterCompany) {
                $stmtEmp = $this->mainDb->prepare("SELECT rec_id FROM hrd_employee WHERE empno = ? LIMIT 1");
                $stmtEmp->execute([$empNo]);
                $emp = $stmtEmp->fetch(PDO::FETCH_ASSOC);

                if (!$emp) {
                    return $this->fail('Employee No. Tidak Sesuai. Silahkan input kembali atau hubungi Administrator/HR.');
                }

                $hrdRecId = $emp['rec_id'];
            }

            $otpTtlMinutes = 10;
            $token = random_int(10000, 99999);
            $tokenExpiredAt = (new DateTimeImmutable("+{$otpTtlMinutes} minutes"))->format('Y-m-d H:i:s');
            $cmpcd = 0;

            $this->db->beginTransaction();
            if ($isSisterCompany && $this->mainDb) {
                $this->mainDb->beginTransaction();
            }

            if ($isIndividu === 0 && !$isSisterCompany && $this->mainDb) {
                $stmtClient = $this->mainDb->prepare("SELECT rec_id FROM sys_mstclient WHERE clientnm = ? LIMIT 1");
                $stmtClient->execute([$instansi]);
                $client = $stmtClient->fetch(PDO::FETCH_ASSOC);

                if ($client) {
                    $cmpcd = $client['rec_id'];
                } else {
                    $stmtInsertClient = $this->mainDb->prepare("INSERT INTO sys_mstclient (clientnm) VALUES (?)");
                    $stmtInsertClient->execute([$instansi]);
                    $cmpcd = $this->mainDb->lastInsertId();
                }
            }

            $loginRecId = $this->insertLogin($this->db, $accountId, $password, $token, $tokenExpiredAt, $email, $whatsapp, $accountNm);
            $userRecId = $this->insertUser($this->db, $loginRecId, $accountNm, $alias, $dob, $whatsapp, $sexmf, $email, $cmpcd);
            $this->insertUserMail($this->db, $userRecId, $email);

            if ($isSisterCompany && $this->mainDb) {
                $loginRecIdMain = $this->insertLogin($this->mainDb, $accountId, $password, $token, $tokenExpiredAt, $email, $whatsapp, $accountNm);
                $userRecIdMain = $this->insertUser($this->mainDb, $loginRecIdMain, $accountNm, $alias, $dob, $whatsapp, $sexmf, $email, $cmpcd);
                $this->insertUserMail($this->mainDb, $userRecIdMain, $email);

                if ($hrdRecId !== null) {
                    $stmtUpdateEmp = $this->mainDb->prepare("UPDATE hrd_employee SET itc_user_id = ? WHERE rec_id = ?");
                    $stmtUpdateEmp->execute([$userRecIdMain, $hrdRecId]);
                }
            }

            $this->db->commit();
            if ($this->mainDb && $this->mainDb->inTransaction()) {
                $this->mainDb->commit();
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
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ($this->mainDb && $this->mainDb->inTransaction()) {
                $this->mainDb->rollBack();
            }

            return $this->fail($e->getMessage());
        }
    }

    public function requestForgotPassword(string $loginInput): array
    {
        $loginInput = trim($loginInput);

        if ($loginInput === '') {
            return $this->fail('Silakan masukkan Account ID atau Email.');
        }

        $sql = "SELECT log.rec_id, log.account_id, log.email_id,
                       usr.account_nm, usr.token_smart
                FROM sysitc_login log
                INNER JOIN sysitc_users usr ON log.rec_id = usr.login_rec_id
                WHERE log.email_id = ? OR log.account_id = ?
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$loginInput, $loginInput]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return $this->fail('Account ID / Email tidak ditemukan.');
        }

        $otpTtlMinutes = 10;
        $token = random_int(10000, 99999);
        $tokenExpiredAt = (new DateTimeImmutable("+{$otpTtlMinutes} minutes"))->format('Y-m-d H:i:s');

        $stmtUpdate = $this->db->prepare("
            UPDATE sysitc_login
            SET token = ?,
                token_exp = ?,
                token_used = NULL
            WHERE rec_id = ?
        ");
        $stmtUpdate->execute([$token, $tokenExpiredAt, $user['rec_id']]);

        return [
            'success' => true,
            'message' => 'OTP berhasil dibuat.',
            'account_id' => $user['account_id'],
            'email' => $user['email_id'],
            'name' => $user['account_nm'],
            'token' => $token,
            'smart_token' => $user['token_smart'] ?: $user['email_id'],
        ];
    }

    public function verifyOtp(string $accountId, string $token): array
    {
        $accountId = trim($accountId);
        $token = trim($token);

        if ($accountId === '' || $token === '') {
            return $this->fail('Account ID dan OTP wajib diisi.');
        }

        try {
            return $this->processVerifyOtp($this->db, $accountId, $token);
        } catch (Throwable $e) {
            // Jika gagal di DB utama (RUN), coba di DB MAIN jika ada
            if ($this->mainDb) {
                try {
                    return $this->processVerifyOtp($this->mainDb, $accountId, $token);
                } catch (Throwable $eMain) {
                    return $this->fail('Terjadi kesalahan sistem saat verifikasi (Main): ' . $eMain->getMessage());
                }
            }
            return $this->fail('Terjadi kesalahan sistem saat verifikasi: ' . $e->getMessage());
        }
    }

    private function processVerifyOtp(PDO $pdo, string $accountId, string $token): array
    {
        // 1. Ambil data token dulu untuk pengecekan detail
        $sqlCheck = "SELECT token, token_used, token_exp FROM sysitc_login WHERE account_id = ? LIMIT 1";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([$accountId]);
        $data = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return $this->fail('Akun tidak ditemukan.');
        }

        // 2. Validasi Token
        if ($data['token'] === null || $data['token'] === '') {
            return $this->fail('Tidak ada kode OTP aktif untuk akun ini. Silakan minta kode baru.');
        }

        if ($data['token'] !== $token) {
            return $this->fail('Kode OTP yang Anda masukkan salah.');
        }

        if ($data['token_used'] !== null) {
            return $this->fail('Kode OTP ini sudah pernah digunakan.');
        }

        // 3. Validasi Expiry (dengan toleransi 1 jam untuk perbedaan timezone server)
        if ($data['token_exp'] !== null && $data['token_exp'] !== '') {
            $expiry = strtotime($data['token_exp']);
            if ($expiry !== false && $expiry < (time() - 3600)) { 
                return $this->fail('Kode OTP sudah kedaluwarsa. Silakan minta kode baru.');
            }
        }

        // 4. Consume Token
        $sqlConsume = "
            UPDATE sysitc_login
            SET token_used = NOW(),
                token = NULL,
                token_exp = NULL
            WHERE account_id = ? AND token = ?
        ";
        $stmtConsume = $pdo->prepare($sqlConsume);
        $stmtConsume->execute([$accountId, $token]);

        if ($stmtConsume->rowCount() > 0) {
            // Aktifkan User
            $stmtActive = $pdo->prepare("
                UPDATE sysitc_users usr 
                INNER JOIN sysitc_login log ON log.rec_id = usr.login_rec_id 
                SET usr.status = 1 
                WHERE log.account_id = ?
            ");
            $stmtActive->execute([$accountId]);

            return [
                'success' => true,
                'message' => 'OTP valid.',
                'account_id' => $accountId,
            ];
        }

        return $this->fail('Gagal memverifikasi OTP. Silakan coba lagi.');
    }

    public function resetPassword(string $accountId, string $password, string $retype): array
    {
        $accountId = trim($accountId);

        if ($accountId === '') {
            return $this->fail('Session reset password tidak valid.');
        }

        if (strlen($password) < 6) {
            return $this->fail('Password minimal 6 karakter.');
        }

        if ($password !== $retype) {
            return $this->fail('Password dan Re-Type Password tidak sama.');
        }

        $stmt = $this->db->prepare("UPDATE sysitc_login SET password_id = MD5(?) WHERE account_id = ?");
        $stmt->execute([$password, $accountId]);

        if ($this->mainDb) {
            $stmtMain = $this->mainDb->prepare("UPDATE sysitc_login SET password_id = MD5(?) WHERE account_id = ?");
            $stmtMain->execute([$password, $accountId]);
        }

        return [
            'success' => true,
            'message' => 'Password berhasil diubah.',
        ];
    }

    public function insertBotNotification(string $token, string $subject, string $message): void
    {
        if (!$this->botDb || trim($token) === '') {
            return;
        }

        $sql = "INSERT INTO bot_nsmartcart (token, notif_subject, notif_msg) VALUES (?, ?, ?)";
        $stmt = $this->botDb->prepare($sql);
        $stmt->execute([$token, $subject, $message]);
    }

    private function insertLogin(PDO $pdo, string $accountId, string $password, int $token, string $tokenExpiredAt, string $email, string $whatsapp, string $accountNm): int
    {
        $sql = "INSERT INTO sysitc_login
                    (account_id, password_id, token, token_exp, token_used, email_id, entdt, whatsapp, account_nm)
                VALUES
                    (?, MD5(?), ?, ?, NULL, ?, CURDATE(), ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$accountId, $password, $token, $tokenExpiredAt, $email, $whatsapp, $accountNm]);

        return (int)$pdo->lastInsertId();
    }

    private function insertUser(PDO $pdo, int $loginRecId, string $accountNm, string $alias, ?string $dob, string $whatsapp, string $sexmf, string $email, int|string $cmpcd): int
    {
        $sql = "INSERT INTO sysitc_users
                    (login_rec_id, account_nm, alias_nm, dob, whatsapp, sexmf, status, token_smart, cmpcd)
                VALUES
                    (?, ?, ?, ?, ?, ?, 0, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$loginRecId, $accountNm, $alias, $dob, $whatsapp, $sexmf, $email, $cmpcd]);

        return (int)$pdo->lastInsertId();
    }

    private function insertUserMail(PDO $pdo, int $userRecId, string $email): void
    {
        $sql = "INSERT INTO sysitc_usermail (user_recid, email, asdefault) VALUES (?, ?, 1)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userRecId, $email]);
    }

    private function clearToken(string $accountId): void
    {
        $stmt = $this->db->prepare("
            UPDATE sysitc_login
            SET token = NULL,
                token_exp = NULL,
                token_used = NOW()
            WHERE account_id = ?
        ");
        $stmt->execute([$accountId]);

        if ($this->mainDb) {
            $stmtMain = $this->mainDb->prepare("
                UPDATE sysitc_login
                SET token = NULL,
                    token_exp = NULL,
                    token_used = NOW()
                WHERE account_id = ?
            ");
            $stmtMain->execute([$accountId]);
        }
    }

    private function fail(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
        ];
    }
}
