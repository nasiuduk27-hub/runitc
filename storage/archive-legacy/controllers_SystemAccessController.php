<?php

// File: controllers/SystemAccessController.php

class SystemAccessController
{
    private SystemAccess $model;

    private PDO $pdoRun;

    public function __construct(PDO $pdoRun)
    {
        $this->pdoRun = $pdoRun;
        $this->model = new SystemAccess($pdoRun);
        require_once BASE_PATH.'/includes/audit_helper.php';
    }

    public function getModel(): SystemAccess
    {
        return $this->model;
    }

    public function handleAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['action'])) {
            return;
        }

        Csrf::verify();

        $action = $_POST['action'];

        try {
            match ($action) {
                'create_role' => $this->createRole(),
                'update_role' => $this->updateRole(),
                'delete_role' => $this->deleteRole(),
                'save_menu_access' => $this->saveMenuAccess(),
                'assign_user_role' => $this->assignUserRole(),
                'remove_user_role' => $this->removeUserRole(),
                'update_user_status' => $this->updateUserStatus(),
                'reset_password' => $this->resetPassword(),
                'delete_user' => $this->deleteUser(),
                'add_menu' => $this->addMenu(),
                'update_menu' => $this->updateMenu(),
                'delete_menu' => $this->deleteMenu(),
                default => null,
            };
        } catch (Exception $e) {
            $_SESSION['error'] = 'Gagal: '.$e->getMessage();
        }

        $this->redirectBack();
    }

    private function createRole(): void
    {
        $grpaccess = trim($_POST['grpaccess'] ?? '');
        $grpacc = trim($_POST['grpacc'] ?? '');
        $grpdesc = trim($_POST['grpdesc'] ?? '');

        if ($grpaccess === '' || $grpacc === '' || $grpdesc === '') {
            $_SESSION['error'] = 'Semua field role wajib diisi.';

            return;
        }

        $this->model->createRole([
            'grpaccess' => $grpaccess,
            'grpacc' => $grpacc,
            'grpdesc' => $grpdesc,
        ]);

        logAudit($this->pdoRun, 'ROLE_CREATED', 'sysitc_grpacc', null, [
            'grpaccess' => $grpaccess,
            'grpacc' => $grpacc,
            'grpdesc' => $grpdesc,
        ]);

        $_SESSION['success'] = 'Role berhasil ditambahkan.';
    }

    private function updateRole(): void
    {
        $id = (int) ($_POST['role_id'] ?? 0);
        $grpdesc = trim($_POST['grpdesc'] ?? '');

        if ($id <= 0 || $grpdesc === '') {
            $_SESSION['error'] = 'Data role tidak valid.';

            return;
        }

        $oldRole = $this->model->getRoleById($id);
        $this->model->updateRole($id, ['grpdesc' => $grpdesc]);

        logAudit($this->pdoRun, 'ROLE_UPDATED', 'sysitc_grpacc', $id, [
            'old_desc' => $oldRole['grpdesc'] ?? '',
            'new_desc' => $grpdesc,
        ]);

        $_SESSION['success'] = 'Role berhasil diperbarui.';
    }

    private function deleteRole(): void
    {
        $id = (int) ($_POST['role_id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['error'] = 'ID role tidak valid.';

            return;
        }

        $role = $this->model->getRoleById($id);
        $deleted = $this->model->deleteRole($id);

        if ($deleted) {
            logAudit($this->pdoRun, 'ROLE_DELETED', 'sysitc_grpacc', $id, [
                'grpaccess' => $role['grpaccess'] ?? '',
                'grpacc' => $role['grpacc'] ?? '',
                'grpdesc' => $role['grpdesc'] ?? '',
            ]);
            $_SESSION['success'] = 'Role berhasil dihapus.';
        } else {
            $_SESSION['error'] = 'Role tidak bisa dihapus karena masih digunakan oleh user atau memiliki mapping menu.';
        }
    }

    private function saveMenuAccess(): void
    {
        $grpaccId = (int) ($_POST['grpacc_id'] ?? 0);
        $menuIds = $_POST['menu_ids'] ?? [];

        if ($grpaccId <= 0) {
            $_SESSION['error'] = 'Role tidak valid.';

            return;
        }

        // Ensure parent menus are included
        $menuIds = $this->model->ensureParentMenuIds($menuIds);

        $this->model->syncRoleMenuAccess($grpaccId, $menuIds);

        logAudit($this->pdoRun, 'MENU_ACCESS_UPDATED', 'sys_menu_access', $grpaccId, [
            'menu_ids' => $menuIds,
        ]);

        $_SESSION['success'] = 'Akses menu berhasil disimpan.';
    }

    private function assignUserRole(): void
    {
        $userRecId = (int) ($_POST['user_rec_id'] ?? 0);
        $grpaccId = (int) ($_POST['grpacc_id'] ?? 0);

        if ($userRecId <= 0 || $grpaccId <= 0) {
            $_SESSION['error'] = 'Data user atau role tidak valid.';

            return;
        }

        $role = $this->model->getRoleById($grpaccId);
        $this->model->assignUserRole($userRecId, $grpaccId);

        logAudit($this->pdoRun, 'USER_ROLE_ASSIGNED', 'sysitc_usracc', $userRecId, [
            'role_id' => $grpaccId,
            'grpaccess' => $role['grpaccess'] ?? '',
            'grpacc' => $role['grpacc'] ?? '',
            'grpdesc' => $role['grpdesc'] ?? '',
        ]);

        $_SESSION['success'] = 'Role user berhasil diperbarui.';
    }

    private function removeUserRole(): void
    {
        $userRecId = (int) ($_POST['user_rec_id'] ?? 0);
        $accessCode = trim($_POST['access_code'] ?? '');
        $accessAccount = trim($_POST['access_account'] ?? '');

        if ($userRecId <= 0 || $accessCode === '' || $accessAccount === '') {
            $_SESSION['error'] = 'Data tidak valid.';

            return;
        }

        $this->model->removeUserRole($userRecId, $accessCode, $accessAccount);

        logAudit($this->pdoRun, 'USER_ROLE_REMOVED', 'sysitc_usracc', $userRecId, [
            'access_code' => $accessCode,
            'access_account' => $accessAccount,
        ]);

        $_SESSION['success'] = 'Role berhasil dihapus dari user.';
    }

    private function updateUserStatus(): void
    {
        $userRecId = (int) ($_POST['user_rec_id'] ?? 0);
        $status = (int) ($_POST['status'] ?? -1);

        if ($userRecId <= 0 || ! in_array($status, [0, 1], true)) {
            $_SESSION['error'] = 'Data status user tidak valid.';

            return;
        }

        $this->model->updateUserStatus($userRecId, $status);

        logAudit($this->pdoRun, $status === 1 ? 'USER_ACTIVATED' : 'USER_DEACTIVATED', 'sysitc_users', $userRecId, [
            'new_status' => $status,
        ]);

        $_SESSION['success'] = $status === 1
            ? 'User berhasil diaktifkan.'
            : 'User berhasil dinonaktifkan.';
    }

    private function resetPassword(): void
    {
        $userRecId = (int) ($_POST['user_rec_id'] ?? 0);

        if ($userRecId <= 0) {
            $_SESSION['error'] = 'ID user tidak valid.';

            return;
        }

        $tempPassword = $this->model->resetUserPassword($userRecId);

        logAudit($this->pdoRun, 'USER_PASSWORD_RESET', 'sysitc_users', $userRecId, [
            'reset_by' => (int) ($_SESSION['user_id'] ?? 0),
        ]);

        $_SESSION['success'] = 'Password berhasil di-reset. Password sementara: <strong>'.htmlspecialchars($tempPassword).'</strong>';
    }

    private function deleteUser(): void
    {
        $userRecId = (int) ($_POST['user_rec_id'] ?? 0);

        if ($userRecId <= 0) {
            $_SESSION['error'] = 'ID user tidak valid.';

            return;
        }

        $detail = $this->model->getUserDetail($userRecId);
        $this->model->deleteUser($userRecId);

        logAudit($this->pdoRun, 'USER_DELETED', 'sysitc_users', $userRecId, [
            'account_nm' => $detail['account_nm'] ?? '',
            'account_id' => $detail['account_id'] ?? '',
        ]);

        $_SESSION['success'] = 'User berhasil dihapus.';
    }

    private function addMenu(): void
    {
        $this->model->addMenu($_POST);

        logAudit($this->pdoRun, 'MENU_CREATED', 'sys_menus', 0, [
            'title' => $_POST['title'] ?? '',
        ]);

        $_SESSION['success'] = 'Menu berhasil ditambahkan.';
    }

    private function updateMenu(): void
    {
        $id = (int) ($_POST['rec_id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = 'ID menu tidak valid.';

            return;
        }

        $this->model->updateMenu($id, $_POST);

        logAudit($this->pdoRun, 'MENU_UPDATED', 'sys_menus', $id, [
            'title' => $_POST['title'] ?? '',
        ]);

        $_SESSION['success'] = 'Menu berhasil diperbarui.';
    }

    private function deleteMenu(): void
    {
        $id = (int) ($_POST['rec_id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = 'ID menu tidak valid.';

            return;
        }

        $detail = $this->model->getMenuById($id);
        $this->model->deleteMenu($id);

        logAudit($this->pdoRun, 'MENU_DELETED', 'sys_menus', $id, [
            'title' => $detail['title'] ?? '',
        ]);

        $_SESSION['success'] = 'Menu berhasil dihapus.';
    }

    private function redirectBack(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? $_SERVER['REQUEST_URI'];
        header('Location: '.$referer);
        exit;
    }
}
