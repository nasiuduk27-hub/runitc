<?php

namespace App\Support\Legacy;

class TestAdmin
{
    private \PDO $pdo;

    private \PDO $pdoRun;

    private \PDO $pdoWar;

    public function __construct(\PDO $pdo, \PDO $pdoRun, \PDO $pdoWar)
    {
        $this->pdo = $pdo;
        $this->pdoRun = $pdoRun;
        $this->pdoWar = $pdoWar;
    }

    public function getSupervisors(): array
    {
        $this->syncTadRoleUsersToSupervisors();

        $stmt = $this->pdoRun->query('
            SELECT 
                ts.rec_id,
                ts.itc_usr_id,
                ts.spv_name,
                ts.spv_alias,
                ts.captain
            FROM tad_supervisor ts
            WHERE ts.status = 1 
            ORDER BY ts.captain DESC, ts.spv_name ASC
        ');

        $supervisors = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $userIds = array_values(array_unique(array_filter(array_map('intval', array_column($supervisors, 'itc_usr_id')))));
        $userMap = $this->getTadUserMap($userIds);

        foreach ($supervisors as &$supervisor) {
            $userId = (int) ($supervisor['itc_usr_id'] ?? 0);
            $user = $userMap[$userId] ?? [];

            $supervisor['account_nm'] = $user['account_nm'] ?? null;
            $supervisor['account_id'] = $user['account_id'] ?? null;
            $supervisor['tad_roles'] = $user['tad_roles'] ?? null;
        }
        unset($supervisor);

        return $supervisors;
    }

    private function syncTadRoleUsersToSupervisors(): void
    {
        $stmt = $this->pdoRun->query("
            SELECT DISTINCT
                u.rec_id,
                u.account_nm,
                u.alias_nm,
                l.account_id,
                l.email_id
            FROM sysitc_users u
            JOIN sysitc_login l ON l.rec_id = u.login_rec_id
            JOIN sysitc_usracc ua ON ua.user_rec_id = u.rec_id
            JOIN sysitc_grpacc g ON g.grpaccess = ua.access_code AND g.grpacc = ua.access_account
            WHERE u.status = 1
            AND (
                UPPER(g.grpdesc) LIKE '%TAD%ADMIN%'
                OR UPPER(g.grpdesc) LIKE '%TAD%STAFF%'
                OR UPPER(g.grpdesc) LIKE '%TAD%SPV%'
                OR UPPER(g.grpdesc) = 'SUPER ADMIN'
            )
            ORDER BY u.account_nm ASC
        ");

        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($users)) {
            return;
        }

        $stmtExists = $this->pdoRun->prepare('SELECT rec_id FROM tad_supervisor WHERE itc_usr_id = ? LIMIT 1');
        $stmtInsert = $this->pdoRun->prepare('
            INSERT INTO tad_supervisor
            (itc_usr_id, spv_name, spv_alias, email, captain, status, entdt, lupd)
            VALUES (?, ?, ?, ?, 0, 1, NOW(), NOW())
        ');

        foreach ($users as $user) {
            $userId = (int) $user['rec_id'];
            $stmtExists->execute([$userId]);

            if ($stmtExists->fetchColumn()) {
                continue;
            }

            $name = strtoupper(trim((string) ($user['account_nm'] ?: $user['account_id'])));
            $alias = strtoupper(trim((string) ($user['alias_nm'] ?: $user['account_id'])));
            $email = strtolower(trim((string) ($user['email_id'] ?? '')));

            $stmtInsert->execute([$userId, $name, $alias, $email]);
        }
    }

    private function getTadUserMap(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $this->pdoRun->prepare("
            SELECT
                u.rec_id,
                u.account_nm,
                l.account_id,
                GROUP_CONCAT(DISTINCT g.grpdesc ORDER BY g.grpdesc SEPARATOR ', ') AS tad_roles
            FROM sysitc_users u
            JOIN sysitc_login l ON l.rec_id = u.login_rec_id
            LEFT JOIN sysitc_usracc ua ON ua.user_rec_id = u.rec_id
            LEFT JOIN sysitc_grpacc g ON g.grpaccess = ua.access_code AND g.grpacc = ua.access_account
                AND (
                    UPPER(g.grpdesc) LIKE '%TAD%ADMIN%'
                    OR UPPER(g.grpdesc) LIKE '%TAD%STAFF%'
                    OR UPPER(g.grpdesc) LIKE '%TAD%SPV%'
                    OR UPPER(g.grpdesc) = 'SUPER ADMIN'
                )
            WHERE u.rec_id IN ({$placeholders})
            GROUP BY u.rec_id, u.account_nm, l.account_id
        ");

        $stmt->execute($userIds);

        $userMap = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $user) {
            $userMap[(int) $user['rec_id']] = $user;
        }

        return $userMap;
    }

    public function getActiveDatesJson(?int $spvId = null): string
    {
        $params = [];
        $spvWhere = '';

        if ($spvId !== null) {
            if ($spvId <= 0) {
                return json_encode([]);
            }

            $spvWhere = '
                AND EXISTS (
                    SELECT 1
                    FROM t3sT5ub4dm1n sub
                    WHERE sub.admin_id = t3sTAdm1n.rec_id
                    AND sub.spv_recid = ?
                )
            ';
            $params[] = $spvId;
        }

        $stmt = $this->pdoWar->prepare("
            SELECT DISTINCT DATE(testdt)
            FROM t3sTAdm1n
            WHERE statrec = '0'
            AND testdt IS NOT NULL
            {$spvWhere}
        ");

        $stmt->execute($params);

        return json_encode($stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function getClientIdsByName(string $search): array
    {
        $stmt = $this->pdo->prepare('
            SELECT rec_id 
            FROM sys_mstclient 
            WHERE clientnm LIKE ?
        ');

        $stmt->execute(["%{$search}%"]);

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function countTestAdmins(array $whereClauses, array $params): int
    {
        $whereSql = 'WHERE '.implode(' AND ', $whereClauses);

        $stmt = $this->pdoWar->prepare("
            SELECT COUNT(a.rec_id) 
            FROM t3sTAdm1n a 
            {$whereSql}
        ");

        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function getTestAdmins(array $whereClauses, array $params, int $limit, int $offset): array
    {
        $whereSql = 'WHERE '.implode(' AND ', $whereClauses);

        $stmt = $this->pdoWar->prepare("
            SELECT a.* 
            FROM t3sTAdm1n a 
            {$whereSql}
            ORDER BY a.testdt DESC, a.rec_id DESC 
            LIMIT {$limit} OFFSET {$offset}
        ");

        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getClientMap(array $clientIds): array
    {
        if (empty($clientIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($clientIds), '?'));

        $stmt = $this->pdo->prepare("
            SELECT rec_id, clientnm 
            FROM sys_mstclient 
            WHERE rec_id IN ({$placeholders})
        ");

        $stmt->execute(array_values($clientIds));

        $clientMap = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $clientMap[$row['rec_id']] = $row['clientnm'];
        }

        return $clientMap;
    }

    public function getTotalTakers(int $adminId): int
    {
        $stmt = $this->pdoWar->prepare('
            SELECT COUNT(*) 
            FROM t3sTt4keR5 
            WHERE admin_id = ?
        ');

        $stmt->execute([$adminId]);

        return (int) $stmt->fetchColumn();
    }

    public function getAssignedTakers(int $adminId): int
    {
        $stmt = $this->pdoWar->prepare('
            SELECT COUNT(*) 
            FROM t3sTt4keR5 
            WHERE admin_id = ? 
            AND sub_adm_id != 0
        ');

        $stmt->execute([$adminId]);

        return (int) $stmt->fetchColumn();
    }

    public function getFinishedTakers(int $adminId): int
    {
        $stmt = $this->pdoWar->prepare("
            SELECT COUNT(*)
            FROM t3sTt4keR5
            WHERE admin_id = ?
            AND sub_adm_id != 0
            AND statrec IN ('7','8','9','c','C')
        ");

        $stmt->execute([$adminId]);

        return (int) $stmt->fetchColumn();
    }

    public function getTotalIssuesByAdmin(int $adminId): int
    {
        $stmt = $this->pdoWar->prepare('
            SELECT COUNT(n.rec_id) 
            FROM t3sTkNotes n 
            JOIN t3sTt4keR5 t ON n.ttaker_id = t.rec_id 
            WHERE t.admin_id = ?
        ');

        $stmt->execute([$adminId]);

        return (int) $stmt->fetchColumn();
    }

    public function getBatchesByAdmin(int $adminId): array
    {
        $stmt = $this->pdoWar->prepare('
            SELECT * 
            FROM t3sT5ub4dm1n 
            WHERE admin_id = ? 
            ORDER BY batch_no ASC
        ');

        $stmt->execute([$adminId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTotalIssuesByBatch(int $batchId): int
    {
        $stmt = $this->pdoWar->prepare('
            SELECT COUNT(n.rec_id) 
            FROM t3sTkNotes n 
            JOIN t3sTt4keR5 t ON n.ttaker_id = t.rec_id 
            WHERE t.sub_adm_id = ?
        ');

        $stmt->execute([$batchId]);

        return (int) $stmt->fetchColumn();
    }

    public function getSupervisorById(int $spvId): ?array
    {
        $stmt = $this->pdoRun->prepare('
            SELECT rec_id, itc_usr_id, spv_name, LEFT(spv_alias, 5) AS spv_alias, email 
            FROM tad_supervisor 
            WHERE rec_id = ?
        ');

        $stmt->execute([$spvId]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function assignBatch(int $adminId, int $spvId, int $amountToAssign, int $userId): int
    {
        if ($amountToAssign <= 0) {
            throw new \Exception('Jumlah peserta harus lebih dari 0.');
        }

        $spvData = $this->getSupervisorById($spvId);
        $spvAlias = $spvData['spv_alias'] ?? '';
        $spvEmail = $spvData['email'] ?? '';

        $this->pdoWar->beginTransaction();

        try {
            $stmtTakers = $this->pdoWar->prepare('
                SELECT rec_id 
                FROM t3sTt4keR5 
                WHERE admin_id = ? 
                AND sub_adm_id = 0 
                ORDER BY rec_id ASC 
                LIMIT ?
            ');

            $stmtTakers->bindValue(1, $adminId, \PDO::PARAM_INT);
            $stmtTakers->bindValue(2, $amountToAssign, \PDO::PARAM_INT);
            $stmtTakers->execute();

            $unassignedTakers = $stmtTakers->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($unassignedTakers)) {
                throw new \Exception('Semua peserta sudah dialokasikan atau tidak ada data.');
            }

            $stmtExisting = $this->pdoWar->prepare('
                SELECT rec_id, batch_no, authorize_amt
                FROM t3sT5ub4dm1n
                WHERE admin_id = ?
                  AND spv_recid = ?
                ORDER BY rec_id ASC
                LIMIT 1
            ');
            $stmtExisting->execute([$adminId, $spvId]);
            $existingSubAdmin = $stmtExisting->fetch(\PDO::FETCH_ASSOC);

            if ($existingSubAdmin) {
                $currentSubAdminId = (int) $existingSubAdmin['rec_id'];
                $currentAmt = (int) $existingSubAdmin['authorize_amt'];
                $batchNoForAdmin = $existingSubAdmin['batch_no'];
            } else {
                $stmtLast = $this->pdoWar->prepare('
                SELECT batch_no
                FROM t3sT5ub4dm1n 
                WHERE admin_id = ? 
                ORDER BY rec_id DESC 
                LIMIT 1
                ');

                $stmtLast->execute([$adminId]);
                $lastSubAdmin = $stmtLast->fetch(\PDO::FETCH_ASSOC);

                $currentBatchNo = $lastSubAdmin ? $lastSubAdmin['batch_no'] : null;
                $newBatchNo = ! $currentBatchNo ? 'A' : ++$currentBatchNo;

                $stmtInsert = $this->pdoWar->prepare('
                INSERT INTO t3sT5ub4dm1n 
                (
                    admin_id, batch_no, spv_recid, spv_alias, 
                    authorize_amt, smartCEmail, creator_id, lupdt
                ) 
                VALUES (?, ?, ?, ?, 0, ?, ?, NOW())
                ');

                $stmtInsert->execute([
                    $adminId,
                    $newBatchNo,
                    $spvId,
                    $spvAlias,
                    $spvEmail,
                    $userId,
                ]);

                $currentSubAdminId = (int) $this->pdoWar->lastInsertId();
                $currentAmt = 0;
                $batchNoForAdmin = $newBatchNo;
            }

            $this->pdoWar->prepare('
                UPDATE t3sTAdm1n 
                SET last_batchno = ?, spv_recid = ?, lupdt = NOW() 
                WHERE rec_id = ?
            ')->execute([$batchNoForAdmin, $spvId, $adminId]);

            foreach ($unassignedTakers as $taker) {
                $currentAmt++;

                $this->pdoWar->prepare("
                    UPDATE t3sTt4keR5 
                    SET sub_adm_id = ?,
                        statrec = CASE WHEN statrec IN ('0', '1', '2', '') OR statrec IS NULL THEN '2' ELSE statrec END,
                        lupdt = NOW() 
                    WHERE rec_id = ?
                ")->execute([$currentSubAdminId, $taker['rec_id']]);
            }

            $this->pdoWar->prepare('
                UPDATE t3sT5ub4dm1n 
                SET authorize_amt = ?, lupdt = NOW() 
                WHERE rec_id = ?
            ')->execute([$currentAmt, $currentSubAdminId]);

            $this->refreshSubadmStatus($adminId);

            $this->pdoWar->commit();

            $this->syncFilingRecordForBatch($adminId, $currentSubAdminId, $spvData, $userId);

            try {
                $this->logDistributionAudit('TEST_ADMIN_DISTRIBUTE', $adminId, $currentSubAdminId, [
                    'distributed_by_user_id' => $userId,
                    'distributed_by_name' => $this->getUserName($userId),
                    'admin_id' => $adminId,
                    'batch_id' => $currentSubAdminId,
                    'batch_no' => $batchNoForAdmin,
                    'spv_recid' => $spvId,
                    'recipient_user_id' => isset($spvData['itc_usr_id']) ? (int) $spvData['itc_usr_id'] : null,
                    'recipient_name' => $spvData['spv_name'] ?? null,
                    'assigned_amount' => count($unassignedTakers),
                    'total_authorize_amount' => $currentAmt,
                ]);
            } catch (\Throwable $e) {
                error_log('Distribution audit failed: '.$e->getMessage());
            }

            $this->notifySupervisorAssignment($adminId, $spvData, count($unassignedTakers), $userId, 'assigned');

            return count($unassignedTakers);

        } catch (\Throwable $e) {
            if ($this->pdoWar->inTransaction()) {
                $this->pdoWar->rollBack();
            }
            throw $e;
        }
    }

    public function updateBatch(int $batchId, int $spvId, int $newAmount): void
    {
        $spvData = $this->getSupervisorById($spvId);
        $spvAlias = $spvData['spv_alias'] ?? '';
        $spvEmail = $spvData['email'] ?? '';

        $this->pdoWar->beginTransaction();

        try {
            $stmtBatch = $this->pdoWar->prepare('
                SELECT admin_id, authorize_amt, batch_no, spv_recid 
                FROM t3sT5ub4dm1n 
                WHERE rec_id = ?
            ');

            $stmtBatch->execute([$batchId]);
            $batch = $stmtBatch->fetch(\PDO::FETCH_ASSOC);

            if (! $batch) {
                throw new \Exception('Batch tidak ditemukan.');
            }

            $adminId = (int) $batch['admin_id'];
            $currentAmt = (int) $batch['authorize_amt'];
            $oldSpvId = (int) $batch['spv_recid'];
            $oldSpvData = $oldSpvId > 0 ? $this->getSupervisorById($oldSpvId) : null;
            $diff = $newAmount - $currentAmt;

            if ($newAmount === 0) {
                $this->pdoWar->prepare("
                    UPDATE t3sTt4keR5 
                    SET sub_adm_id = 0,
                        statrec = CASE WHEN statrec IN ('0', '1', '2', '') OR statrec IS NULL THEN '0' ELSE statrec END,
                        lupdt = NOW() 
                    WHERE sub_adm_id = ?
                ")->execute([$batchId]);

                $this->pdoWar->prepare('
                    DELETE FROM t3sT5ub4dm1n 
                    WHERE rec_id = ?
                ')->execute([$batchId]);

                $this->refreshSubadmStatus($adminId);
                $this->pdoWar->commit();

                try {
                    $this->logDistributionAudit('TEST_ADMIN_DISTRIBUTION_DELETE', $adminId, $batchId, [
                        'deleted_by_user_id' => (int) ($_SESSION['user_id'] ?? 0),
                        'deleted_by_name' => $this->getUserName((int) ($_SESSION['user_id'] ?? 0)),
                        'admin_id' => $adminId,
                        'batch_id' => $batchId,
                        'batch_no' => $batch['batch_no'] ?? null,
                        'spv_recid' => $oldSpvId,
                        'recipient_user_id' => isset($oldSpvData['itc_usr_id']) ? (int) $oldSpvData['itc_usr_id'] : null,
                        'recipient_name' => $oldSpvData['spv_name'] ?? null,
                        'old_amount' => $currentAmt,
                        'new_amount' => 0,
                    ]);
                } catch (\Throwable $e) {
                    error_log('Distribution audit failed: '.$e->getMessage());
                }

                $this->notifySupervisorAssignment($adminId, $oldSpvData, 0, (int) ($_SESSION['user_id'] ?? 0), 'removed');

                return;
            }

            if ($diff > 0) {
                $stmtTakers = $this->pdoWar->prepare('
                    SELECT rec_id 
                    FROM t3sTt4keR5 
                    WHERE admin_id = ? 
                    AND sub_adm_id = 0 
                    ORDER BY rec_id ASC 
                    LIMIT ?
                ');

                $stmtTakers->bindValue(1, $adminId, \PDO::PARAM_INT);
                $stmtTakers->bindValue(2, $diff, \PDO::PARAM_INT);
                $stmtTakers->execute();

                $unassignedTakers = $stmtTakers->fetchAll(\PDO::FETCH_ASSOC);

                if (count($unassignedTakers) < $diff) {
                    throw new \Exception('Sisa kuota peserta tidak mencukupi.');
                }

                foreach ($unassignedTakers as $taker) {
                    $this->pdoWar->prepare("
                        UPDATE t3sTt4keR5 
                        SET sub_adm_id = ?,
                            statrec = CASE WHEN statrec IN ('0', '1', '2', '') OR statrec IS NULL THEN '2' ELSE statrec END,
                            lupdt = NOW() 
                        WHERE rec_id = ?
                    ")->execute([$batchId, $taker['rec_id']]);
                }
            }

            if ($diff < 0) {
                $reduceAmount = abs($diff);

                $stmtTakers = $this->pdoWar->prepare('
                    SELECT rec_id 
                    FROM t3sTt4keR5 
                    WHERE sub_adm_id = ? 
                    ORDER BY CAST(detshf AS UNSIGNED) DESC, rec_id DESC 
                    LIMIT ?
                ');

                $stmtTakers->bindValue(1, $batchId, \PDO::PARAM_INT);
                $stmtTakers->bindValue(2, $reduceAmount, \PDO::PARAM_INT);
                $stmtTakers->execute();

                $takersToRemove = $stmtTakers->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($takersToRemove as $taker) {
                    $this->pdoWar->prepare("
                        UPDATE t3sTt4keR5 
                        SET sub_adm_id = 0,
                            statrec = CASE WHEN statrec IN ('0', '1', '2', '') OR statrec IS NULL THEN '0' ELSE statrec END,
                            lupdt = NOW() 
                        WHERE rec_id = ?
                    ")->execute([$taker['rec_id']]);
                }
            }

            $this->pdoWar->prepare('
                UPDATE t3sT5ub4dm1n 
                SET spv_recid = ?, spv_alias = ?, smartCEmail = ?, authorize_amt = ?, lupdt = NOW() 
                WHERE rec_id = ?
            ')->execute([$spvId, $spvAlias, $spvEmail, $newAmount, $batchId]);

            $this->pdoWar->prepare('
                UPDATE t3sTAdm1n 
                SET spv_recid = ?, lupdt = NOW() 
                WHERE rec_id = ?
            ')->execute([$spvId, $adminId]);

            $this->refreshSubadmStatus($adminId);

            $this->pdoWar->commit();

            $this->syncFilingRecordForBatch($adminId, $batchId, $spvData, (int) ($_SESSION['user_id'] ?? 0));

            try {
                $this->logDistributionAudit('TEST_ADMIN_DISTRIBUTION_UPDATE', $adminId, $batchId, [
                    'updated_by_user_id' => (int) ($_SESSION['user_id'] ?? 0),
                    'updated_by_name' => $this->getUserName((int) ($_SESSION['user_id'] ?? 0)),
                    'admin_id' => $adminId,
                    'batch_id' => $batchId,
                    'batch_no' => $batch['batch_no'] ?? null,
                    'old_spv_recid' => $oldSpvId,
                    'old_recipient_user_id' => isset($oldSpvData['itc_usr_id']) ? (int) $oldSpvData['itc_usr_id'] : null,
                    'old_recipient_name' => $oldSpvData['spv_name'] ?? null,
                    'new_spv_recid' => $spvId,
                    'new_recipient_user_id' => isset($spvData['itc_usr_id']) ? (int) $spvData['itc_usr_id'] : null,
                    'new_recipient_name' => $spvData['spv_name'] ?? null,
                    'old_amount' => $currentAmt,
                    'new_amount' => $newAmount,
                ]);
            } catch (\Throwable $e) {
                error_log('Distribution audit failed: '.$e->getMessage());
            }

            $this->notifySupervisorAssignment($adminId, $spvData, $newAmount, (int) ($_SESSION['user_id'] ?? 0), 'updated');

            if ($oldSpvId > 0 && $oldSpvId !== $spvId) {
                $this->notifySupervisorAssignment($adminId, $oldSpvData, 0, (int) ($_SESSION['user_id'] ?? 0), 'reassigned');
            }

        } catch (\Throwable $e) {
            if ($this->pdoWar->inTransaction()) {
                $this->pdoWar->rollBack();
            }
            throw $e;
        }
    }

    public function deleteDistribution(int $adminId, int $userId = 0): void
    {
        $stmtAssignedSpv = $this->pdoWar->prepare('
            SELECT DISTINCT spv_recid
            FROM t3sT5ub4dm1n
            WHERE admin_id = ?
              AND spv_recid > 0
        ');
        $stmtAssignedSpv->execute([$adminId]);
        $assignedSpvIds = array_map('intval', $stmtAssignedSpv->fetchAll(\PDO::FETCH_COLUMN));

        $this->pdoWar->prepare("
            UPDATE t3sTt4keR5 
            SET sub_adm_id = 0,
                statrec = CASE WHEN statrec IN ('0', '1', '2', '') OR statrec IS NULL THEN '0' ELSE statrec END
            WHERE admin_id = ?
        ")->execute([$adminId]);

        $this->pdoWar->prepare('
            DELETE FROM t3sT5ub4dm1n 
            WHERE admin_id = ?
        ')->execute([$adminId]);

        $this->pdoWar->prepare("
            UPDATE t3sTAdm1n 
            SET subadm_cd = '0' 
            WHERE rec_id = ?
        ")->execute([$adminId]);

        foreach ($assignedSpvIds as $assignedSpvId) {
            $this->notifySupervisorAssignment($adminId, $this->getSupervisorById($assignedSpvId), 0, $userId, 'removed');
        }

        $recipients = [];
        foreach ($assignedSpvIds as $assignedSpvId) {
            $spv = $this->getSupervisorById($assignedSpvId);
            $recipients[] = [
                'spv_recid' => $assignedSpvId,
                'recipient_user_id' => isset($spv['itc_usr_id']) ? (int) $spv['itc_usr_id'] : null,
                'recipient_name' => $spv['spv_name'] ?? null,
            ];
        }

        try {
            $this->logDistributionAudit('TEST_ADMIN_DISTRIBUTION_DELETE', $adminId, null, [
                'deleted_by_user_id' => $userId,
                'deleted_by_name' => $this->getUserName($userId),
                'admin_id' => $adminId,
                'recipients' => $recipients,
            ]);
        } catch (\Throwable $e) {
            error_log('Distribution audit failed: '.$e->getMessage());
        }
    }

    private function refreshSubadmStatus(int $adminId): void
    {
        $stmt = $this->pdoWar->prepare('
            SELECT 
                COUNT(*) AS total, 
                SUM(CASE WHEN sub_adm_id != 0 THEN 1 ELSE 0 END) AS assigned 
            FROM t3sTt4keR5 
            WHERE admin_id = ?
        ');

        $stmt->execute([$adminId]);
        $statusData = $stmt->fetch(\PDO::FETCH_ASSOC);

        $total = (int) ($statusData['total'] ?? 0);
        $assigned = (int) ($statusData['assigned'] ?? 0);

        $newSubadmCd = ($total > 0 && $total === $assigned) ? '1' : '0';

        $this->pdoWar->prepare('
            UPDATE t3sTAdm1n 
            SET subadm_cd = ? 
            WHERE rec_id = ?
        ')->execute([$newSubadmCd, $adminId]);
    }

    private function getAdminSummary(int $adminId): array
    {
        $stmt = $this->pdoWar->prepare('
            SELECT admin_no, testdt, client_id
            FROM t3sTAdm1n
            WHERE rec_id = ?
            LIMIT 1
        ');
        $stmt->execute([$adminId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    private function syncFilingRecordForBatch(int $adminId, int $subAdminId, ?array $spvData, int $userId): void
    {
        if ($adminId <= 0 || $subAdminId <= 0) {
            return;
        }

        try {
            $admin = $this->getAdminSummary($adminId);
            $adminNo = trim((string) ($admin['admin_no'] ?? ''));

            if ($adminNo === '') {
                return;
            }

            $subAdminKey = (string) $subAdminId;
            $spvName = $spvData['spv_name'] ?? '-';
            $tanggal = ! empty($admin['testdt']) ? date('Y-m-d', strtotime((string) $admin['testdt'])) : date('Y-m-d');
            $inputBy = $this->getUserName($userId) ?? 'System Distribution';

            $findStmt = $this->pdoRun->prepare('
                SELECT rec_id
                FROM runit_filing_system
                WHERE nomor_admin = ?
                ORDER BY rec_id DESC
                LIMIT 1
            ');
            $findStmt->execute([$adminNo]);
            $filingId = $findStmt->fetchColumn();

            if ($filingId) {
                $stmt = $this->pdoRun->prepare('
                    UPDATE runit_filing_system
                    SET nomor_admin = ?,
                        sub_admin_id = ?,
                        admin_id = ?,
                        tanggal = ?,
                        input_by = ?,
                        spv_name = ?
                    WHERE rec_id = ?
                ');
                $stmt->execute([$adminNo, $subAdminKey, $adminId, $tanggal, $inputBy, $spvName, $filingId]);

                return;
            }

            $stmt = $this->pdoRun->prepare('
                INSERT INTO runit_filing_system
                (
                    nomor_admin,
                    sub_admin_id,
                    admin_id,
                    tanggal,
                    keterangan,
                    input_by,
                    spv_name
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');

            $stmt->execute([
                $adminNo,
                $subAdminKey,
                $adminId,
                $tanggal,
                '',
                $inputBy,
                $spvName,
            ]);
        } catch (\Throwable $e) {
            error_log('[FILING_ASSIGNMENT_SYNC_ERROR] '.$e->getMessage());
        }
    }

    private function getUserName(int $userId): ?string
    {
        if ($userId <= 0) {
            return null;
        }

        $stmt = $this->pdoRun->prepare('SELECT account_nm FROM sysitc_users WHERE rec_id = ? LIMIT 1');
        $stmt->execute([$userId]);

        $name = $stmt->fetchColumn();

        return $name !== false ? (string) $name : null;
    }

    private function logDistributionAudit(string $action, int $adminId, ?int $targetId, array $metadata): void
    {
        $admin = $this->getAdminSummary($adminId);

        AuditLog::logAudit($this->pdoRun, $action, 't3sTAdm1n', $targetId ?? $adminId, array_merge([
            'admin_no' => $admin['admin_no'] ?? null,
            'test_date' => $admin['testdt'] ?? null,
        ], $metadata));
    }

    private function notifySupervisorAssignment(int $adminId, ?array $spvData, int $amount, int $senderUserId, string $action): void
    {
        $recipientUserId = (int) ($spvData['itc_usr_id'] ?? 0);

        if ($recipientUserId <= 0) {
            return;
        }

        $admin = $this->getAdminSummary($adminId);
        $adminNo = trim((string) ($admin['admin_no'] ?? 'Admin #'.$adminId));
        $testDate = ! empty($admin['testdt']) ? date('d M Y', strtotime((string) $admin['testdt'])) : null;
        $dateText = $testDate ? " untuk tanggal {$testDate}" : '';
        $baseUrl = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') : '';
        $targetUrl = $baseUrl.'/cbt-ops/test-admin?search='.urlencode($adminNo);

        if ($action === 'removed') {
            $title = 'Penugasan SPV Dihapus';
            $message = "Tugas Anda pada admin {$adminNo}{$dateText} telah dihapus dari distribusi.";
        } elseif ($action === 'reassigned') {
            $title = 'Penugasan SPV Dialihkan';
            $message = "Tugas Anda pada admin {$adminNo}{$dateText} telah dialihkan atau diganti.";
        } elseif ($action === 'updated') {
            $title = 'Penugasan SPV Diperbarui';
            $message = "Kuota tugas Anda pada admin {$adminNo}{$dateText} diperbarui menjadi {$amount} peserta.";
        } else {
            $title = 'Penugasan SPV Baru';
            $message = "Anda ditugaskan menangani admin {$adminNo}{$dateText} sebanyak {$amount} peserta.";
        }

        try {
            (new Notification($this->pdoRun))->create(
                $recipientUserId,
                $senderUserId > 0 ? $senderUserId : null,
                'tad_spv_assignment',
                $title,
                $message,
                $targetUrl,
                't3sTAdm1n',
                $adminId
            );
        } catch (\Throwable $e) {
            error_log('Notification create failed: '.$e->getMessage());
        }
    }
}
