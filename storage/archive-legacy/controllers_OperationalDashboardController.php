<?php

require_once BASE_PATH.'/models/OperationalDashboard.php';

class OperationalDashboardController
{
    private OperationalDashboard $model;

    private PDO $pdoRun;

    public function __construct(PDO $pdo, PDO $pdoRun, PDO $pdoWar)
    {
        $this->model = new OperationalDashboard($pdo, $pdoRun, $pdoWar);
        $this->pdoRun = $pdoRun;
    }

    public function getModel(): OperationalDashboard
    {
        return $this->model;
    }

    public function handleApi(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $action = $_GET['action'] ?? '';

        try {
            $result = match ($action) {
                'summary' => $this->getSummaryJson(),
                'rooms' => $this->getRoomListJson(),
                'room_detail' => $this->getRoomDetailJson(),
                'incidents' => $this->getIncidentsJson(),
                default => ['error' => 'Unknown action'],
            };
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    private function getSummaryJson(): array
    {
        $date = $_GET['date'] ?? date('Y-m-d');
        $clientId = ! empty($_GET['client_id']) ? (int) $_GET['client_id'] : null;

        return [
            'rooms' => $this->model->getRoomStatusSummary($date, $clientId),
            'participants' => $this->model->getParticipantSummary($date, $clientId),
            'spv' => $this->model->getSPVDistributionStatus($date, $clientId),
            'crc' => $this->model->getCRCUploadStatus($date, $clientId),
            'ba' => $this->model->getBeritaAcaraStatus($date, $clientId),
            'date' => $date,
        ];
    }

    private function getRoomListJson(): array
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $filterDate = $_GET['date'] ?? date('Y-m-d');
        $clientId = ! empty($_GET['client_id']) ? (int) $_GET['client_id'] : null;

        $result = $this->model->getRoomList([
            'date' => $filterDate,
            'client_id' => $clientId,
            'search' => $_GET['search'] ?? null,
        ], $page);

        return $result;
    }

    private function getRoomDetailJson(): array
    {
        $adminNo = $_GET['admin_no'] ?? '';
        if (empty($adminNo)) {
            http_response_code(400);

            return ['error' => 'admin_no required'];
        }

        $room = $this->model->getRoomDetail($adminNo);
        if (! $room) {
            http_response_code(404);

            return ['error' => 'Room not found'];
        }

        return $room;
    }

    private function getIncidentsJson(): array
    {
        $date = $_GET['date'] ?? date('Y-m-d');
        $clientId = ! empty($_GET['client_id']) ? (int) $_GET['client_id'] : null;

        return $this->model->getIncidents($date, $clientId);
    }
}
