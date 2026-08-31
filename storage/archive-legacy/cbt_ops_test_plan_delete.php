<?php

require_once dirname(__DIR__, 3).'/config.php';
require_once BASE_PATH.'/includes/tad_access.php';
require_once BASE_PATH.'/models/AuditLog.php';
require_once BASE_PATH.'/includes/audit_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$canManageSupervisor = canManageTadSupervisor($pdo_run, (int) ($_SESSION['user_id'] ?? 0));
if (! $canManageSupervisor) {
    http_response_code(403);
    exit('Anda tidak memiliki izin untuk menghapus data TAD/SPV.');
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id <= 0) {
    $_SESSION['error'] = 'Data TAD/SPV tidak valid.';
    header('Location: index.php');
    exit;
}

try {
    $pdo_run->beginTransaction();

    $stmtTad = $pdo_run->prepare('SELECT rec_id, itc_usr_id, spv_name, spv_alias, captain, status, photo_path FROM tad_supervisor WHERE rec_id = ? LIMIT 1');
    $stmtTad->execute([$id]);
    $tad = $stmtTad->fetch(PDO::FETCH_ASSOC);

    if (! $tad) {
        throw new Exception('Data TAD/SPV tidak ditemukan.');
    }

    $pdo_run->prepare('DELETE FROM tad_rekening WHERE tad_id = ?')->execute([$id]);
    $pdo_run->prepare('DELETE FROM tad_supervisor WHERE rec_id = ?')->execute([$id]);

    $pdo_run->commit();

    logAudit($pdo_run, 'TEST_PLAN_DELETE', 'tad_supervisor', $id, [
        'deleted_by_user_id' => (int) ($_SESSION['user_id'] ?? 0),
        'deleted_by_name' => $_SESSION['account_nm'] ?? $_SESSION['username'] ?? null,
        'tad_id' => $id,
        'itc_user_id' => isset($tad['itc_usr_id']) ? (int) $tad['itc_usr_id'] : null,
        'tad_name' => $tad['spv_name'] ?? null,
        'tad_alias' => $tad['spv_alias'] ?? null,
        'type' => ! empty($tad['captain']) ? 'CAP' : 'SPV',
        'status' => isset($tad['status']) ? (int) $tad['status'] : null,
    ]);

    $photoPath = (string) ($tad['photo_path'] ?? '');
    if (substr($photoPath, 0, strlen('storage/photos/tad/')) === 'storage/photos/tad/') {
        $fullPhotoPath = BASE_PATH.'/'.$photoPath;
        if (is_file($fullPhotoPath)) {
            unlink($fullPhotoPath);
        }
    }

    $_SESSION['success'] = 'Data TAD ['.($tad['spv_name'] ?? $id).'] berhasil dihapus.';
} catch (Throwable $e) {
    if ($pdo_run->inTransaction()) {
        $pdo_run->rollBack();
    }
    $_SESSION['error'] = 'Gagal menghapus data TAD/SPV: '.$e->getMessage();
}

header('Location: index.php');
exit;
