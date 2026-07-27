<?php

require_once BASE_PATH . '/models/TestAdmin.php';
require_once BASE_PATH . '/includes/tad_access.php';

class TestAdminController
{
    private TestAdmin $model;
    private PDO $pdoRun;
    private PDO $pdoWar;

    public function __construct(PDO $pdo, PDO $pdoRun, PDO $pdoWar)
    {
        $this->model = new TestAdmin($pdo, $pdoRun, $pdoWar);
        $this->pdoRun = $pdoRun;
        $this->pdoWar = $pdoWar;
    }

    public function handle(): array
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $this->handlePost();
        }

        return $this->getPageData();
    }

    private function handlePost(): void
    {
        $action = $_POST['action'] ?? '';
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        if (!canManageTadDistribution($this->pdoRun, $userId)) {
            $_SESSION['error'] = 'Anda tidak memiliki izin untuk mengelola distribusi.';
            $this->redirectBack();
        }

        try {
            if ($action === 'assign_batch') {
                $adminId = (int) ($_POST['admin_id'] ?? 0);
                $spvId = (int) ($_POST['spv_recid'] ?? 0);
                $amount = (int) ($_POST['amount'] ?? 0);
                if ($amount <= 0) {
                    throw new Exception('Jumlah peserta harus lebih dari 0.');
                }

                $assignedCount = $this->model->assignBatch($adminId, $spvId, $amount, $userId);

                $_SESSION['success'] = "{$assignedCount} Peserta berhasil didistribusikan.";

                $this->redirectBack();
            }

            if ($action === 'update_batch') {
                $batchId = (int) ($_POST['batch_id'] ?? 0);
                $spvId = (int) ($_POST['spv_recid'] ?? 0);
                $newAmount = max(0, (int) ($_POST['amount'] ?? 0));

                $this->model->updateBatch($batchId, $spvId, $newAmount);

                $_SESSION['success'] = "Data pengawas dan jumlah kuota berhasil diperbarui.";

                $this->redirectBack();
            }

            if ($action === 'delete') {
                $adminId = (int) ($_POST['admin_id'] ?? 0);

                $this->model->deleteDistribution($adminId, $userId);

                $_SESSION['success'] = "Distribusi Ujian berhasil direset.";

                $this->redirectClean();
            }

        } catch (Exception $e) {
            $_SESSION['error'] = "Gagal: " . $e->getMessage();
            $this->redirectBack();
        }
    }

    private function getPageData(): array
    {
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $filterDate = isset($_GET['date']) ? trim($_GET['date']) : '';
        $distStatus = isset($_GET['dist_status']) ? trim($_GET['dist_status']) : '';
        $filterSpv = isset($_GET['spv_id']) ? trim($_GET['spv_id']) : '';

        $limit = isset($_GET['limit']) && is_numeric($_GET['limit'])
            ? (int) $_GET['limit']
            : 10;

        $page = isset($_GET['page']) && is_numeric($_GET['page'])
            ? (int) $_GET['page']
            : 1;

        $offset = ($page - 1) * $limit;

        $testAdmins = [];
        $totalRows = 0;
        $totalPages = 0;
        $dbError = null;
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $canManageDistribution = canManageTadDistribution($this->pdoRun, $userId);
        $assignedSpvId = $canManageDistribution ? 0 : getTadSupervisorIdForUser($this->pdoRun, $userId);

        try {
            $supervisors = $this->model->getSupervisors();
            $activeDatesJson = $this->model->getActiveDatesJson($canManageDistribution ? null : $assignedSpvId);

            [$whereClauses, $params] = $this->buildFilterQuery(
                $search,
                $filterDate,
                $distStatus,
                $canManageDistribution ? $filterSpv : ''
            );

            if (!$canManageDistribution) {
                if ($assignedSpvId > 0) {
                    $whereClauses[] = "
                        EXISTS (
                            SELECT 1
                            FROM t3sT5ub4dm1n sub_scope
                            WHERE sub_scope.admin_id = a.rec_id
                            AND sub_scope.spv_recid = ?
                        )
                    ";
                    $params[] = $assignedSpvId;
                } else {
                    $whereClauses[] = '1 = 0';
                }
            }

            $totalRows = $this->model->countTestAdmins($whereClauses, $params);
            $totalPages = (int) ceil($totalRows / $limit);

            $testAdmins = $this->model->getTestAdmins($whereClauses, $params, $limit, $offset);

            $testAdmins = $this->attachExtraData($testAdmins, $supervisors, $canManageDistribution ? null : $assignedSpvId);

        } catch (PDOException $e) {
            $supervisors = [];
            $activeDatesJson = "[]";
            $dbError = $e->getMessage();
        }

        return [
            'search' => $search,
            'filterDate' => $filterDate,
            'distStatus' => $distStatus,
            'filterSpv' => $filterSpv,
            'limit' => $limit,
            'page' => $page,
            'offset' => $offset,
            'testAdmins' => $testAdmins,
            'total_rows' => $totalRows,
            'total_pages' => $totalPages,
            'db_error' => $dbError,
            'supervisors' => $supervisors,
            'active_dates_json' => $activeDatesJson,
            'canManageDistribution' => $canManageDistribution,
            'assignedSpvId' => $assignedSpvId,
            'isAssignedOnlyView' => !$canManageDistribution,
        ];
    }

    private function buildFilterQuery(
        string $search,
        string $filterDate,
        string $distStatus,
        string $filterSpv
    ): array {
        $whereClauses = ["a.statrec = '0'"];
        $params = [];

        if (!empty($search)) {
            $matchedClientIds = $this->model->getClientIdsByName($search);

            if (count($matchedClientIds) > 0) {
                $placeholders = implode(',', array_fill(0, count($matchedClientIds), '?'));

                $whereClauses[] = "(a.admin_no LIKE ? OR a.client_id IN ({$placeholders}))";
                $params[] = "%{$search}%";
                $params = array_merge($params, $matchedClientIds);
            } else {
                $whereClauses[] = "(a.admin_no LIKE ?)";
                $params[] = "%{$search}%";
            }
        }

        if (!empty($filterDate)) {
            if (strpos($filterDate, ' to ') !== false) {
                $dates = explode(' to ', $filterDate);

                $whereClauses[] = "DATE(a.testdt) BETWEEN ? AND ?";
                $params[] = trim($dates[0]);
                $params[] = trim($dates[1]);
            } else {
                $whereClauses[] = "DATE(a.testdt) = ?";
                $params[] = trim($filterDate);
            }
        }

        if (!empty($filterSpv)) {
            $whereClauses[] = "
                EXISTS (
                    SELECT 1 
                    FROM t3sT5ub4dm1n sub 
                    WHERE sub.admin_id = a.rec_id 
                    AND sub.spv_recid = ?
                )
            ";

            $params[] = $filterSpv;
        }

        if ($distStatus === '0') {
            $whereClauses[] = "a.subadm_cd = '0'";
        }

        if ($distStatus === '1') {
            $whereClauses[] = "a.subadm_cd = '1'";
        }

        return [$whereClauses, $params];
    }

    private function attachExtraData(array $testAdmins, array $supervisors, ?int $onlySpvId = null): array
    {
        $uniqueClientIds = array_unique(array_column($testAdmins, 'client_id'));
        $clientMap = $this->model->getClientMap($uniqueClientIds);

        $spvMap = [];

        foreach ($supervisors as $supervisor) {
            $spvMap[$supervisor['rec_id']] = $supervisor;
        }

        foreach ($testAdmins as &$admin) {
            $adminId = (int) $admin['rec_id'];

            // Pembeda menu monitoring:
            // t3sTAdm1n.conn_type = 1 => Online, 2 => Hybrid.
            // Jika model TestAdmin::getTestAdmins() belum SELECT conn_type, ambil fallback langsung dari WAR.
            $admin['conn_type'] = isset($admin['conn_type'])
                ? (int) $admin['conn_type']
                : $this->getAdminConnType($adminId);

            $admin['client_nm'] = $clientMap[$admin['client_id']] ?? '-';
            $admin['total_takers'] = $this->model->getTotalTakers($adminId);
            $admin['assigned_takers'] = $this->model->getAssignedTakers($adminId);
            $admin['finished_takers'] = $this->model->getFinishedTakers($adminId);
            $admin['total_issues'] = $this->model->getTotalIssuesByAdmin($adminId);

            $batches = $this->model->getBatchesByAdmin($adminId);

            if ($onlySpvId !== null) {
                $batches = array_values(array_filter($batches, static function ($batch) use ($onlySpvId) {
                    return (int) ($batch['spv_recid'] ?? 0) === $onlySpvId;
                }));
            }

            foreach ($batches as &$batch) {
                $spvId = $batch['spv_recid'];

                $batch['spv_name'] = $spvMap[$spvId]['spv_name'] ?? 'SPV Dihapus/Tidak Valid';
                $batch['itc_usr_id'] = $spvMap[$spvId]['itc_usr_id'] ?? null;
                $batch['captain'] = $spvMap[$spvId]['captain'] ?? 0;
                $batch['total_issues'] = $this->model->getTotalIssuesByBatch((int) $batch['rec_id']);
            }

            unset($batch);

            $admin['batches'] = $batches;
        }

        unset($admin);

        return $testAdmins;
    }


    private function getAdminConnType(int $adminId): int
    {
        if ($adminId <= 0) {
            return 1;
        }

        $stmt = $this->pdoWar->prepare("\n            SELECT conn_type\n            FROM t3sTAdm1n\n            WHERE rec_id = ?\n            LIMIT 1\n        ");
        $stmt->execute([$adminId]);

        $connType = $stmt->fetchColumn();

        return (int) ($connType ?: 1);
    }

    public function buildPageUrl(int $newPage): string
    {
        $params = $_GET;
        $params['page'] = $newPage;

        return '?' . http_build_query($params);
    }

    private function redirectBack(): void
    {
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    private function redirectClean(): void
    {
        header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
}
