<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class OperationalDashboardController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($this->isSuperadmin($request), 403);

        $today = date('Y-m-d');
        $filterDate = (string) $request->query('date', $today);
        $filterClientId = ! empty($request->query('client_id')) ? (int) $request->query('client_id') : null;

        $roomSummary = ['total_rooms' => 0, 'active' => 0, 'completed' => 0, 'pending' => 0, 'error' => 0, 'waiting' => 0];
        $partSummary = ['total' => 0, 'assigned' => 0, 'unassigned' => 0, 'finished' => 0];
        $spvStatus = ['active_spv' => 0, 'total_assignments' => 0, 'rooms_pending_assignment' => 0, 'total_spv' => 0];
        $crcStatus = ['total_rooms' => 0, 'uploaded' => 0, 'pending' => 0];
        $baStatus = ['total_rooms' => 0, 'complete' => 0, 'pending' => 0];
        $locDist = [];
        $incidents = [];
        $activities = [];
        $roomList = ['rooms' => [], 'total' => 0];
        $clientOptions = [];

        try {
            $roomSummary = $this->getRoomStatusSummary($filterDate, $filterClientId);
            $partSummary = $this->getParticipantSummary($filterDate, $filterClientId);
            $spvStatus = $this->getSPVDistributionStatus($filterDate, $filterClientId);
            $crcStatus = $this->getCRCUploadStatus($filterDate, $filterClientId);
            $baStatus = $this->getBeritaAcaraStatus($filterDate, $filterClientId);
            $locDist = $this->getParticipantsByLocation($filterDate, $filterClientId);
            $incidents = $this->getIncidents($filterDate, $filterClientId);
            $activities = $this->getRecentActivity($filterDate, 15);
            $roomList = $this->getRoomList(['date' => $filterDate, 'client_id' => $filterClientId], 1, 50);
            $clientOptions = $this->getDistinctClients($filterDate);
        } catch (Throwable) {
            // Simulator/filing tables may be unavailable; fall back to empty defaults.
        }

        return view('admin.operational-dashboard.index', [
            'filterDate' => $filterDate,
            'filterClientId' => $filterClientId,
            'roomSummary' => $roomSummary,
            'partSummary' => $partSummary,
            'spvStatus' => $spvStatus,
            'crcStatus' => $crcStatus,
            'baStatus' => $baStatus,
            'locDist' => $locDist,
            'incidents' => $incidents,
            'activities' => $activities,
            'roomList' => $roomList,
            'clientOptions' => $clientOptions,
        ]);
    }

    public function api(Request $request): JsonResponse
    {
        abort_unless($this->isSuperadmin($request), 403);

        $result = match ($request->query('action')) {
            'summary' => $this->getSummaryJson($request),
            'rooms' => $this->getRoomListJson($request),
            'room_detail' => $this->getRoomDetailJson($request),
            'incidents' => $this->getIncidentsJson($request),
            default => ['error' => 'Unknown action'],
        };

        return $result instanceof JsonResponse ? $result : response()->json($result);
    }

    public function legacy(Request $request): View|JsonResponse
    {
        return $request->query->has('api') ? $this->api($request) : $this->index($request);
    }

    private function getSummaryJson(Request $request): array
    {
        $date = (string) $request->query('date', date('Y-m-d'));
        $clientId = ! empty($request->query('client_id')) ? (int) $request->query('client_id') : null;

        return [
            'rooms' => $this->getRoomStatusSummary($date, $clientId),
            'participants' => $this->getParticipantSummary($date, $clientId),
            'spv' => $this->getSPVDistributionStatus($date, $clientId),
            'crc' => $this->getCRCUploadStatus($date, $clientId),
            'ba' => $this->getBeritaAcaraStatus($date, $clientId),
            'date' => $date,
        ];
    }

    private function getRoomListJson(Request $request): array
    {
        $page = max(1, (int) $request->query('page', 1));
        $date = (string) $request->query('date', date('Y-m-d'));
        $clientId = ! empty($request->query('client_id')) ? (int) $request->query('client_id') : null;

        return $this->getRoomList([
            'date' => $date,
            'client_id' => $clientId,
            'search' => $request->query('search') ? (string) $request->query('search') : null,
        ], $page);
    }

    private function getRoomDetailJson(Request $request): array|JsonResponse
    {
        $adminNo = (string) $request->query('admin_no', '');
        if ($adminNo === '') {
            return response()->json(['error' => 'admin_no required'], 400);
        }

        $room = $this->getRoomDetail($adminNo);
        if (! $room) {
            return response()->json(['error' => 'Room not found'], 404);
        }

        return $room;
    }

    private function getIncidentsJson(Request $request): array
    {
        $date = (string) $request->query('date', date('Y-m-d'));
        $clientId = ! empty($request->query('client_id')) ? (int) $request->query('client_id') : null;

        return $this->getIncidents($date, $clientId);
    }

    private function getRoomStatusSummary(?string $date = null, ?int $clientId = null, ?string $location = null): array
    {
        $date = $date ?: date('Y-m-d');
        [$where, $params] = $this->buildFilterWhere($date, $clientId, $location);
        $whereSql = implode(' AND ', $where);

        $sql = '
            SELECT
                COUNT(*) AS total_rooms,
                SUM(CASE WHEN EXISTS (
                    SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.statrec IN ("1","2","3","4","5","6","a","A")
                ) THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN NOT EXISTS (
                    SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.statrec NOT IN ("7","8","9","c","C")
                ) AND EXISTS (
                    SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id
                ) THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN EXISTS (
                    SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id
                ) AND NOT EXISTS (
                    SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.statrec IN ("1","2","3","4","5","6","a","A")
                ) AND EXISTS (
                    SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.statrec NOT IN ("7","8","9","c","C")
                ) THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN EXISTS (
                    SELECT 1 FROM t3sTkNotes n JOIN t3sTt4keR5 t ON n.ttaker_id = t.rec_id WHERE t.admin_id = a.rec_id
                ) THEN 1 ELSE 0 END) AS error,
                SUM(CASE WHEN NOT EXISTS (
                    SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id
                ) THEN 1 ELSE 0 END) AS waiting
            FROM t3sTAdm1n a
            WHERE '.$whereSql;

        $row = $this->first('war', $sql, $params);

        return $row ?: ['total_rooms' => 0, 'active' => 0, 'completed' => 0, 'pending' => 0, 'error' => 0, 'waiting' => 0];
    }

    private function getParticipantSummary(?string $date = null, ?int $clientId = null, ?string $location = null): array
    {
        $date = $date ?: date('Y-m-d');
        [$where, $params] = $this->buildFilterWhere($date, $clientId, $location);
        $whereSql = implode(' AND ', $where);

        $sql = '
            SELECT
                COUNT(DISTINCT t.rec_id) AS total,
                SUM(CASE WHEN t.sub_adm_id != 0 THEN 1 ELSE 0 END) AS assigned,
                SUM(CASE WHEN t.sub_adm_id = 0 THEN 1 ELSE 0 END) AS unassigned,
                SUM(CASE WHEN t.statrec IN ("7","8","9","c","C") THEN 1 ELSE 0 END) AS finished
            FROM t3sTt4keR5 t
            JOIN t3sTAdm1n a ON a.rec_id = t.admin_id
            WHERE '.$whereSql;

        $row = $this->first('war', $sql, $params);

        return $row ?: ['total' => 0, 'assigned' => 0, 'unassigned' => 0, 'finished' => 0];
    }

    private function getParticipantsByLocation(?string $date = null, ?int $clientId = null): array
    {
        $date = $date ?: date('Y-m-d');
        [$where, $params] = $this->buildFilterWhere($date, $clientId, null);
        $whereSql = implode(' AND ', $where);

        $sql = '
            SELECT a.client_id, COUNT(DISTINCT t.rec_id) AS total
            FROM t3sTt4keR5 t
            JOIN t3sTAdm1n a ON a.rec_id = t.admin_id
            WHERE '.$whereSql.'
            GROUP BY a.client_id
            ORDER BY total DESC';

        $rows = $this->all('war', $sql, $params);

        $clientIds = array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'client_id')))));
        $clientMap = $this->getClientMap($clientIds);

        $result = [];
        foreach ($rows as $row) {
            $cid = (int) ($row['client_id'] ?? 0);
            $result[] = [
                'location' => $clientMap[$cid] ?? 'Unknown #'.$cid,
                'total' => (int) $row['total'],
            ];
        }

        return $result;
    }

    private function getSPVDistributionStatus(?string $date = null, ?int $clientId = null): array
    {
        $date = $date ?: date('Y-m-d');
        [$where, $params] = $this->buildFilterWhere($date, $clientId, null);
        $whereSql = implode(' AND ', $where);

        $sql = '
            SELECT
                COUNT(DISTINCT s.spv_recid) AS active_spv,
                COUNT(DISTINCT s.rec_id) AS total_assignments,
                SUM(CASE WHEN a.subadm_cd = "0" THEN 1 ELSE 0 END) AS rooms_pending_assignment
            FROM t3sT5ub4dm1n s
            JOIN t3sTAdm1n a ON a.rec_id = s.admin_id
            WHERE '.$whereSql.' AND s.spv_recid > 0';

        $data = $this->first('war', $sql, $params) ?: [
            'active_spv' => 0, 'total_assignments' => 0, 'rooms_pending_assignment' => 0,
        ];

        $data['total_spv'] = (int) DB::connection('run')->table('tad_supervisor')->where('status', 1)->count();

        return $data;
    }

    private function getCRCUploadStatus(?string $date = null, ?int $clientId = null): array
    {
        $date = $date ?: date('Y-m-d');
        [$where, $params] = $this->buildFilterWhere($date, $clientId, null);
        $whereSql = implode(' AND ', $where);

        $totalStmt = $this->first('war', 'SELECT COUNT(*) AS total FROM t3sTAdm1n a WHERE '.$whereSql, $params);
        $totalRooms = (int) ($totalStmt['total'] ?? 0);

        $crcStmt = $this->first('run', '
            SELECT COUNT(DISTINCT f.rec_id) AS total
            FROM runit_filing_system f
            JOIN runit_filing_files ff ON ff.filing_id = f.rec_id
            WHERE ff.file_name LIKE "%CRC%"
              AND DATE(f.tanggal) = ?', [$date]);
        $uploaded = (int) ($crcStmt['total'] ?? 0);

        return [
            'total_rooms' => $totalRooms,
            'uploaded' => $uploaded,
            'pending' => max(0, $totalRooms - $uploaded),
        ];
    }

    private function getBeritaAcaraStatus(?string $date = null, ?int $clientId = null): array
    {
        $date = $date ?: date('Y-m-d');
        [$where, $params] = $this->buildFilterWhere($date, $clientId, null);
        $whereSql = implode(' AND ', $where);

        $totalStmt = $this->first('war', 'SELECT COUNT(*) AS total FROM t3sTAdm1n a WHERE '.$whereSql, $params);
        $totalRooms = (int) ($totalStmt['total'] ?? 0);

        $baStmt = $this->first('run', '
            SELECT
                COUNT(DISTINCT f.rec_id) AS total_ba,
                SUM(CASE WHEN ff.file_id IS NOT NULL THEN 1 ELSE 0 END) AS with_pdf
            FROM runit_filing_system f
            LEFT JOIN runit_filing_files ff ON ff.filing_id = f.rec_id
            WHERE DATE(f.tanggal) = ?
              AND f.keterangan LIKE "%Berita Acara%"', [$date]);
        $withPdf = (int) ($baStmt['with_pdf'] ?? 0);

        return [
            'total_rooms' => $totalRooms,
            'complete' => $withPdf,
            'pending' => max(0, $totalRooms - $withPdf),
        ];
    }

    private function getRoomList(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $date = $filters['date'] ?? date('Y-m-d');
        $clientId = ! empty($filters['client_id']) ? (int) $filters['client_id'] : null;
        $search = $filters['search'] ?? null;

        $where = ['DATE(a.testdt) = ?'];
        $params = [$date];

        if ($clientId) {
            $where[] = 'a.client_id = ?';
            $params[] = $clientId;
        }
        if ($search) {
            $where[] = 'a.admin_no LIKE ?';
            $params[] = "%{$search}%";
        }

        $whereSql = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $countStmt = $this->first('war', 'SELECT COUNT(*) AS total FROM t3sTAdm1n a WHERE '.$whereSql, $params);
        $total = (int) ($countStmt['total'] ?? 0);

        $sql = "
            SELECT
                a.rec_id,
                a.admin_no,
                a.testdt,
                a.client_id,
                a.conn_type,
                a.subadm_cd,
                a.lupdt,
                (SELECT COUNT(*) FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id) AS total_participants,
                (SELECT COUNT(*) FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.sub_adm_id != 0) AS assigned_participants,
                (SELECT COUNT(*) FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.statrec IN ('7','8','9','c','C')) AS finished_participants,
                (SELECT COUNT(*) FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.statrec IN ('1','2','3','4','5','6','a','A')) AS active_participants,
                (SELECT COUNT(*) FROM t3sTkNotes n JOIN t3sTt4keR5 t ON n.ttaker_id = t.rec_id WHERE t.admin_id = a.rec_id) AS issues_count,
                (SELECT spv_recid FROM t3sT5ub4dm1n WHERE t3sT5ub4dm1n.admin_id = a.rec_id AND spv_recid > 0 LIMIT 1) AS spv_recid
            FROM t3sTAdm1n a
            WHERE {$whereSql}
            ORDER BY a.admin_no ASC
            LIMIT {$perPage} OFFSET {$offset}";

        $rooms = $this->all('war', $sql, $params);

        $clientIds = array_values(array_unique(array_filter(array_map('intval', array_column($rooms, 'client_id')))));
        $clientMap = $this->getClientMap($clientIds);

        $spvIds = array_values(array_unique(array_filter(array_map('intval', array_column($rooms, 'spv_recid')))));
        $spvMap = $this->getSPVMap($spvIds);

        $crcUploadedNos = $this->getCRCUploadedAdminNos($date);
        $baDoneNos = $this->getBADoneAdminNos($date);

        foreach ($rooms as &$room) {
            $adminNo = $room['admin_no'];
            $totalP = (int) ($room['total_participants'] ?? 0);
            $finishedP = (int) ($room['finished_participants'] ?? 0);
            $activeP = (int) ($room['active_participants'] ?? 0);
            $hasIssues = (int) ($room['issues_count'] ?? 0) > 0;

            $room['client_nm'] = $clientMap[(int) ($room['client_id'] ?? 0)] ?? '-';
            $room['spv_name'] = $spvMap[(int) ($room['spv_recid'] ?? 0)] ?? null;
            $room['is_spv_assigned'] = (int) ($room['spv_recid'] ?? 0) > 0;
            $room['is_crc_uploaded'] = in_array($adminNo, $crcUploadedNos, true);
            $room['is_ba_done'] = in_array($adminNo, $baDoneNos, true);

            if ($totalP === 0) {
                $room['status'] = 'waiting';
            } elseif ($finishedP === $totalP) {
                $room['status'] = 'completed';
            } elseif ($activeP > 0) {
                $room['status'] = 'active';
            } elseif ($hasIssues) {
                $room['status'] = 'error';
            } else {
                $room['status'] = 'pending';
            }
        }
        unset($room);

        return ['rooms' => $rooms, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }

    private function getRoomDetail(string $adminNo): ?array
    {
        $room = $this->first('war', '
            SELECT a.*,
                (SELECT COUNT(*) FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id) AS total_participants,
                (SELECT COUNT(*) FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.statrec IN ("7","8","9","c","C")) AS finished_participants,
                (SELECT COUNT(*) FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.statrec IN ("1","2","3","4","5","6","a","A")) AS active_participants
            FROM t3sTAdm1n a
            WHERE a.admin_no = ?
            LIMIT 1', [$adminNo]);
        if (! $room) {
            return null;
        }

        $room['client_nm'] = $this->getClientName((int) ($room['client_id'] ?? 0));

        $room['batches'] = $this->all('war', '
            SELECT s.*, ts.spv_name
            FROM t3sT5ub4dm1n s
            LEFT JOIN tad_supervisor ts ON ts.rec_id = s.spv_recid
            WHERE s.admin_id = ?
            ORDER BY s.batch_no ASC', [(int) $room['rec_id']]);

        $room['crc'] = $this->first('run', '
            SELECT ff.*, f.tanggal, f.input_by
            FROM runit_filing_system f
            JOIN runit_filing_files ff ON ff.filing_id = f.rec_id
            WHERE f.nomor_admin = ? AND ff.file_name LIKE "%CRC%"
            ORDER BY ff.uploaded_at DESC
            LIMIT 1', [$adminNo]);

        $room['berita_acara'] = $this->first('run', '
            SELECT ff.*, f.tanggal, f.input_by, f.spv_name, f.keterangan
            FROM runit_filing_system f
            LEFT JOIN runit_filing_files ff ON ff.filing_id = f.rec_id
            WHERE f.nomor_admin = ? AND f.keterangan LIKE "%Berita Acara%"
            ORDER BY f.rec_id DESC
            LIMIT 1', [$adminNo]);

        $room['participants'] = $this->all('war', '
            SELECT t.rec_id, t.authorize, t.statrec, t.regnm, t.sub_adm_id,
                t.start_time, t.end_time, t.remindtm, t.lupdt,
                (SELECT COUNT(*) FROM t3sTkNotes n WHERE n.ttaker_id = t.rec_id) AS issue_count
            FROM t3sTt4keR5 t
            WHERE t.admin_id = ?
            ORDER BY t.rec_id ASC', [(int) $room['rec_id']]);

        return $room;
    }

    private function getIncidents(?string $date = null, ?int $clientId = null): array
    {
        $date = $date ?: date('Y-m-d');
        $incidents = [];

        foreach ($this->all('war', '
            SELECT a.admin_no, a.rec_id, COUNT(n.rec_id) AS cnt
            FROM t3sTkNotes n
            JOIN t3sTt4keR5 t ON n.ttaker_id = t.rec_id
            JOIN t3sTAdm1n a ON a.rec_id = t.admin_id
            WHERE DATE(a.testdt) = ?
            GROUP BY a.admin_no, a.rec_id', [$date]) as $row) {
            $incidents[] = [
                'severity' => 'critical',
                'type' => 'participant_error',
                'message' => "{$row['cnt']} peserta error di room {$row['admin_no']}",
                'target_room' => $row['admin_no'],
                'room_id' => (int) $row['rec_id'],
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        }

        foreach ($this->all('war', '
            SELECT a.admin_no, a.rec_id
            FROM t3sTAdm1n a
            WHERE DATE(a.testdt) = ?
              AND a.subadm_cd = "0"
              AND EXISTS (SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.sub_adm_id = 0)', [$date]) as $row) {
            $incidents[] = [
                'severity' => 'warning',
                'type' => 'spv_pending',
                'message' => "Room {$row['admin_no']} belum memiliki SPV assignment",
                'target_room' => $row['admin_no'],
                'room_id' => (int) $row['rec_id'],
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        }

        foreach ($this->all('run', '
            SELECT a.admin_no, a.rec_id
            FROM t3sTAdm1n a
            WHERE DATE(a.testdt) = ?
              AND NOT EXISTS (
                SELECT 1 FROM runit_filing_system f
                JOIN runit_filing_files ff ON ff.filing_id = f.rec_id
                WHERE f.nomor_admin = a.admin_no AND ff.file_name LIKE "%CRC%"
              )
              AND NOT EXISTS (
                SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.statrec NOT IN ("7","8","9","c","C")
              )
              AND EXISTS (SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id)', [$date]) as $row) {
            $incidents[] = [
                'severity' => 'warning',
                'type' => 'crc_overdue',
                'message' => "CRC belum diupload untuk room {$row['admin_no']} (room sudah selesai)",
                'target_room' => $row['admin_no'],
                'room_id' => (int) $row['rec_id'],
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        }

        foreach ($this->all('run', '
            SELECT a.admin_no, a.rec_id
            FROM t3sTAdm1n a
            WHERE DATE(a.testdt) = ?
              AND NOT EXISTS (
                SELECT 1 FROM runit_filing_system f
                WHERE f.nomor_admin = a.admin_no AND f.keterangan LIKE "%Berita Acara%"
              )
              AND NOT EXISTS (
                SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.statrec NOT IN ("7","8","9","c","C")
              )
              AND EXISTS (SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id)', [$date]) as $row) {
            $incidents[] = [
                'severity' => 'info',
                'type' => 'ba_pending',
                'message' => "Berita Acara pending untuk room {$row['admin_no']} (room sudah selesai)",
                'target_room' => $row['admin_no'],
                'room_id' => (int) $row['rec_id'],
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        }

        $severityOrder = ['critical' => 0, 'warning' => 1, 'info' => 2];
        usort($incidents, fn ($a, $b) => ($severityOrder[$a['severity']] ?? 99) <=> ($severityOrder[$b['severity']] ?? 99));

        return $incidents;
    }

    private function getRecentActivity(?string $date = null, int $limit = 20): array
    {
        $date = $date ?: date('Y-m-d');
        $activities = [];

        foreach ($this->all('war', '
            SELECT a.admin_no, a.lupdt
            FROM t3sTAdm1n a
            WHERE DATE(a.testdt) = ?
              AND NOT EXISTS (
                SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.statrec NOT IN ("7","8","9","c","C")
              )
              AND EXISTS (SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id)
            ORDER BY a.lupdt DESC
            LIMIT 5', [$date]) as $row) {
            $activities[] = [
                'type' => 'room_completed',
                'message' => "Room {$row['admin_no']} selesai",
                'timestamp' => $row['lupdt'],
            ];
        }

        foreach ($this->all('run', '
            SELECT f.nomor_admin, ff.uploaded_at, f.input_by
            FROM runit_filing_system f
            JOIN runit_filing_files ff ON ff.filing_id = f.rec_id
            WHERE ff.file_name LIKE "%CRC%" AND DATE(ff.uploaded_at) = ?
            ORDER BY ff.uploaded_at DESC
            LIMIT 5', [$date]) as $row) {
            $activities[] = [
                'type' => 'crc_uploaded',
                'message' => 'CRC uploaded '.$row['nomor_admin'].' oleh '.($row['input_by'] ?? 'System'),
                'timestamp' => $row['uploaded_at'],
            ];
        }

        foreach ($this->all('run', '
            SELECT f.nomor_admin, ff.uploaded_at, f.input_by
            FROM runit_filing_system f
            JOIN runit_filing_files ff ON ff.filing_id = f.rec_id
            WHERE f.keterangan LIKE "%Berita Acara%" AND DATE(ff.uploaded_at) = ?
            ORDER BY ff.uploaded_at DESC
            LIMIT 5', [$date]) as $row) {
            $activities[] = [
                'type' => 'ba_submitted',
                'message' => 'Berita Acara '.$row['nomor_admin'].' di-submit oleh '.($row['input_by'] ?? 'System'),
                'timestamp' => $row['uploaded_at'],
            ];
        }

        usort($activities, fn ($a, $b) => strtotime($b['timestamp']) - strtotime($a['timestamp']));

        return array_slice($activities, 0, $limit);
    }

    private function getDistinctClients(?string $date = null): array
    {
        $date = $date ?: date('Y-m-d');

        $rows = $this->all('war', '
            SELECT DISTINCT a.client_id FROM t3sTAdm1n a
            WHERE DATE(a.testdt) = ? AND a.client_id IS NOT NULL AND a.client_id > 0
            ORDER BY a.client_id', [$date]);

        $ids = array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'client_id')))));

        return $this->getClientMap($ids);
    }

    private function getClientName(int $clientId): string
    {
        if ($clientId <= 0) {
            return '-';
        }

        $name = DB::connection('mysql')->table('sys_mstclient')->where('rec_id', $clientId)->value('clientnm');

        return (string) ($name ?: '-');
    }

    private function buildFilterWhere(string $date, ?int $clientId, ?string $location): array
    {
        $where = ['DATE(a.testdt) = ?'];
        $params = [$date];
        if ($clientId) {
            $where[] = 'a.client_id = ?';
            $params[] = $clientId;
        }

        return [$where, $params];
    }

    private function getClientMap(array $clientIds): array
    {
        if (empty($clientIds)) {
            return [];
        }

        $map = [];
        foreach (DB::connection('mysql')->table('sys_mstclient')->whereIn('rec_id', $clientIds)->get(['rec_id', 'clientnm']) as $row) {
            $map[(int) $row->rec_id] = $row->clientnm;
        }

        return $map;
    }

    private function getSPVMap(array $spvIds): array
    {
        if (empty($spvIds)) {
            return [];
        }

        $map = [];
        foreach (DB::connection('run')->table('tad_supervisor')->whereIn('rec_id', $spvIds)->get(['rec_id', 'spv_name']) as $row) {
            $map[(int) $row->rec_id] = $row->spv_name;
        }

        return $map;
    }

    private function getCRCUploadedAdminNos(string $date): array
    {
        return array_column($this->all('run', '
            SELECT DISTINCT f.nomor_admin
            FROM runit_filing_system f
            JOIN runit_filing_files ff ON ff.filing_id = f.rec_id
            WHERE ff.file_name LIKE "%CRC%" AND DATE(ff.uploaded_at) = ?', [$date]), 'nomor_admin');
    }

    private function getBADoneAdminNos(string $date): array
    {
        return array_column($this->all('run', '
            SELECT DISTINCT f.nomor_admin
            FROM runit_filing_system f
            WHERE f.keterangan LIKE "%Berita Acara%" AND DATE(f.tanggal) = ?', [$date]), 'nomor_admin');
    }

    private function first(string $connection, string $sql, array $params = []): ?array
    {
        $rows = DB::connection($connection)->select($sql, $params);
        $row = $rows[0] ?? null;

        return $row ? (array) $row : null;
    }

    private function all(string $connection, string $sql, array $params = []): array
    {
        return array_map(fn ($row): array => (array) $row, DB::connection($connection)->select($sql, $params));
    }

    private function isSuperadmin(Request $request): bool
    {
        $userId = (int) $request->session()->get('user_id', 0);
        if ($userId <= 0) {
            return false;
        }

        return DB::connection('run')
            ->table('sysitc_usracc as ua')
            ->join('sysitc_grpacc as g', function ($join): void {
                $join->on('g.grpaccess', '=', 'ua.access_code')->on('g.grpacc', '=', 'ua.access_account');
            })
            ->where('ua.user_rec_id', $userId)
            ->where('g.grpaccess', '03')
            ->where('g.grpacc', '999')
            ->where('g.grpdesc', 'like', '%SUPER%ADMIN%')
            ->exists();
    }
}
