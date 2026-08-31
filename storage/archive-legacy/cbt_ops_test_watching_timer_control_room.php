<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 3).'/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

if (! $pdo_war) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database WAR tidak tersedia']);
    exit;
}

$action = trim((string) ($_POST['action'] ?? ''));
$adminId = (int) ($_POST['admin_rec_id'] ?? 0);
$subAdminId = (int) ($_POST['sub_admin_id'] ?? 0);
$itcUserId = (int) ($_SESSION['user_id'] ?? 0);

if ($itcUserId <= 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sesi login tidak valid']);
    exit;
}

if ($adminId <= 0 || $subAdminId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Data room tidak valid']);
    exit;
}

$stmtSpv = $pdo_run->prepare('
    SELECT rec_id
    FROM tad_supervisor
    WHERE itc_usr_id = ?
    LIMIT 1
');
$stmtSpv->execute([$itcUserId]);
$spvRecId = (int) $stmtSpv->fetchColumn();

if ($spvRecId <= 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses SPV tidak ditemukan']);
    exit;
}

$stmtAccess = $pdo_war->prepare('
    SELECT COUNT(*)
    FROM t3sT5ub4dm1n
    WHERE rec_id = ?
      AND admin_id = ?
      AND spv_recid = ?
');
$stmtAccess->execute([$subAdminId, $adminId, $spvRecId]);

if ((int) $stmtAccess->fetchColumn() <= 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Anda tidak ditugaskan untuk room monitoring ini']);
    exit;
}

try {
    switch ($action) {
        case 'play':
            $stmtCheck = $pdo_war->prepare("
                SELECT
                    COUNT(*) AS total_peserta,
                    SUM(CASE WHEN statrec = '2' THEN 1 ELSE 0 END) AS total_readiness
                FROM t3sTt4keR5
                WHERE admin_id = ?
                  AND sub_adm_id = ?
            ");
            $stmtCheck->execute([$adminId, $subAdminId]);
            $roomStatus = $stmtCheck->fetch(PDO::FETCH_ASSOC) ?: [];

            $totalPeserta = (int) ($roomStatus['total_peserta'] ?? 0);
            $totalReadiness = (int) ($roomStatus['total_readiness'] ?? 0);

            if ($totalPeserta === 0) {
                throw new Exception('Start All gagal: peserta tidak ditemukan.');
            }

            if ($totalReadiness !== $totalPeserta) {
                throw new Exception("Start All gagal: semua peserta harus Readiness. Saat ini {$totalReadiness} dari {$totalPeserta} peserta Readiness.");
            }

            $stmt = $pdo_war->prepare("
                UPDATE t3sTt4keR5
                SET statrec = '3', ke_suspend = 0, lupdt = NOW()
                WHERE admin_id = ?
                  AND sub_adm_id = ?
                  AND statrec = '2'
            ");
            $stmt->execute([$adminId, $subAdminId]);
            break;

        case 'pause':
            $stmt = $pdo_war->prepare("
                UPDATE t3sTt4keR5
                SET ke_suspend = 1, lupdt = NOW()
                WHERE admin_id = ?
                  AND sub_adm_id = ?
                  AND statrec IN ('3', '4', '5', '6')
                  AND ke_suspend != 1
            ");
            $stmt->execute([$adminId, $subAdminId]);
            break;

        case 'resume':
            $stmt = $pdo_war->prepare('
                UPDATE t3sTt4keR5
                SET ke_suspend = 0, lupdt = NOW()
                WHERE admin_id = ?
                  AND sub_adm_id = ?
                  AND ke_suspend = 1
            ');
            $stmt->execute([$adminId, $subAdminId]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action tidak dikenal']);
            exit;
    }

    echo json_encode([
        'success' => true,
        'affected_rows' => $stmt->rowCount(),
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
