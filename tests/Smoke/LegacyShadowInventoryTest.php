<?php

namespace Tests\Smoke;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Group;

/**
 * Inventaris lapisan legacy yang sudah dinamespaced ke app/Support/Legacy
 * dan guard arsip/modules yang membayangi route Laravel.
 *
 * Fase saat ini: class global di app/Support telah dipindah ke
 * App\Support\Legacy\{Nisn,TadAccess,ParticipantRecap,AuditLog,...} via PSR-4.
 * Tidak boleh ada require_once app_path('Support/...') tersisa dan folder
 * app/Support hanya boleh berisi CooperativeAccess.php.
 */
#[Group('smoke')]
class LegacyShadowInventoryTest extends SmokeTestCase
{
    public function test_shadowed_legacy_module_file_count_does_not_increase(): void
    {
        $shadowed = $this->shadowedModuleFiles();

        $baseline = 0;

        $this->assertLessThanOrEqual(
            $baseline,
            $shadowed->count(),
            "Jumlah file legacy yang membayangi route Laravel naik menjadi {$shadowed->count()} (baseline {$baseline}).\n"
            .'File yang masih membayangi: '.$shadowed->implode(', ')
        );
    }

    public function test_notifications_batch_is_archived(): void
    {
        foreach ([
            'notifications/index.php',
            'notifications/fetch.php',
            'notifications/mark_all_read.php',
            'notifications/read.php',
        ] as $relativePath) {
            $this->assertFileDoesNotExist(
                $this->legacyFile($relativePath),
                "{$relativePath} kembali muncul di modules/. Route Laravel akan terbayangi lagi."
            );
        }
    }

    public function test_notifications_archive_files_exist(): void
    {
        foreach ([
            'notifications_index.php',
            'notifications_fetch.php',
            'notifications_mark_all_read.php',
            'notifications_read.php',
        ] as $file) {
            $this->assertFileExists(base_path('storage/archive-legacy/'.$file));
        }
    }

    public function test_profile_batch_is_archived(): void
    {
        foreach ([
            'profile/index.php',
            'profile/process_profile.php',
            'profile/process_password.php',
            'profile/process_email_request.php',
            'profile/process_email_verify.php',
            'profile/verify_email_change.php',
        ] as $relativePath) {
            $this->assertFileDoesNotExist(
                $this->legacyFile($relativePath),
                "{$relativePath} kembali muncul di modules/. Route Laravel akan terbayangi lagi."
            );
        }
    }

    public function test_profile_archive_files_exist(): void
    {
        foreach ([
            'profile_index.php',
            'profile_process_profile.php',
            'profile_process_password.php',
            'profile_process_email_request.php',
            'profile_process_email_verify.php',
            'profile_verify_email_change.php',
        ] as $file) {
            $this->assertFileExists(base_path('storage/archive-legacy/'.$file));
        }
    }

    public function test_admin_batch_is_archived(): void
    {
        foreach ([
            'admin/dashboard.php',
            'admin/operational_dashboard.php',
            'admin/reporting.php',
            'admin/system_health.php',
            'admin/system_settings.php',
            'admin/system_access/ajax_user_detail.php',
            'admin/system_access/audit_log.php',
            'admin/system_access/menu_management.php',
            'admin/system_access/permissions.php',
            'admin/system_access/role_menu.php',
            'admin/system_access/roles.php',
            'admin/system_access/user_list_active.php',
            'admin/system_access/user_role.php',
            'admin/system_access/users.php',
        ] as $relativePath) {
            $this->assertFileDoesNotExist(
                $this->legacyFile($relativePath),
                "{$relativePath} kembali muncul di modules/. Route Laravel akan terbayangi lagi."
            );
        }
    }

    public function test_admin_archive_files_exist(): void
    {
        foreach ([
            'admin_dashboard.php',
            'admin_operational_dashboard.php',
            'admin_reporting.php',
            'admin_system_health.php',
            'admin_system_settings.php',
            'admin_system_access_ajax_user_detail.php',
            'admin_system_access_audit_log.php',
            'admin_system_access_menu_management.php',
            'admin_system_access_permissions.php',
            'admin_system_access_role_menu.php',
            'admin_system_access_roles.php',
            'admin_system_access_user_list_active.php',
            'admin_system_access_user_role.php',
            'admin_system_access_users.php',
        ] as $file) {
            $this->assertFileExists(base_path('storage/archive-legacy/'.$file));
        }
    }

    public function test_root_php_entrypoints_are_laravel_wrappers(): void
    {
        foreach (['index.php', 'dashboard.php'] as $file) {
            $contents = file_get_contents(base_path($file)) ?: '';
            $this->assertStringContainsString("require __DIR__.'/public/index.php';", $contents);
            $this->assertStringNotContainsString("require_once __DIR__.'/config.php'", $contents);
        }

        foreach (['config_debug.php', 'deploy_check.php'] as $file) {
            $this->assertFileDoesNotExist(base_path($file), "Root debug/deploy script {$file} kembali muncul di web root.");
        }

        $this->assertFileExists(base_path('tools/config_debug.php'));
        $this->assertFileExists(base_path('tools/deploy_check.php'));
        $this->assertFileExists(base_path('storage/archive-legacy/root_index.php'));
        $this->assertFileExists(base_path('storage/archive-legacy/root_dashboard.php'));
        $this->assertFileExists(base_path('storage/archive-legacy/root_deploy_check.php'));
    }

    public function test_root_config_php_is_archived(): void
    {
        $this->assertFileDoesNotExist(base_path('config.php'), 'Legacy root config.php kembali muncul di web root.');
        $this->assertFileExists(base_path('storage/archive-legacy/root_config.php'));

        $tool = file_get_contents(base_path('tools/config_debug.php')) ?: '';
        $this->assertStringContainsString('bootstrap/app.php', $tool);
        $this->assertStringNotContainsString("require __DIR__.'/config.php'", $tool);
        $this->assertStringNotContainsString("require_once __DIR__.'/config.php'", $tool);
    }

    public function test_root_nisnlib_is_replaced_by_namespaced_class(): void
    {
        $this->assertFileDoesNotExist(base_path('nisnlib.php'), 'Legacy root nisnlib.php kembali muncul di web root.');
        $this->assertFileDoesNotExist(app_path('Support/nisn.php'), 'Support/nisn.php (global) masih ada; harus diganti class App\Support\Legacy\Nisn.');
        $this->assertFileExists(app_path('Support/Legacy/Nisn.php'));

        $class = file_get_contents(app_path('Support/Legacy/Nisn.php')) ?: '';
        $this->assertStringContainsString('namespace App\\Support\\Legacy;', $class);
        $this->assertStringContainsString('class Nisn', $class);
        $this->assertStringContainsString('public static function encrypt', $class);
        $this->assertStringContainsString('public static function decrypt', $class);
        $this->assertStringContainsString('public static function shift', $class);

        $controller = file_get_contents(app_path('Http/Controllers/CbtOps/TestWatchingController.php')) ?: '';
        $this->assertStringContainsString('App\\Support\\Legacy\\Nisn', $controller);
        $this->assertStringNotContainsString("base_path('nisnlib.php')", $controller);
    }

    public function test_legacy_support_classes_are_namespaced_in_legacy(): void
    {
        $expected = [
            'AuditLog.php', 'Notification.php', 'StatusHelper.php', 'ParticipantTableRenderer.php',
            'FtpStorage.php', 'FilingSystem.php', 'FilingSystemRecord.php', 'Monitoring.php',
            'MonitoringController.php', 'TestAdminController.php', 'TestAdmin.php',
            'Nisn.php', 'TadAccess.php', 'ParticipantRecap.php',
        ];

        foreach ($expected as $file) {
            $path = app_path('Support/Legacy/'.$file);
            $this->assertFileExists($path, "Class legacy {$file} harus berada di app/Support/Legacy.");
            $contents = file_get_contents($path) ?: '';
            $this->assertStringContainsString('namespace App\\Support\\Legacy;', $contents, "{$file} kehilangan namespace.");
        }

        $this->assertFileDoesNotExist(app_path('Support/Legacy/CooperativeAccess.php'), 'CooperativeAccess bukan legacy; jangan pindahkan.');
    }

    public function test_support_root_only_has_non_legacy_helper(): void
    {
        $files = glob(app_path('Support/*.php')) ?: [];
        $names = array_map(fn ($f) => basename($f), $files);
        sort($names);

        $this->assertSame(
            ['CooperativeAccess.php'],
            $names,
            'app/Support hanya boleh berisi CooperativeAccess.php; class global harus pindah ke Support/Legacy.'
        );
    }

    public function test_no_support_require_once_remains_in_app(): void
    {
        $recursive = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($recursive as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname()) ?: '';
            $this->assertStringNotContainsString("app_path('Support/", $contents, 'Sisa require_once app_path(\'Support/...\') di '.$file->getPathname());
            $this->assertStringNotContainsString("BASE_PATH.'/app/Support/", $contents, 'Sisa require_once BASE_PATH app/Support di '.$file->getPathname());
        }
    }

    public function test_legacy_function_files_were_converted_to_classes(): void
    {
        $this->assertFileDoesNotExist(app_path('Support/tad_access.php'), 'tad_access.php (global) harus diganti TadAccess class.');
        $this->assertFileDoesNotExist(app_path('Support/tad_participant_recap.php'), 'tad_participant_recap.php (global) harus diganti ParticipantRecap class.');
        $this->assertFileDoesNotExist(app_path('Support/audit_helper.php'), 'audit_helper.php (global) harus diganti AuditLog::logAudit.');

        $tad = file_get_contents(app_path('Support/Legacy/TadAccess.php')) ?: '';
        $this->assertStringContainsString('class TadAccess', $tad);
        $this->assertStringContainsString('public static function userHasRole', $tad);

        $recap = file_get_contents(app_path('Support/Legacy/ParticipantRecap.php')) ?: '';
        $this->assertStringContainsString('class ParticipantRecap', $recap);
        $this->assertStringContainsString('public static function getRecap', $recap);

        $audit = file_get_contents(app_path('Support/Legacy/AuditLog.php')) ?: '';
        $this->assertStringContainsString('public static function logAudit', $audit);
    }

    public function test_mail_is_laravel_backed(): void
    {
        $this->assertFileExists(app_path('Services/MailService.php'));
        $this->assertFileDoesNotExist(app_path('Services/LegacyMailService.php'), 'LegacyMailService (PHPMailer) harus dihapus.');

        $composer = file_get_contents(base_path('composer.json')) ?: '';
        $this->assertStringNotContainsString('phpmailer', strtolower($composer));
    }

    public function test_legacy_login_php_view_is_archived(): void
    {
        $this->assertFileDoesNotExist(base_path('views/login.php'), 'Legacy views/login.php kembali muncul; login aktif harus memakai resources/views/auth/login.blade.php.');
        $this->assertFileExists(base_path('storage/archive-legacy/root_views_login.php'));
        $this->assertFileExists(resource_path('views/auth/login.blade.php'));
    }

    public function test_cbt_ops_light_batch_is_archived(): void
    {
        foreach ([
            'cbt_ops/test_plan/index.php',
            'cbt_ops/test_plan/create.php',
            'cbt_ops/test_plan/edit.php',
            'cbt_ops/test_plan/delete.php',
            'cbt_ops/test_admin/index.php',
            'cbt_ops/test_admin/participant_recap_print.php',
        ] as $relativePath) {
            $this->assertFileDoesNotExist(
                $this->legacyFile($relativePath),
                "{$relativePath} kembali muncul di modules/. Route Laravel akan terbayangi lagi."
            );
        }
    }

    public function test_cbt_ops_light_archive_files_exist(): void
    {
        foreach ([
            'cbt_ops_test_plan_index.php',
            'cbt_ops_test_plan_create.php',
            'cbt_ops_test_plan_edit.php',
            'cbt_ops_test_plan_delete.php',
            'cbt_ops_test_admin_index.php',
            'cbt_ops_test_admin_participant_recap_print.php',
        ] as $file) {
            $this->assertFileExists(base_path('storage/archive-legacy/'.$file));
        }
    }

    public function test_cbt_ops_test_watching_small_batch_is_archived(): void
    {
        foreach ([
            'cbt_ops/test_watching/participant_photo.php',
            'cbt_ops/test_watching/timer_control.php',
            'cbt_ops/test_watching/timer_control_room.php',
            'cbt_ops/test_watching/outbound_receiver.php',
            'cbt_ops/test_watching/crc_receiver.php',
            'cbt_ops/test_watching/upload_crc.php',
            'cbt_ops/test_watching/generate_crc.php',
        ] as $relativePath) {
            $this->assertFileDoesNotExist(
                $this->legacyFile($relativePath),
                "{$relativePath} kembali muncul di modules/. Route Laravel akan terbayangi lagi."
            );
        }
    }

    public function test_cbt_ops_test_watching_small_archive_files_exist(): void
    {
        foreach ([
            'cbt_ops_test_watching_participant_photo.php',
            'cbt_ops_test_watching_timer_control.php',
            'cbt_ops_test_watching_timer_control_room.php',
            'cbt_ops_test_watching_outbound_receiver.php',
            'cbt_ops_test_watching_crc_receiver.php',
            'cbt_ops_test_watching_upload_crc.php',
            'cbt_ops_test_watching_generate_crc.php',
        ] as $file) {
            $this->assertFileExists(base_path('storage/archive-legacy/'.$file));
        }
    }

    public function test_cbt_ops_monitoring_batch_is_archived(): void
    {
        foreach ([
            'cbt_ops/test_watching/monitoring.php',
            'cbt_ops/test_watching/monitoring_hybrid.php',
        ] as $relativePath) {
            $this->assertFileDoesNotExist(
                $this->legacyFile($relativePath),
                "{$relativePath} kembali muncul di modules/. Route Laravel akan terbayangi lagi."
            );
        }
    }

    public function test_cbt_ops_monitoring_archive_files_exist(): void
    {
        foreach ([
            'cbt_ops_test_watching_monitoring.php',
            'cbt_ops_test_watching_monitoring_hybrid.php',
        ] as $file) {
            $this->assertFileExists(base_path('storage/archive-legacy/'.$file));
        }
    }

    public function test_filing_system_endpoint_batch_is_archived(): void
    {
        foreach ([
            'cbt_ops/filing_system/upload.php',
            'cbt_ops/filing_system/action.php',
            'cbt_ops/filing_system/http_upload_receiver.php',
            'cbt_ops/filing_system/crc_b2_receiver.php',
        ] as $relativePath) {
            $this->assertFileDoesNotExist(
                $this->legacyFile($relativePath),
                "{$relativePath} kembali muncul di modules/. Route Laravel akan terbayangi lagi."
            );
        }
    }

    public function test_filing_system_endpoint_archive_files_exist(): void
    {
        foreach ([
            'filing_system_upload.php',
            'filing_system_action.php',
            'filing_system_http_upload_receiver.php',
            'filing_system_crc_b2_receiver.php',
        ] as $file) {
            $this->assertFileExists(base_path('storage/archive-legacy/'.$file));
        }
    }

    public function test_filing_system_partial_views_are_blade_partials(): void
    {
        foreach ([
            'upload_modal.php' => 'upload-modal.blade.php',
            'share_modal.php' => 'share-modal.blade.php',
            'edit_metadata_modal.php' => 'edit-metadata-modal.blade.php',
            'permission_modal.php' => 'permission-modal.blade.php',
            'info_drawer.php' => 'info-drawer.blade.php',
            'zip_inspection_modal.php' => 'zip-inspection-modal.blade.php',
        ] as $legacyFile => $bladeFile) {
            $this->assertFileDoesNotExist(
                $this->legacyFile('cbt_ops/filing_system/views/'.$legacyFile),
                "Partial legacy {$legacyFile} kembali muncul di modules/cbt_ops/filing_system/views."
            );

            $this->assertFileExists(
                resource_path('views/filing-system/partials/'.$bladeFile),
                "Blade partial {$bladeFile} tidak ditemukan."
            );
        }
    }

    public function test_filing_system_migrated_services_are_in_app_services(): void
    {
        foreach ([
            'ZipValidationService.php',
            'ZipInspectionService.php',
            'ShareCodeService.php',
            'FilingPermissionService.php',
            'FilingStorageService.php',
            'FilingExpiryService.php',
            'FilingAdminService.php',
        ] as $file) {
            $this->assertFileDoesNotExist(
                $this->legacyFile('cbt_ops/filing_system/services/'.$file),
                "Service {$file} kembali muncul di modules/cbt_ops/filing_system/services."
            );

            $this->assertFileExists(
                app_path('Services/FilingSystem/'.$file),
                "Service Laravel app/Services/FilingSystem/{$file} tidak ditemukan."
            );
        }
    }

    public function test_filing_system_repositories_are_in_app_repositories(): void
    {
        foreach ([
            'FilingSystem.php',
            'FilingShare.php',
            'FilingAudit.php',
            'FilingAccess.php',
            'FileSystemDrive.php',
        ] as $file) {
            $this->assertFileDoesNotExist(
                $this->legacyFile('cbt_ops/filing_system/models/'.$file),
                "Repository {$file} kembali muncul di modules/cbt_ops/filing_system/models."
            );

            $this->assertFileExists(
                app_path('Repositories/FilingSystem/'.$file),
                "Repository Laravel app/Repositories/FilingSystem/{$file} tidak ditemukan."
            );
        }
    }

    public function test_filing_drive_controller_is_in_laravel_controller_namespace(): void
    {
        $this->assertFileDoesNotExist(
            $this->legacyFile('cbt_ops/filing_system/controllers/FilingDriveController.php'),
            'FilingDriveController.php kembali muncul di modules/cbt_ops/filing_system/controllers.'
        );

        $this->assertFileExists(
            app_path('Http/Controllers/CbtOps/FilingDriveController.php'),
            'Controller Laravel app/Http/Controllers/CbtOps/FilingDriveController.php tidak ditemukan.'
        );
    }

    public function test_filing_expiry_cron_is_artisan_command_backed(): void
    {
        $commandPath = app_path('Console/Commands/ProcessExpiredFilingFiles.php');
        $toolPath = base_path('tools/process_expired_filing_files.php');

        $this->assertFileExists(
            $commandPath,
            'Artisan command app/Console/Commands/ProcessExpiredFilingFiles.php tidak ditemukan.'
        );

        $this->assertFileDoesNotExist(
            $this->legacyFile('cbt_ops/filing_system/cron/process_expired_files.php'),
            'Wrapper cron legacy kembali muncul di modules/. Gunakan tools/process_expired_filing_files.php atau php artisan filing:process-expired.'
        );

        $this->assertFileExists(
            $toolPath,
            'Tool cron tools/process_expired_filing_files.php tidak ditemukan.'
        );

        $wrapper = file_get_contents($toolPath) ?: '';
        $this->assertStringContainsString('filing:process-expired', $wrapper);
        $this->assertStringContainsString('bootstrap/app.php', $wrapper);
        $this->assertStringNotContainsString('new FilingExpiryService', $wrapper);
    }

    public function test_modules_directory_is_empty(): void
    {
        $modulesPath = base_path('modules');

        $files = [];
        if (is_dir($modulesPath)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($modulesPath, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile()) {
                    $files[] = str_replace('\\', '/', $file->getPathname());
                }
            }
        }
        sort($files);

        $this->assertSame([], $files, 'modules/ harus kosong; route compatibility sekarang explicit Laravel routes dan cron pindah ke tools/.');
        $this->assertFileDoesNotExist(base_path('modules/cbt_ops/filing_system/filing-system-migration-plan.md'));
        $this->assertFileExists(base_path('docs/filing-system-migration-plan.md'));
    }

    public function test_legacy_module_file_fallback_controller_is_archived(): void
    {
        $this->assertFileDoesNotExist(
            app_path('Http/Controllers/LegacyModuleController.php'),
            'LegacyModuleController kembali muncul; file fisik modules/* tidak boleh dieksekusi lewat fallback.'
        );
        $this->assertFileExists(base_path('storage/archive-legacy/app_http_controllers_LegacyModuleController.php'));

        $webRoutes = file_get_contents(base_path('routes/web.php')) ?: '';
        $this->assertStringNotContainsString('LegacyModuleController', $webRoutes);
        $this->assertStringNotContainsString('/modules/{path}', $webRoutes);
        $this->assertStringNotContainsString('legacy.modules', $webRoutes);
    }

    public function test_test_document_default_data_uses_laravel_service(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/CbtOps/FilingSystemController.php')) ?: '';
        $service = file_get_contents(app_path('Services/FilingSystem/TestDocumentDataService.php')) ?: '';

        $this->assertStringContainsString('TestDocumentDataService', $controller);
        $this->assertStringNotContainsString('needsLegacyTestDocumentAction', $controller);
        $this->assertStringNotContainsString('loadLegacyTestDocumentActionDependencies', $controller);
        $this->assertStringNotContainsString("base_path('controllers/FilingSystemController.php')", $controller);
        $this->assertStringNotContainsString('$legacyController->handle()', $controller);
        $this->assertFileExists(app_path('Services/FilingSystem/TestDocumentDataService.php'));
        $this->assertStringContainsString('fetchReceiverFileList', $service);
        $this->assertStringContainsString('getOutboundFilesForFiling', $service);
        $this->assertStringContainsString('getCrcRawFilesForFiling', $service);
        $this->assertStringContainsString('getLiveFinishedParticipants', $service);
        $this->assertStringContainsString('adminInfo', $service);
        $this->assertStringContainsString('entry', $service);
        $this->assertStringContainsString('debugOutbound', $service);
        $this->assertStringContainsString('issueCsv', $service);
        $this->assertStringContainsString('crcRawDownloadUrl', $service);
        $this->assertStringContainsString('outboundDownload', $service);
        $this->assertStringContainsString('filingFileDownload', $service);
        $this->assertStringContainsString('adminFolderDownload', $service);
        $this->assertStringContainsString('filingAdminNo', $service);
        $this->assertStringContainsString('saveInput', $service);
        $this->assertStringContainsString('deleteEntry', $service);
        $this->assertStringContainsString('fileAction', $service);
        $this->assertStringContainsString('uploadFileAction', $service);
        $this->assertStringNotContainsString("require_once base_path('nisnlib.php')", $service);

        $crcB2Service = file_get_contents(base_path('app/Services/FilingSystem/TestDocumentCrcB2Service.php'));
        $this->assertStringContainsString('downloadZipByAdmin', $crcB2Service);
        $this->assertStringContainsString('collect', $crcB2Service);
    }

    public function test_berita_acara_endpoint_is_archived(): void
    {
        $this->assertFileDoesNotExist(
            $this->legacyFile('cbt_ops/filing_system/berita_acara.php'),
            'berita_acara.php kembali muncul di modules/. Route Laravel akan terbayangi lagi.'
        );

        $this->assertFileDoesNotExist(
            base_path('controllers/FilingSystemController.php'),
            'Root legacy controllers/FilingSystemController.php kembali muncul setelah berita acara dipindah penuh ke Laravel.'
        );

        $this->assertFileExists(
            base_path('storage/archive-legacy/filing_system_berita_acara.php'),
            'Arsip berita_acara.php tidak ditemukan.'
        );

        $this->assertFileExists(
            base_path('storage/archive-legacy/controllers_FilingSystemController.php'),
            'Arsip root FilingSystemController legacy tidak ditemukan.'
        );
    }

    private function shadowedModuleFiles(): Collection
    {
        return collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri) => str_starts_with($uri, 'modules/') && str_ends_with($uri, '.php'))
            ->unique()
            ->filter(fn (string $uri) => is_file($this->legacyFile(substr($uri, strlen('modules/')))))
            ->values();
    }
}
