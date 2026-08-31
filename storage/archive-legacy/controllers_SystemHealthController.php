<?php

require_once BASE_PATH.'/models/SystemHealth.php';

class SystemHealthController
{
    private SystemHealth $model;

    private PDO $pdoRun;

    public function __construct(PDO $pdo, PDO $pdoBot, PDO $pdoRun, PDO $pdoWar, array $ftpConfig)
    {
        $this->model = new SystemHealth($pdo, $pdoBot, $pdoRun, $pdoWar, $ftpConfig);
        $this->pdoRun = $pdoRun;
    }

    public function getModel(): SystemHealth
    {
        return $this->model;
    }

    public function handleApi(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $action = $_GET['action'] ?? '';

        try {
            $result = match ($action) {
                'check_all' => $this->model->checkAll(),
                'test_db' => $this->testDbAjax(),
                'test_ftp' => $this->model->checkFTP(),
                default => ['error' => 'Unknown action'],
            };
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    private function testDbAjax(): array
    {
        $dbKey = $_GET['db'] ?? '';
        $pdoMap = ['main_db' => $this->pdo, 'bot_db' => $this->pdoBot, 'run_db' => $this->pdoRun, 'war_db' => $this->pdoWar];
        $pdo = $pdoMap[$dbKey] ?? null;
        if (! $pdo) {
            return ['error' => 'Unknown database'];
        }

        return $this->model->checkDatabaseConnection(ucfirst(str_replace('_', ' ', $dbKey)), $pdo);
    }
}
