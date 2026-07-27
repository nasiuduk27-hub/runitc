<?php

function getTadRoleRows(PDO $pdoRun, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    $stmt = $pdoRun->prepare("
        SELECT g.rec_id, g.grpaccess, g.grpacc, UPPER(g.grpdesc) AS role_name
        FROM sysitc_usracc ua
        JOIN sysitc_grpacc g ON g.grpaccess = ua.access_code AND g.grpacc = ua.access_account
        WHERE ua.user_rec_id = ?
    ");
    $stmt->execute([$userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function userHasTadRole(PDO $pdoRun, int $userId, array $allowedRoles): bool
{
    $roles = getTadRoleRows($pdoRun, $userId);

    foreach ($roles as $role) {
        $roleName = (string) ($role['role_name'] ?? '');

        if ($roleName === 'SUPER ADMIN' && in_array('SUPER ADMIN', $allowedRoles, true)) {
            return true;
        }

        foreach ($allowedRoles as $allowedRole) {
            if ($allowedRole !== 'SUPER ADMIN' && str_contains($roleName, $allowedRole)) {
                return true;
            }
        }
    }

    return false;
}

function canManageTadSupervisor(PDO $pdoRun, int $userId): bool
{
    return userHasTadRole($pdoRun, $userId, ['TAD ADMIN', 'TAD STAFF', 'SUPER ADMIN']);
}

function canManageTadDistribution(PDO $pdoRun, int $userId): bool
{
    return userHasTadRole($pdoRun, $userId, ['TAD ADMIN', 'TAD STAFF', 'SUPER ADMIN']);
}

function getTadSupervisorIdForUser(PDO $pdoRun, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }

    $stmt = $pdoRun->prepare("
        SELECT rec_id
        FROM tad_supervisor
        WHERE itc_usr_id = ? AND status = 1
        LIMIT 1
    ");
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

function ensureUserHasTadSpvRole(PDO $pdoRun, int $userId): void
{
    if ($userId <= 0 || userHasTadRole($pdoRun, $userId, ['TAD ADMIN', 'TAD STAFF', 'TAD SPV', 'SUPER ADMIN'])) {
        return;
    }

    $stmtRole = $pdoRun->query("
        SELECT rec_id, grpaccess, grpacc
        FROM sysitc_grpacc
        WHERE UPPER(grpdesc) LIKE '%TAD%SPV%'
        ORDER BY rec_id ASC
        LIMIT 1
    ");
    $role = $stmtRole->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        throw new Exception('Role TAD SPV belum tersedia di System Access.');
    }

    $stmtExists = $pdoRun->prepare("
        SELECT COUNT(*)
        FROM sysitc_usracc
        WHERE user_rec_id = ? AND access_code = ? AND access_account = ?
    ");
    $stmtExists->execute([$userId, $role['grpaccess'], $role['grpacc']]);

    if ((int) $stmtExists->fetchColumn() > 0) {
        return;
    }

    $stmtInsert = $pdoRun->prepare("
        INSERT INTO sysitc_usracc (user_rec_id, access_code, access_account)
        VALUES (?, ?, ?)
    ");
    $stmtInsert->execute([$userId, $role['grpaccess'], $role['grpacc']]);
}
