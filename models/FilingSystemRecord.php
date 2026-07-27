<?php

class FilingSystemRecord
{
    private PDO $pdo;
    private PDO $pdoRun;
    private PDO $pdoWar;

    public function __construct(PDO $pdo, PDO $pdoRun, PDO $pdoWar)
    {
        $this->pdo = $pdo;
        $this->pdoRun = $pdoRun;
        $this->pdoWar = $pdoWar;
    }

    public function ensureTablesExist(): void
    {
        $this->pdoRun->exec("
            CREATE TABLE IF NOT EXISTS runit_filing_system (
                rec_id INT AUTO_INCREMENT PRIMARY KEY,
                nomor_admin VARCHAR(100),
                sub_admin_id VARCHAR(50),
                admin_id INT,
                tanggal DATE,
                keterangan TEXT,
                input_by VARCHAR(100),
                spv_name VARCHAR(100),
                sesi INT DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdoRun->exec("
            CREATE TABLE IF NOT EXISTS runit_filing_files (
                file_id INT AUTO_INCREMENT PRIMARY KEY,
                filing_id INT,
                file_name VARCHAR(255),
                file_path VARCHAR(255),
                file_type VARCHAR(10),
                uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdoRun->exec("
            CREATE TABLE IF NOT EXISTS runit_filing_issues (
                issue_id INT AUTO_INCREMENT PRIMARY KEY,
                filing_id INT,
                authorize_id VARCHAR(100),
                participant_name VARCHAR(255),
                issue_text TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->addColumnIfNotExists('runit_filing_system', 'sub_admin_id', "ALTER TABLE runit_filing_system ADD COLUMN sub_admin_id VARCHAR(50) AFTER nomor_admin");
        $this->addColumnIfNotExists('runit_filing_system', 'admin_id', "ALTER TABLE runit_filing_system ADD COLUMN admin_id INT AFTER sub_admin_id");
        $this->addColumnIfNotExists('runit_filing_files', 'file_category', "ALTER TABLE runit_filing_files ADD COLUMN file_category VARCHAR(50) AFTER file_type");
    }

    private function addColumnIfNotExists(string $table, string $column, string $alterSql): void
    {
        $stmt = $this->pdoRun->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
        $exists = $stmt->fetch();

        if (!$exists) {
            $this->pdoRun->exec($alterSql);
        }
    }

    public function getSupervisorByUserId(int $userId): ?array
    {
        $stmt = $this->pdoRun->prepare("
            SELECT rec_id, spv_name
            FROM tad_supervisor
            WHERE itc_usr_id = ?
            LIMIT 1
        ");

        $stmt->execute([$userId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function getClientMap(): array
    {
        $clientMap = [];

        $stmt = $this->pdo->query("
            SELECT rec_id, clientnm
            FROM sys_mstclient
        ");

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $client) {
            $clientMap[$client['rec_id']] = $client['clientnm'];
        }

        return $clientMap;
    }

    public function getAdminClientMap(array $clientMap): array
    {
        $adminClientMap = [];

        $stmt = $this->pdoWar->query("
            SELECT admin_no, client_id
            FROM t3sTAdm1n
            GROUP BY admin_no
        ");

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $admin) {
            $adminClientMap[$admin['admin_no']] = $clientMap[$admin['client_id']] ?? '-';
        }

        return $adminClientMap;
    }

    public function getAssignedAdminsBySupervisor(int $spvRecId): array
    {
        $stmt = $this->pdoWar->prepare("
            SELECT 
                s.rec_id AS sub_admin_id,
                a.rec_id AS admin_id,
                a.admin_no,
                a.testdt
            FROM t3sT5ub4dm1n s
            INNER JOIN t3sTAdm1n a ON s.admin_id = a.rec_id
            WHERE s.spv_recid = ?
            ORDER BY a.testdt DESC
        ");

        $stmt->execute([$spvRecId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ensureFilingRecordsFromAssignments(): void
    {
        $assignments = $this->getAllAssignedAdmins();
        if (empty($assignments)) {
            return;
        }

        $spvNames = $this->getSupervisorNamesByIds(array_column($assignments, 'spv_recid'));
        $findStmt = $this->pdoRun->prepare("
            SELECT rec_id
            FROM runit_filing_system
            WHERE nomor_admin = ?
              AND sub_admin_id = ?
            LIMIT 1
        ");
        $insertStmt = $this->pdoRun->prepare("
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
        ");

        foreach ($assignments as $assignment) {
            $adminNo = trim((string) ($assignment['admin_no'] ?? ''));
            $subAdminId = (string) ($assignment['sub_admin_id'] ?? '');

            if ($adminNo === '' || $subAdminId === '') {
                continue;
            }

            $findStmt->execute([$adminNo, $subAdminId]);
            if ($findStmt->fetchColumn()) {
                continue;
            }

            $spvName = $spvNames[(int) ($assignment['spv_recid'] ?? 0)] ?? '-';
            $tanggal = !empty($assignment['testdt']) ? date('Y-m-d', strtotime($assignment['testdt'])) : date('Y-m-d');

            $insertStmt->execute([
                $adminNo,
                $subAdminId,
                (int) ($assignment['admin_id'] ?? 0),
                $tanggal,
                '',
                'System Distribution',
                $spvName,
            ]);
        }
    }

    private function getAllAssignedAdmins(): array
    {
        $stmt = $this->pdoWar->query("
            SELECT
                s.rec_id AS sub_admin_id,
                s.spv_recid,
                s.batch_no,
                s.authorize_amt,
                a.rec_id AS admin_id,
                a.admin_no,
                a.testdt,
                a.client_id
            FROM t3sT5ub4dm1n s
            INNER JOIN t3sTAdm1n a ON s.admin_id = a.rec_id
            WHERE a.admin_no IS NOT NULL
              AND a.admin_no <> ''
            ORDER BY a.testdt DESC, a.admin_no ASC, s.rec_id ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getSupervisorNamesByIds(array $spvIds): array
    {
        $spvIds = array_values(array_unique(array_filter(array_map('intval', $spvIds))));
        if (empty($spvIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($spvIds), '?'));
        $stmt = $this->pdoRun->prepare("
            SELECT rec_id, spv_name
            FROM tad_supervisor
            WHERE rec_id IN ($placeholders)
        ");
        $stmt->execute($spvIds);

        $names = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $names[(int) $row['rec_id']] = $row['spv_name'] ?: '-';
        }

        return $names;
    }

    public function getAdminNoById(int $adminId): ?string
    {
        $stmt = $this->pdoWar->prepare("
            SELECT admin_no
            FROM t3sTAdm1n
            WHERE rec_id = ?
        ");

        $stmt->execute([$adminId]);

        $adminNo = $stmt->fetchColumn();

        return $adminNo ?: null;
    }

    public function saveFiling(array $data): int
    {
        $recId = (int) ($data['rec_id'] ?? 0);

        $this->pdoRun->beginTransaction();

        try {
            if ($recId > 0) {
                $stmt = $this->pdoRun->prepare("
                    UPDATE runit_filing_system
                    SET tanggal = ?, keterangan = ?
                    WHERE rec_id = ?
                ");

                $stmt->execute([
                    $data['tanggal'],
                    $data['keterangan'],
                    $recId
                ]);
            } else {
                $stmt = $this->pdoRun->prepare("
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
                ");

                $stmt->execute([
                    $data['nomor_admin'],
                    $data['sub_admin_id'],
                    $data['admin_id'],
                    $data['tanggal'],
                    $data['keterangan'],
                    $data['input_by'],
                    $data['spv_name']
                ]);

                $recId = (int) $this->pdoRun->lastInsertId();
            }

            $this->deleteIssuesByFilingId($recId);

            if (!empty($data['issues'])) {
                foreach ($data['issues'] as $issue) {
                    $this->insertIssue($recId, $issue);
                }
            }

            $this->pdoRun->commit();

            return $recId;

        } catch (Exception $e) {
            $this->pdoRun->rollBack();
            throw $e;
        }
    }

    private function insertIssue(int $filingId, array $issue): void
    {
        $stmt = $this->pdoRun->prepare("
            INSERT INTO runit_filing_issues
            (
                filing_id,
                authorize_id,
                participant_name,
                issue_text
            )
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $filingId,
            $issue['authorize_id'],
            $issue['participant_name'],
            $issue['issue_text']
        ]);
    }

    public function deleteIssuesByFilingId(int $filingId): void
    {
        $stmt = $this->pdoRun->prepare("
            DELETE FROM runit_filing_issues
            WHERE filing_id = ?
        ");

        $stmt->execute([$filingId]);
    }

    public function getFilingById(int $id): ?array
    {
        $stmt = $this->pdoRun->prepare("
            SELECT *
            FROM runit_filing_system
            WHERE rec_id = ?
        ");

        $stmt->execute([$id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function getEntryWithIssues(int $id): ?array
    {
        $entry = $this->getFilingById($id);

        if (!$entry) {
            return null;
        }

        $entry['issues'] = $this->getIssuesByFilingId($id);

        return $entry;
    }

    public function getIssuesByFilingId(int $filingId): array
    {
        $stmt = $this->pdoRun->prepare("
            SELECT *
            FROM runit_filing_issues
            WHERE filing_id = ?
        ");

        $stmt->execute([$filingId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getIssueAuthorizeIds(int $filingId): array
    {
        $stmt = $this->pdoRun->prepare("
            SELECT authorize_id
            FROM runit_filing_issues
            WHERE filing_id = ?
        ");

        $stmt->execute([$filingId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getFilteredFilingData(array $filters, array $adminClientMap, ?array $spvFilter = null): array
    {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = "(nomor_admin LIKE ? OR keterangan LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['spv'])) {
            $where[] = "spv_name = ?";
            $params[] = $filters['spv'];
        }

        if (!empty($filters['admin'])) {
            $where[] = "nomor_admin = ?";
            $params[] = $filters['admin'];
        }

        if (!empty($filters['date'])) {
            if (strpos($filters['date'], ' to ') !== false) {
                $dates = explode(' to ', $filters['date']);

                $where[] = "tanggal BETWEEN ? AND ?";
                $params[] = trim($dates[0]);
                $params[] = trim($dates[1]);
            } else {
                $where[] = "tanggal = ?";
                $params[] = trim($filters['date']);
            }
        }

        // SPV restriction: only show data uploaded by this SPV or assigned to their exact room.
        if ($spvFilter !== null) {
            $spvConditions = [];
            $spvName = $spvFilter['spv_name'] ?? '';
            $assignedPairs = $spvFilter['assigned_admin_pairs'] ?? [];

            if ($spvName !== '' && $spvName !== '-') {
                $spvConditions[] = "spv_name = ?";
                $params[] = $spvName;
            }

            if (!empty($assignedPairs)) {
                $pairConditions = [];
                foreach ($assignedPairs as $pair) {
                    $adminId = (int) ($pair['admin_id'] ?? 0);
                    $subAdminId = (string) ($pair['sub_admin_id'] ?? '');
                    if ($adminId <= 0 || $subAdminId === '') {
                        continue;
                    }

                    $pairConditions[] = "(admin_id = ? AND sub_admin_id = ?)";
                    $params[] = $adminId;
                    $params[] = $subAdminId;
                }

                if (!empty($pairConditions)) {
                    $spvConditions[] = "(" . implode(" OR ", $pairConditions) . ")";
                }
            }

            if (!empty($spvConditions)) {
                $where[] = "(" . implode(" OR ", $spvConditions) . ")";
            }
        }

        $stmt = $this->pdoRun->prepare("
            SELECT *
            FROM runit_filing_system
            WHERE " . implode(" AND ", $where) . "
            ORDER BY tanggal DESC, rec_id DESC
        ");

        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = [];

        foreach ($rows as $row) {
            $clientName = $adminClientMap[$row['nomor_admin']] ?? '-';

            if (!empty($filters['client']) && $clientName !== $filters['client']) {
                continue;
            }

            $row['client_name'] = $clientName;
            $row['issues_count'] = $this->countIssuesByFilingId((int) $row['rec_id']);
            $row['files'] = $this->getFilesByFilingId((int) $row['rec_id']);

            $data[] = $row;
        }

        return $data;
    }

    public function countIssuesByFilingId(int $filingId): int
    {
        $stmt = $this->pdoRun->prepare("
            SELECT COUNT(*)
            FROM runit_filing_issues
            WHERE filing_id = ?
        ");

        $stmt->execute([$filingId]);

        return (int) $stmt->fetchColumn();
    }

    public function insertFile(array $data): void
    {
        $stmt = $this->pdoRun->prepare("
            INSERT INTO runit_filing_files
            (
                filing_id,
                file_name,
                file_path,
                file_type,
                file_category
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['filing_id'],
            $data['file_name'],
            $data['file_path'],
            $data['file_type'],
            $data['file_category'] ?? ''
        ]);
    }

    public function getFilesByFilingId(int $filingId): array
    {
        $stmt = $this->pdoRun->prepare("
            SELECT file_id, file_name, file_path, file_type, file_category, uploaded_at
            FROM runit_filing_files
            WHERE filing_id = ?
            ORDER BY file_id DESC
        ");

        $stmt->execute([$filingId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFilesByCategory(int $filingId): array
    {
        $stmt = $this->pdoRun->prepare("
            SELECT file_id, file_name, file_path, file_type, file_category, uploaded_at
            FROM runit_filing_files
            WHERE filing_id = ?
            ORDER BY file_category, file_id DESC
        ");

        $stmt->execute([$filingId]);

        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $file) {
            $cat = $file['file_category'] ?: 'uncategorized';
            $grouped[$cat][] = $file;
        }
        return $grouped;
    }

    public function getFileById(int $fileId): ?array
    {
        $stmt = $this->pdoRun->prepare("
            SELECT *
            FROM runit_filing_files
            WHERE file_id = ?
        ");

        $stmt->execute([$fileId]);

        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        return $file ?: null;
    }

    public function deleteFileById(int $fileId): void
    {
        $stmt = $this->pdoRun->prepare("
            DELETE FROM runit_filing_files
            WHERE file_id = ?
        ");

        $stmt->execute([$fileId]);
    }

    public function deleteEntry(int $filingId): array
    {
        $this->pdoRun->beginTransaction();

        try {
            $files = $this->getFilesByFilingId($filingId);

            $this->deleteIssuesByFilingId($filingId);

            $stmtFiles = $this->pdoRun->prepare("
                DELETE FROM runit_filing_files
                WHERE filing_id = ?
            ");

            $stmtFiles->execute([$filingId]);

            $stmtMain = $this->pdoRun->prepare("
                DELETE FROM runit_filing_system
                WHERE rec_id = ?
            ");

            $stmtMain->execute([$filingId]);

            $this->pdoRun->commit();

            return $files;

        } catch (Exception $e) {
            $this->pdoRun->rollBack();
            throw $e;
        }
    }

    public function getAdminById(int $adminId): ?array
    {
        $stmt = $this->pdoWar->prepare("
            SELECT admin_no, testdt, client_id
            FROM t3sTAdm1n
            WHERE rec_id = ?
        ");

        $stmt->execute([$adminId]);

        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        return $admin ?: null;
    }

    public function getAdminIdByAdminNo(string $adminNo): ?int
    {
        $stmt = $this->pdoWar->prepare("
            SELECT rec_id
            FROM t3sTAdm1n
            WHERE admin_no = ?
            LIMIT 1
        ");

        $stmt->execute([$adminNo]);

        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;
    }

    public function getParticipantsBySubAdmin(int $adminId, int $subAdminId): array
    {
        $stmt = $this->pdoWar->prepare("
            SELECT authorize, regnm, statrec
            FROM t3sTt4keR5
            WHERE admin_id = ?
            AND sub_adm_id = ?
        ");

        $stmt->execute([$adminId, $subAdminId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFinishedParticipantsBySubAdmin(int $adminId, int $subAdminId): array
    {
        $stmt = $this->pdoWar->prepare("
            SELECT authorize, regnm, statrec, remindtm, lupdt
            FROM t3sTt4keR5
            WHERE admin_id = ?
            AND sub_adm_id = ?
            AND statrec IN ('7','8','9','c','C')
        ");

        $stmt->execute([$adminId, $subAdminId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
