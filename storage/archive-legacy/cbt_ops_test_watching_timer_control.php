<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 3).'/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

if (! $pdo_war) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database WAR tidak tersedia']);
    exit;
}

$action = $_POST['action'] ?? '';
$userId = $_POST['user_id'] ?? '';
$itcUserId = (int) ($_SESSION['user_id'] ?? 0);

if ($itcUserId <= 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sesi login tidak valid']);
    exit;
}

if ($userId === '') {
    echo json_encode(['success' => false, 'message' => 'User tidak valid']);
    exit;
}

$stmt = $pdo_war->prepare('SELECT rec_id, admin_id, sub_adm_id, remindtm, statrec, ke_suspend FROM t3sTt4keR5 WHERE authorize = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (! $user) {
    echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
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
$stmtAccess->execute([(int) $user['sub_adm_id'], (int) $user['admin_id'], $spvRecId]);

if ((int) $stmtAccess->fetchColumn() <= 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Anda tidak ditugaskan untuk mengontrol peserta ini']);
    exit;
}

try {
    switch ($action) {
        case 'play':
            // LOGIC LAMA: Start hanya mengubah peserta Readiness/statrec 2 ke 3.
            $stmt = $pdo_war->prepare("UPDATE t3sTt4keR5 SET statrec='3', lupdt=NOW() WHERE authorize=? AND statrec='2'");
            $stmt->execute([$userId]);
            break;

        case 'pause':
            // LOGIC LAMA: Pause/Suspend adalah toggle ke_suspend.
            // Jika 0 menjadi 1, jika 1 menjadi 0. Tombol Resume di monitoring tetap memanggil action pause.
            $stmt = $pdo_war->prepare('UPDATE t3sTt4keR5 SET ke_suspend = CASE WHEN ke_suspend = 1 THEN 0 ELSE 1 END, lupdt=NOW() WHERE authorize=?');
            $stmt->execute([$userId]);
            break;

        case 'stop':
            // Terminate = statrec 7 + ke_suspend 1.
            // statrec 7 tanpa ke_suspend 1 tetap berarti End of Test normal.
            $stmt = $pdo_war->prepare("UPDATE t3sTt4keR5 SET statrec='7', ke_suspend=1, lupdt=NOW() WHERE authorize=?");
            $stmt->execute([$userId]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action tidak dikenal']);
            exit;
    }

    $stmt = $pdo_war->prepare('SELECT authorize, statrec, ke_suspend, remindtm FROM t3sTt4keR5 WHERE authorize = ?');
    $stmt->execute([$userId]);
    $latest = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    echo json_encode(['success' => true, 'data' => $latest]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
