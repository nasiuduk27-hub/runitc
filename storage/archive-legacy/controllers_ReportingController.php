<?php

require_once BASE_PATH.'/models/OperationalDashboard.php';
require_once BASE_PATH.'/models/AuditLog.php';

class ReportingController
{
    private PDO $pdo;

    private PDO $pdoRun;

    private PDO $pdoWar;

    private OperationalDashboard $opsDash;

    private AuditLog $auditLog;

    public function __construct(PDO $pdo, PDO $pdoRun, PDO $pdoWar)
    {
        $this->pdo = $pdo;
        $this->pdoRun = $pdoRun;
        $this->pdoWar = $pdoWar;
        $this->opsDash = new OperationalDashboard($pdo, $pdoRun, $pdoWar);
        $this->auditLog = new AuditLog($pdoRun);
    }

    public function handleExport(): void
    {
        $type = $_GET['type'] ?? '';
        $format = $_GET['format'] ?? 'csv';

        try {
            match ($type) {
                'audit_log' => $this->exportAuditLog($format),
                'operational' => $this->exportOperational($format),
                'user_activity' => $this->exportUserActivity($format),
                default => exit('Unknown report type'),
            };
        } catch (Throwable $e) {
            exit('Export error: '.$e->getMessage());
        }
    }

    public function handlePreview(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $type = $_GET['type'] ?? '';

        try {
            $data = match ($type) {
                'audit_log' => $this->previewAuditLog(),
                'user_activity' => $this->previewUserActivity(),
                default => ['error' => 'Unknown type'],
            };
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    private function getAuditLogFilters(): array
    {
        return [
            'action' => $_GET['action'] ?? '',
            'search' => $_GET['search'] ?? '',
            'date_from' => $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days')),
            'date_to' => $_GET['date_to'] ?? date('Y-m-d'),
        ];
    }

    private function previewAuditLog(): array
    {
        $filters = $this->getAuditLogFilters();
        $result = $this->auditLog->getFiltered($filters, 1, 20);

        return ['rows' => $result['rows'], 'total' => $result['total']];
    }

    private function previewUserActivity(): array
    {
        $filters = $this->getAuditLogFilters();
        $result = $this->auditLog->getFiltered($filters, 1, 20);

        return ['rows' => $result['rows'], 'total' => $result['total']];
    }

    private function exportAuditLog(string $format): void
    {
        $filters = $this->getAuditLogFilters();
        $result = $this->auditLog->getFiltered($filters, 1, 99999);
        $rows = $result['rows'];

        $headers = ['Timestamp', 'Actor', 'Action', 'Target Type', 'Target ID', 'IP Address', 'Metadata'];
        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                $r['created_at'] ?? '',
                $r['account_nm'] ?? 'Unknown',
                $r['action'] ?? '',
                $r['target_type'] ?? '',
                $r['target_id'] ?? '',
                $r['ip_address'] ?? '',
                $r['metadata_json'] ?? '',
            ];
        }

        $this->sendCsv($headers, $data, 'audit_log_export_'.date('Ymd_His').'.csv');
    }

    private function exportUserActivity(string $format): void
    {
        // User activity is essentially audit log filtered by user actions
        $filters = $this->getAuditLogFilters();
        $result = $this->auditLog->getFiltered($filters, 1, 99999);
        $rows = $result['rows'];

        $headers = ['Timestamp', 'User', 'Action', 'Resource', 'Status', 'IP Address'];
        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                $r['created_at'] ?? '',
                $r['account_nm'] ?? 'Unknown',
                $r['action'] ?? '',
                ($r['target_type'] ?? '').($r['target_id'] ? '#'.$r['target_id'] : ''),
                'success',
                $r['ip_address'] ?? '',
            ];
        }

        $this->sendCsv($headers, $data, 'user_activity_export_'.date('Ymd_His').'.csv');
    }

    private function exportOperational(string $format): void
    {
        $date = $_GET['date'] ?? date('Y-m-d');
        $clientId = ! empty($_GET['client_id']) ? (int) $_GET['client_id'] : null;

        $roomSummary = $this->opsDash->getRoomStatusSummary($date, $clientId);
        $partSummary = $this->opsDash->getParticipantSummary($date, $clientId);
        $crcStatus = $this->opsDash->getCRCUploadStatus($date, $clientId);
        $baStatus = $this->opsDash->getBeritaAcaraStatus($date, $clientId);
        $spvStatus = $this->opsDash->getSPVDistributionStatus($date, $clientId);
        $incidents = $this->opsDash->getIncidents($date, $clientId);
        $roomList = $this->opsDash->getRoomList(['date' => $date, 'client_id' => $clientId], 1, 999);

        // Generate HTML report
        $html = '<html><head><meta charset="utf-8"><style>
            body { font-family: sans-serif; font-size: 11px; padding: 20px; }
            h1 { color: #1D4ED8; font-size: 18px; margin-bottom: 5px; }
            h2 { font-size: 14px; margin-top: 20px; border-bottom: 2px solid #eee; padding-bottom: 5px; }
            table { width: 100%; border-collapse: collapse; margin: 10px 0; }
            th, td { padding: 6px 8px; text-align: left; border: 1px solid #ddd; }
            th { background: #f3f4f6; font-weight: bold; }
            .summary { display: flex; gap: 15px; flex-wrap: wrap; }
            .summary-item { padding: 10px; background: #f9fafb; border-radius: 8px; min-width: 120px; }
            .summary-item strong { font-size: 18px; display: block; }
            .severity-critical { border-left: 3px solid #DC2626; padding-left: 8px; }
            .severity-warning { border-left: 3px solid #D97706; padding-left: 8px; }
            .severity-info { border-left: 3px solid #3D5FF0; padding-left: 8px; }
            .footer { margin-top: 30px; font-size: 10px; color: #999; text-align: center; }
        </style></head><body>
        <h1>Operational Summary Report</h1>
        <p>Date: '.htmlspecialchars($date).' | Generated: '.date('Y-m-d H:i:s').'</p>';

        $html .= '<h2>Room Summary</h2><div class="summary">
            <div class="summary-item"><strong>'.($roomSummary['active'] ?? 0).'</strong>Active</div>
            <div class="summary-item"><strong>'.($roomSummary['completed'] ?? 0).'</strong>Completed</div>
            <div class="summary-item"><strong>'.($roomSummary['error'] ?? 0).'</strong>Error</div>
            <div class="summary-item"><strong>'.($roomSummary['pending'] ?? 0).'</strong>Pending</div>
            <div class="summary-item"><strong>'.($roomSummary['total_rooms'] ?? 0).'</strong>Total</div>
        </div>';

        $html .= '<h2>Participant Summary</h2><div class="summary">
            <div class="summary-item"><strong>'.($partSummary['total'] ?? 0).'</strong>Total</div>
            <div class="summary-item"><strong>'.($partSummary['assigned'] ?? 0).'</strong>Assigned</div>
            <div class="summary-item"><strong>'.($partSummary['unassigned'] ?? 0).'</strong>Unassigned</div>
        </div>';

        $html .= '<h2>SPV, CRC & BA Status</h2><div class="summary">
            <div class="summary-item"><strong>'.($spvStatus['active_spv'] ?? 0).'</strong>Active SPV</div>
            <div class="summary-item"><strong>'.($crcStatus['uploaded'] ?? 0).'/'.($crcStatus['total_rooms'] ?? 0).'</strong>CRC Uploaded</div>
            <div class="summary-item"><strong>'.($baStatus['complete'] ?? 0).'/'.($baStatus['total_rooms'] ?? 0).'</strong>BA Complete</div>
        </div>';

        if (! empty($incidents)) {
            $html .= '<h2>Incidents ('.count($incidents).')</h2>';
            foreach ($incidents as $inc) {
                $html .= '<div class="severity-'.$inc['severity'].'">'.htmlspecialchars($inc['message']).'</div>';
            }
        }

        if (! empty($roomList['rooms'])) {
            $html .= '<h2>Room List</h2><table>
                <tr><th>Room</th><th>Status</th><th>Peserta</th><th>SPV</th><th>CRC</th><th>BA</th></tr>';
            foreach ($roomList['rooms'] as $room) {
                $html .= '<tr>
                    <td>'.htmlspecialchars($room['admin_no']).'</td>
                    <td>'.$room['status'].'</td>
                    <td>'.($room['finished_participants'] ?? 0).'/'.($room['total_participants'] ?? 0).'</td>
                    <td>'.($room['is_spv_assigned'] ? '✓' : '✗').'</td>
                    <td>'.($room['is_crc_uploaded'] ? '✓' : '✗').'</td>
                    <td>'.($room['is_ba_done'] ? '✓' : '✗').'</td>
                </tr>';
            }
            $html .= '</table>';
        }

        $html .= '<div class="footer">RUNITC Operational Report — Generated '.date('Y-m-d H:i:s').'</div></body></html>';

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    private function sendCsv(array $headers, array $rows, string $filename): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }
}
