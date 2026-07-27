<?php

class SystemSettings
{
    private PDO $pdoRun;

    private array $defaults = [
        'app_name' => ['value' => 'RUNITC', 'type' => 'string', 'group' => 'application', 'description' => 'Nama aplikasi yang tampil di header dan title'],
        'app_timezone' => ['value' => 'Asia/Jakarta', 'type' => 'string', 'group' => 'application', 'description' => 'Timezone untuk timestamp sistem'],
        'maintenance_mode' => ['value' => '0', 'type' => 'bool', 'group' => 'maintenance', 'description' => 'Aktifkan mode maintenance (non-superadmin diblokir)'],
        'maintenance_message' => ['value' => 'Sistem sedang dalam pemeliharaan. Silakan kembali lagi nanti.', 'type' => 'string', 'group' => 'maintenance', 'description' => 'Pesan yang tampil saat maintenance mode ON'],
        'notification_email' => ['value' => '', 'type' => 'string', 'group' => 'notification', 'description' => 'Email penerima notifikasi critical alert'],
        'feature_2fa' => ['value' => '0', 'type' => 'bool', 'group' => 'features', 'description' => 'Aktifkan Two-Factor Authentication'],
        'feature_api_access' => ['value' => '0', 'type' => 'bool', 'group' => 'features', 'description' => 'Aktifkan API Access'],
        'feature_advanced_reporting' => ['value' => '1', 'type' => 'bool', 'group' => 'features', 'description' => 'Aktifkan Advanced Reporting'],
        'feature_websocket' => ['value' => '0', 'type' => 'bool', 'group' => 'features', 'description' => 'Aktifkan WebSocket Real-time Updates'],
        'feature_audit_log' => ['value' => '1', 'type' => 'bool', 'group' => 'features', 'description' => 'Aktifkan Audit Log'],
        'upload_max_size' => ['value' => '50', 'type' => 'int', 'group' => 'upload', 'description' => 'Max upload file size (MB)'],
        'upload_allowed_types' => ['value' => 'pdf,docx,zip,xlsx,jpg,png', 'type' => 'string', 'group' => 'upload', 'description' => 'Allowed file extensions (comma-separated)'],
        'session_timeout' => ['value' => '120', 'type' => 'int', 'group' => 'security', 'description' => 'Session timeout (minutes)'],
        'password_min_length' => ['value' => '8', 'type' => 'int', 'group' => 'security', 'description' => 'Minimum password length'],
        'password_expiry_days' => ['value' => '0', 'type' => 'int', 'group' => 'security', 'description' => 'Password expiry (days, 0 = never)'],
        'max_login_attempts' => ['value' => '5', 'type' => 'int', 'group' => 'security', 'description' => 'Max login attempts before lockout'],
        'lockout_duration' => ['value' => '30', 'type' => 'int', 'group' => 'security', 'description' => 'Lockout duration (minutes)'],
        'require_email_verify' => ['value' => '0', 'type' => 'bool', 'group' => 'security', 'description' => 'Require email verification for new users'],
        'audit_log_retention_days' => ['value' => '90', 'type' => 'int', 'group' => 'logging', 'description' => 'Audit log retention (days, 0 = forever)'],
        'auto_delete_old_logs' => ['value' => '0', 'type' => 'bool', 'group' => 'logging', 'description' => 'Auto-delete old audit logs'],
        'log_sensitive_data' => ['value' => '1', 'type' => 'bool', 'group' => 'logging', 'description' => 'Log sensitive data (password reset, login)'],
    ];

    public function __construct(PDO $pdoRun)
    {
        $this->pdoRun = $pdoRun;
        $this->ensureTable();
        $this->ensureDefaults();
    }

    private function ensureTable(): void
    {
        $this->pdoRun->exec("
            CREATE TABLE IF NOT EXISTS system_settings (
                rec_id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value LONGTEXT,
                setting_type VARCHAR(50) DEFAULT 'string',
                description VARCHAR(255),
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                updated_by INT DEFAULT NULL,
                INDEX idx_setting_key (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function ensureDefaults(): void
    {
        foreach ($this->defaults as $key => $cfg) {
            $stmt = $this->pdoRun->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            if ((int) $stmt->fetchColumn() === 0) {
                $ins = $this->pdoRun->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)");
                $ins->execute([$key, $cfg['value'], $cfg['type'], $cfg['description']]);
            }
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $stmt = $this->pdoRun->prepare("SELECT setting_value, setting_type FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return $default;
        return $this->cast($row['setting_value'], $row['setting_type']);
    }

    public function set(string $key, mixed $value, ?int $updatedBy = null): void
    {
        $cfg = $this->defaults[$key] ?? null;
        $type = $cfg ? $cfg['type'] : gettype($value);
        $strValue = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

        $stmt = $this->pdoRun->prepare("
            INSERT INTO system_settings (setting_key, setting_value, setting_type, updated_by, updated_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()
        ");
        $stmt->execute([$key, $strValue, $type, $updatedBy]);

        $old = $this->getOldValue($key);
        logAudit($this->pdoRun, 'SETTING_UPDATED', 'system_settings', 0, [
            'key' => $key,
            'old_value' => $old,
            'new_value' => $strValue,
            'updated_by' => $updatedBy,
        ]);
    }

    public function getAll(): array
    {
        $stmt = $this->pdoRun->query("SELECT * FROM system_settings ORDER BY setting_key ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $row['setting_value'] = $this->cast($row['setting_value'], $row['setting_type']);
            $cfg = $this->defaults[$row['setting_key']] ?? null;
            $row['group'] = $cfg['group'] ?? 'general';
            $row['description'] = $row['description'] ?: ($cfg['description'] ?? '');
            $result[] = $row;
        }
        return $result;
    }

    public function getGroup(string $group): array
    {
        return array_filter($this->getAll(), fn($s) => ($s['group'] ?? '') === $group);
    }

    public function resetToDefault(string $key, ?int $updatedBy = null): void
    {
        $cfg = $this->defaults[$key] ?? null;
        if (!$cfg) return;
        $this->set($key, $cfg['value'], $updatedBy);
    }

    public function validate(string $key, mixed $value): ?string
    {
        $cfg = $this->defaults[$key] ?? null;
        if (!$cfg) return 'Setting tidak dikenal';

        return match ($cfg['type']) {
            'int' => (is_numeric($value) && (int) $value >= 0) ? null : 'Harus angka positif',
            'bool' => (in_array((string) $value, ['0', '1'])) ? null : 'Harus 0 atau 1',
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Format email tidak valid',
            default => null,
        };
    }

    public function getDefaultValue(string $key): mixed
    {
        return $this->defaults[$key]['value'] ?? null;
    }

    private function getOldValue(string $key): ?string
    {
        $stmt = $this->pdoRun->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        return $stmt->fetchColumn() ?: null;
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'bool' => (bool) $value,
            'float' => (float) $value,
            default => (string) $value,
        };
    }

    public function getDefaults(): array
    {
        return $this->defaults;
    }
}
