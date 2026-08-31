<?php

use App\Http\Controllers\Admin\ActiveUserController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MenuManagementController;
use App\Http\Controllers\Admin\OperationalDashboardController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ReportingController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\RoleMenuController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\UserRoleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('legacy.auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/system-access/active-users', [ActiveUserController::class, 'index'])->name('active-users.index');
    Route::post('/system-access/active-users/status', [ActiveUserController::class, 'updateStatus'])->name('active-users.status');
    Route::get('/system-access/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::post('/system-access/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::post('/system-access/roles/update', [RoleController::class, 'update'])->name('roles.update');
    Route::post('/system-access/roles/delete', [RoleController::class, 'destroy'])->name('roles.destroy');
    Route::get('/system-access/role-menu', [RoleMenuController::class, 'index'])->name('role-menu.index');
    Route::post('/system-access/role-menu', [RoleMenuController::class, 'save'])->name('role-menu.save');
    Route::get('/system-access/menu-management', [MenuManagementController::class, 'index'])->name('menu-management.index');
    Route::post('/system-access/menu-management', [MenuManagementController::class, 'store'])->name('menu-management.store');
    Route::post('/system-access/menu-management/reorder', [MenuManagementController::class, 'reorder'])->name('menu-management.reorder');
    Route::post('/system-access/menu-management/update', [MenuManagementController::class, 'update'])->name('menu-management.update');
    Route::post('/system-access/menu-management/delete', [MenuManagementController::class, 'destroy'])->name('menu-management.destroy');
    Route::get('/system-access/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
    Route::get('/system-access/permissions', [PermissionController::class, 'index'])->name('permissions.index');
    Route::get('/system-settings', [SystemSettingsController::class, 'index'])->name('system-settings.index');
    Route::post('/system-settings/api', [SystemSettingsController::class, 'api'])->name('system-settings.api');
    Route::get('/system-health', [SystemHealthController::class, 'index'])->name('system-health.index');
    Route::get('/system-health/api', [SystemHealthController::class, 'api'])->name('system-health.api');
    Route::get('/reporting', [ReportingController::class, 'index'])->name('reporting.index');
    Route::get('/operational-dashboard', [OperationalDashboardController::class, 'index'])->name('operational-dashboard.index');
    Route::get('/operational-dashboard/api', [OperationalDashboardController::class, 'api'])->name('operational-dashboard.api');
    Route::get('/system-access/user-role', [UserRoleController::class, 'index'])->name('user-role.index');
    Route::post('/system-access/user-role/assign', [UserRoleController::class, 'assign'])->name('user-role.assign');
    Route::post('/system-access/user-role/remove', [UserRoleController::class, 'remove'])->name('user-role.remove');
    Route::get('/system-access/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::get('/system-access/users/detail', [UserManagementController::class, 'detail'])->name('users.detail');
    Route::post('/system-access/users/status', [UserManagementController::class, 'updateStatus'])->name('users.status');
    Route::post('/system-access/users/reset-password', [UserManagementController::class, 'resetPassword'])->name('users.reset-password');
    Route::post('/system-access/users/delete', [UserManagementController::class, 'destroy'])->name('users.destroy');
});

Route::middleware('legacy.auth')->group(function () {
    Route::get('/modules/admin/dashboard.php', DashboardController::class);
    Route::get('/modules/admin/system_access/user_list_active.php', [ActiveUserController::class, 'index']);
    Route::post('/modules/admin/system_access/user_list_active.php', [ActiveUserController::class, 'updateStatus']);
    Route::get('/modules/admin/system_access/roles.php', [RoleController::class, 'index']);
    Route::post('/modules/admin/system_access/roles.php', [RoleController::class, 'legacyAction']);
    Route::get('/modules/admin/system_access/role_menu.php', [RoleMenuController::class, 'index']);
    Route::post('/modules/admin/system_access/role_menu.php', [RoleMenuController::class, 'save']);
    Route::get('/modules/admin/system_access/menu_management.php', [MenuManagementController::class, 'index']);
    Route::post('/modules/admin/system_access/menu_management.php', [MenuManagementController::class, 'legacyAction']);
    Route::get('/modules/admin/system_access/audit_log.php', [AuditLogController::class, 'index']);
    Route::get('/modules/admin/system_access/permissions.php', [PermissionController::class, 'index']);
    Route::get('/modules/admin/system_settings.php', [SystemSettingsController::class, 'index']);
    Route::post('/modules/admin/system_settings.php', [SystemSettingsController::class, 'api']);
    Route::get('/modules/admin/system_health.php', [SystemHealthController::class, 'legacy']);
    Route::get('/modules/admin/reporting.php', [ReportingController::class, 'index']);
    Route::get('/modules/admin/operational_dashboard.php', [OperationalDashboardController::class, 'legacy']);
    Route::get('/modules/admin/system_access/user_role.php', [UserRoleController::class, 'index']);
    Route::post('/modules/admin/system_access/user_role.php', function (Request $request, UserRoleController $controller) {
        return $request->input('action') === 'remove_user_role'
            ? $controller->remove($request)
            : $controller->assign($request);
    });
    Route::get('/modules/admin/system_access/users.php', [UserManagementController::class, 'index']);
    Route::post('/modules/admin/system_access/users.php', [UserManagementController::class, 'legacyAction']);
    Route::get('/modules/admin/system_access/ajax_user_detail.php', [UserManagementController::class, 'detail']);
});
