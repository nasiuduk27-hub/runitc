<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$basePath = dirname(__DIR__, 2);
require_once $basePath . '/config.php';
require_once BASE_PATH . '/includes/menu_guard.php';
/** @var \PDO $pdo_run */
requireMenuAccess($pdo_run, 'modules/admin/system_settings.php');
requireSuperadmin($pdo_run);

// API handler
if (isset($_GET['api'])) {
    $ctrl = new SystemSettingsController($pdo_run);
    $ctrl->handleApi();
    exit;
}

require_once BASE_PATH . '/models/SystemSettings.php';
$settingsModel = new SystemSettings($pdo_run);
$allSettings = $settingsModel->getAll();

// Group settings
$groups = [];
foreach ($allSettings as $s) {
    $g = $s['group'] ?? 'general';
    if (!isset($groups[$g])) $groups[$g] = [];
    $groups[$g][] = $s;
}

$groupLabels = [
    'application' => 'Application',
    'maintenance' => 'Maintenance & Notification',
    'notification' => 'Notification',
    'features' => 'Feature Toggles',
    'upload' => 'Upload & Storage',
    'security' => 'Security Settings',
    'logging' => 'Logging & Audit',
    'general' => 'General',
];

$pageTitle = 'System Settings';
include BASE_PATH . '/includes/layout_header.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/compact-admin.css">
<div class="dashboard-wrapper min-h-screen bg-brand-bg p-6">
    <div class="max-w-4xl mx-auto space-y-6">

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">System Settings</h1>
                <p class="text-sm text-gray-500 mt-0.5">Kelola konfigurasi aplikasi</p>
            </div>
        </div>

        <?php if (empty($groups)): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center text-gray-400">
            <i class="fa-solid fa-sliders text-4xl mb-3 block"></i>
            <p>Belum ada pengaturan yang tersedia.</p>
        </div>
        <?php else: ?>
        <?php foreach ($groups as $group => $settings): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                <h2 class="font-bold text-gray-900"><?= htmlspecialchars($groupLabels[$group] ?? ucfirst($group)) ?></h2>
            </div>
            <div class="divide-y divide-gray-100">
                <?php foreach ($settings as $setting): ?>
                <div class="p-5" data-key="<?= htmlspecialchars($setting['setting_key']) ?>">
                    <label class="block font-semibold text-gray-800 text-sm"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $setting['setting_key']))) ?></label>
                    <p class="text-xs text-gray-500 mt-0.5 mb-3"><?= htmlspecialchars($setting['description'] ?? '') ?></p>

                    <?php if ($setting['setting_type'] === 'bool'): ?>
                    <label class="inline-flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" class="setting-toggle w-5 h-5 text-brand-primary rounded border-gray-300 focus:ring-brand-primary"
                               data-key="<?= htmlspecialchars($setting['setting_key']) ?>"
                               <?= $setting['setting_value'] ? 'checked' : '' ?>>
                        <span class="text-sm text-gray-600"><?= $setting['setting_value'] ? 'Aktif' : 'Nonaktif' ?></span>
                    </label>

                    <?php else: ?>
                    <div class="flex items-center gap-2">
                        <input type="<?= $setting['setting_type'] === 'int' ? 'number' : 'text' ?>"
                               class="setting-input flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary"
                               data-key="<?= htmlspecialchars($setting['setting_key']) ?>"
                               value="<?= htmlspecialchars((string) $setting['setting_value']) ?>"
                               <?= $setting['setting_type'] === 'int' ? 'min="0"' : '' ?>>
                        <button onclick="resetSetting('<?= htmlspecialchars($setting['setting_key']) ?>')"
                                class="px-3 py-2 text-xs font-semibold bg-gray-100 text-gray-500 rounded-lg hover:bg-gray-200 transition"
                                title="Reset to default">Default</button>
                    </div>
                    <?php endif; ?>
                    <p class="text-xs text-gray-400 mt-1 setting-feedback" id="fb_<?= htmlspecialchars($setting['setting_key']) ?>"></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.querySelectorAll('.setting-input').forEach(input => {
    input.addEventListener('change', function() {
        saveSetting(this.dataset.key, this.value);
    });
});

document.querySelectorAll('.setting-toggle').forEach(cb => {
    cb.addEventListener('change', function() {
        saveSetting(this.dataset.key, this.checked ? '1' : '0');
    });
});

function saveSetting(key, value) {
    const fb = document.getElementById('fb_' + key);
    fb.innerHTML = '<span class="text-amber-500">Menyimpan...</span>';

    const formData = new FormData();
    formData.append('action', 'update_setting');
    formData.append('key', key);
    formData.append('value', value);

    fetch('system_settings.php?api=1', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                fb.innerHTML = '<span class="text-green-600">✓ ' + data.message + '</span>';
                setTimeout(() => fb.innerHTML = '', 3000);
            } else {
                fb.innerHTML = '<span class="text-red-600">✗ ' + (data.error || 'Gagal') + '</span>';
            }
        })
        .catch(() => fb.innerHTML = '<span class="text-red-600">✗ Network error</span>');
}

function resetSetting(key) {
    if (!confirm('Reset "' + key.replace(/_/g, ' ') + '" ke nilai default?')) return;

    const fb = document.getElementById('fb_' + key);
    fb.innerHTML = '<span class="text-amber-500">Mereset...</span>';

    const formData = new FormData();
    formData.append('action', 'reset_setting');
    formData.append('key', key);

    fetch('system_settings.php?api=1', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                fb.innerHTML = '<span class="text-green-600">✓ Reset ke default</span>';
                // Reload page to reflect default value
                setTimeout(() => location.reload(), 1000);
            } else {
                fb.innerHTML = '<span class="text-red-600">✗ ' + (data.error || 'Gagal') + '</span>';
            }
        });
}

tailwind.config = {
    theme: { extend: { colors: { brand: { primary: '#1D4ED8', primaryHover: '#1E40AF', bg: '#f3f4f6', card: '#ffffff' } } } }
}
</script>

<?php include BASE_PATH . '/includes/layout_footer.php'; ?>
