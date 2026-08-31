<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemSettingsController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($this->isSuperadmin($request), 403);
        $this->ensureTable();
        $this->ensureDefaults();

        return view('admin.system-settings.index', [
            'groups' => $this->getGroupedSettings(),
            'groupLabels' => $this->groupLabels(),
        ]);
    }

    public function api(Request $request): JsonResponse
    {
        abort_unless($this->isSuperadmin($request), 403);
        $this->ensureTable();
        $this->ensureDefaults();

        return match ($request->input('action')) {
            'update_setting' => $this->updateSetting($request),
            'reset_setting' => $this->resetSetting($request),
            default => response()->json(['success' => false, 'error' => 'Unknown action'], 422),
        };
    }

    private function updateSetting(Request $request): JsonResponse
    {
        $key = (string) $request->input('key', '');
        $value = (string) $request->input('value', '');
        $error = $this->validateSetting($key, $value);

        if ($error) {
            return response()->json(['success' => false, 'error' => $error], 422);
        }

        $this->setSetting($key, $value, (int) $request->session()->get('user_id', 0));

        return response()->json(['success' => true, 'message' => 'Setting berhasil disimpan']);
    }

    private function resetSetting(Request $request): JsonResponse
    {
        $key = (string) $request->input('key', '');
        $defaults = $this->defaults();

        if (! isset($defaults[$key])) {
            return response()->json(['success' => false, 'error' => 'Setting tidak dikenal'], 422);
        }

        $this->setSetting($key, $defaults[$key]['value'], (int) $request->session()->get('user_id', 0));

        return response()->json(['success' => true, 'message' => 'Setting dikembalikan ke default', 'default_value' => $defaults[$key]['value']]);
    }

    private function getGroupedSettings(): array
    {
        $defaults = $this->defaults();
        $rows = DB::connection('run')->table('system_settings')->orderBy('setting_key')->get();
        $groups = [];

        foreach ($rows as $row) {
            $default = $defaults[$row->setting_key] ?? null;
            $group = $default['group'] ?? 'general';
            $groups[$group] ??= [];
            $groups[$group][] = [
                'setting_key' => $row->setting_key,
                'setting_value' => $this->cast($row->setting_value, $row->setting_type),
                'setting_type' => $row->setting_type,
                'description' => $row->description ?: ($default['description'] ?? ''),
            ];
        }

        return $groups;
    }

    private function setSetting(string $key, string $value, int $userId): void
    {
        $defaults = $this->defaults();
        $type = $defaults[$key]['type'] ?? 'string';
        $oldValue = DB::connection('run')->table('system_settings')->where('setting_key', $key)->value('setting_value');

        DB::connection('run')->table('system_settings')->updateOrInsert(
            ['setting_key' => $key],
            ['setting_value' => $value, 'setting_type' => $type, 'updated_by' => $userId ?: null, 'updated_at' => now()]
        );

        $this->logAudit('SETTING_UPDATED', 'system_settings', 0, ['key' => $key, 'old_value' => $oldValue, 'new_value' => $value, 'updated_by' => $userId], $userId);
    }

    private function validateSetting(string $key, string $value): ?string
    {
        $default = $this->defaults()[$key] ?? null;
        if (! $default) {
            return 'Setting tidak dikenal';
        }

        return match ($default['type']) {
            'int' => is_numeric($value) && (int) $value >= 0 ? null : 'Harus angka positif',
            'bool' => in_array($value, ['0', '1'], true) ? null : 'Harus 0 atau 1',
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Format email tidak valid',
            default => null,
        };
    }

    private function ensureTable(): void
    {
        DB::connection('run')->statement(
            'CREATE TABLE IF NOT EXISTS system_settings (
                rec_id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value LONGTEXT,
                setting_type VARCHAR(50) DEFAULT "string",
                description VARCHAR(255),
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                updated_by INT DEFAULT NULL,
                INDEX idx_setting_key (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function ensureDefaults(): void
    {
        foreach ($this->defaults() as $key => $config) {
            if (! DB::connection('run')->table('system_settings')->where('setting_key', $key)->exists()) {
                DB::connection('run')->table('system_settings')->insert([
                    'setting_key' => $key,
                    'setting_value' => $config['value'],
                    'setting_type' => $config['type'],
                    'description' => $config['description'],
                ]);
            }
        }
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'bool' => (bool) $value,
            default => (string) $value,
        };
    }

    private function logAudit(string $action, string $targetType, ?int $targetId, array $metadata, int $actorUserId): void
    {
        try {
            DB::connection('run')->table('sys_audit_log')->insert([
                'actor_user_id' => $actorUserId,
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                'ip_address' => request()->ip() ?: '127.0.0.1',
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Audit logging must not block settings updates.
        }
    }

    private function isSuperadmin(Request $request): bool
    {
        $userId = (int) $request->session()->get('user_id', 0);
        if ($userId <= 0) {
            return false;
        }

        return DB::connection('run')
            ->table('sysitc_usracc as ua')
            ->join('sysitc_grpacc as g', function ($join): void {
                $join->on('g.grpaccess', '=', 'ua.access_code')->on('g.grpacc', '=', 'ua.access_account');
            })
            ->where('ua.user_rec_id', $userId)
            ->where('g.grpaccess', '03')
            ->where('g.grpacc', '999')
            ->where('g.grpdesc', 'like', '%SUPER%ADMIN%')
            ->exists();
    }

    private function groupLabels(): array
    {
        return [
            'application' => 'Application',
            'maintenance' => 'Maintenance & Notification',
            'notification' => 'Notification',
            'features' => 'Feature Toggles',
            'upload' => 'Upload & Storage',
            'security' => 'Security Settings',
            'logging' => 'Logging & Audit',
            'cooperative' => 'Koperasi',
            'general' => 'General',
        ];
    }

    private function defaults(): array
    {
        return [
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
            'coop_default_loan_rate' => ['value' => '6', 'type' => 'string', 'group' => 'cooperative', 'description' => 'Bunga pinjaman default (%) untuk pengajuan baru.'],
            'coop_default_loan_method' => ['value' => 'flat', 'type' => 'string', 'group' => 'cooperative', 'description' => 'Metode perhitungan default untuk pengajuan baru (flat/effective/annuity).'],
        ];
    }
}
