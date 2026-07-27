<?php

class OperationalDashboard
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

    public function getRoomStatusSummary(?string $date = null, ?int $clientId = null, ?string $location = null): array
    {
        $date = $date ?: date('Y-m-d');
        [$where, $params] = $this->buildFilterWhere($date, $clientId, $location);

        $whereSql = implode(' AND ', $where);

        $stmt = $this->pdoWar->prepare("
            SELECT
                COUNT(*) AS total_rooms,
                SUM(CASE WHEN EXISTS (
                    SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.statrec IN ('1','2','3','4','5','6','a','A')
                ) THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN NOT EXISTS (
                    SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.statrec NOT IN ('7','8','9','c','C')
                ) AND EXISTS (
                    SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id
                ) THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN EXISTS (
                    SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id
                ) AND NOT EXISTS (
                    SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.statrec IN ('1','2','3','4','5','6','a','A')
                ) AND EXISTS (
                    SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.statrec NOT IN ('7','8','9','c','C')
                ) THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN EXISTS (
                    SELECT 1 FROM t3sTkNotes n JOIN t3sTt4keR5 t ON n.ttaker_id = t.rec_id WHERE t.admin_id = a.rec_id
                ) THEN 1 ELSE 0 END) AS error,
                SUM(CASE WHEN NOT EXISTS (
                    SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id
                ) THEN 1 ELSE 0 END) AS waiting
            FROM t3sTAdm1n a
            WHERE {$whereSql}
        ");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_rooms' => 0, 'active' => 0, 'completed' => 0,
            'pending' => 0, 'error' => 0, 'waiting' => 0,
        ];
    }

    public function getParticipantSummary(?string $date = null, ?int $clientId = null, ?string $location = null): array
    {
        $date = $date ?: date('Y-m-d');
        [$where, $params] = $this->buildFilterWhere($date, $clientId, $location);
        $whereSql = implode(' AND ', $where);

        $stmt = $this->pdoWar->prepare("
            SELECT
                COUNT(DISTINCT t.rec_id) AS total,
                SUM(CASE WHEN t.sub_adm_id != 0 THEN 1 ELSE 0 END) AS assigned,
                SUM(CASE WHEN t.sub_adm_id = 0 THEN 1 ELSE 0 END) AS unassigned,
                SUM(CASE WHEN t.statrec IN ('7','8','9','c','C') THEN 1 ELSE 0 END) AS finished
            FROM t3sTt4keR5 t
            JOIN t3sTAdm1n a ON a.rec_id = t.admin_id
            WHERE {$whereSql}
        ");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total' => 0, 'assigned' => 0, 'unassigned' => 0, 'finished' => 0,
        ];
    }

    public function getParticipantsByLocation(?string $date = null, ?int $clientId = null): array
    {
        $date = $date ?: date('Y-m-d');
        [$where, $params] = $this->buildFilterWhere($date, $clientId, null);
        $whereSql = implode(' AND ', $where);

        // Gunakan client_id sebagai proxy untuk "location"
        $stmt = $this->pdoWar->prepare("
            SELECT a.client_id, COUNT(DISTINCT t.rec_id) AS total
            FROM t3sTt4keR5 t
            JOIN t3sTAdm1n a ON a.rec_id = t.admin_id
            WHERE {$whereSql}
            GROUP BY a.client_id
            ORDER BY total DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Map client IDs ke nama
        $clientIds = array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'client_id')))));
        $clientMap = $this->getClientMap($clientIds);

        $result = [];
        foreach ($rows as $row) {
            $cid = (int) ($row['client_id'] ?? 0);
            $result[] = [
                'location' => $clientMap[$cid] ?? 'Unknown #' . $cid,
                'total' => (int) $row['total'],
            ];
        }
        return $result;
    }

    public function getSPVDistributionStatus(?string $date = null, ?int $clientId = null): array
    {
        $date = $date ?: date('Y-m-d');
        [$where, $params] = $this->buildFilterWhere($date, $clientId, null);
        $whereSql = implode(' AND ', $where);

        $stmt = $this->pdoWar->prepare("
            SELECT
                COUNT(DISTINCT s.spv_recid) AS active_spv,
                COUNT(DISTINCT s.rec_id) AS total_assignments,
                SUM(CASE WHEN a.subadm_cd = '0' THEN 1 ELSE 0 END) AS rooms_pending_assignment
            FROM t3sT5ub4dm1n s
            JOIN t3sTAdm1n a ON a.rec_id = s.admin_id
            WHERE {$whereSql} AND s.spv_recid > 0
        ");
        $stmt->execute($params);
        $data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'active_spv' => 0, 'total_assignments' => 0, 'rooms_pending_assignment' => 0,
        ];

        // Total SPV registered
        $spvStmt = $this->pdoRun->query("SELECT COUNT(*) FROM tad_supervisor WHERE status = 1");
        $data['total_spv'] = (int) $spvStmt->fetchColumn();

        return $data;
    }

    public function getCRCUploadStatus(?string $date = null, ?int $clientId = null): array
    {
        // CRC upload di-track via filing system untuk CRC files
        $date = $date ?: date('Y-m-d');
        [$where, $params] = $this->buildFilterWhere($date, $clientId, null);
        $whereSql = implode(' AND ', $where);

        // Total rooms today
        $totalStmt = $this->pdoWar->prepare("SELECT COUNT(*) FROM t3sTAdm1n a WHERE {$whereSql}");
        $totalStmt->execute($params);
        $totalRooms = (int) $totalStmt->fetchColumn();

        // Rooms with CRC uploaded via filing files (CRC ZIP)
        $crcStmt = $this->pdoRun->prepare("
            SELECT COUNT(DISTINCT f.rec_id)
            FROM runit_filing_system f
            JOIN runit_filing_files ff ON ff.filing_id = f.rec_id
            WHERE ff.file_name LIKE '%CRC%'
              AND DATE(f.tanggal) = ?
        ");
        $crcStmt->execute([$date]);
        $uploaded = (int) $crcStmt->fetchColumn();

        $pending = max(0, $totalRooms - $uploaded);

        return [
            'total_rooms' => $totalRooms,
            'uploaded' => $uploaded,
            'pending' => $pending,
        ];
    }

    public function getBeritaAcaraStatus(?string $date = null, ?int $clientId = null): array
    {
        $date = $date ?: date('Y-m-d');
        [$where, $params] = $this->buildFilterWhere($date, $clientId, null);
        $whereSql = implode(' AND ', $where);

        $totalStmt = $this->pdoWar->prepare("SELECT COUNT(*) FROM t3sTAdm1n a WHERE {$whereSql}");
        $totalStmt->execute($params);
        $totalRooms = (int) $totalStmt->fetchColumn();

        // BA status lewat filing system
        $baStmt = $this->pdoRun->prepare("
            SELECT
                COUNT(DISTINCT f.rec_id) AS total_ba,
                SUM(CASE WHEN ff.file_id IS NOT NULL THEN 1 ELSE 0 END) AS with_pdf
            FROM runit_filing_system f
            LEFT JOIN runit_filing_files ff ON ff.filing_id = f.rec_id
            WHERE DATE(f.tanggal) = ?
              AND f.keterangan LIKE '%Berita Acara%'
        ");
        $baStmt->execute([$date]);
        $baData = $baStmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_rooms' => $totalRooms,
            'complete' => (int) ($baData['with_pdf'] ?? 0),
            'pending' => max(0, $totalRooms - (int) ($baData['with_pdf'] ?? 0)),
        ];
    }

    public function getRoomList(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $date = $filters['date'] ?? date('Y-m-d');
        $clientId = !empty($filters['client_id']) ? (int) $filters['client_id'] : null;
        $location = $filters['location'] ?? null;
        $status = $filters['status'] ?? null;
        $search = $filters['search'] ?? null;

        $where = ["DATE(a.testdt) = ?"];
        $params = [$date];

        if ($clientId) {
            $where[] = "a.client_id = ?";
            $params[] = $clientId;
        }
        if ($search) {
            $where[] = "a.admin_no LIKE ?";
            $params[] = "%{$search}%";
        }

        $whereSql = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        // Count
        $countStmt = $this->pdoWar->prepare("SELECT COUNT(*) FROM t3sTAdm1n a WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Fetch rooms with key aggregates
        $stmt = $this->pdoWar->prepare("
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
                (SELECT spv_recid FROM t3sT5ub4dm1n WHERE admin_id = a.rec_id AND spv_recid > 0 LIMIT 1) AS spv_recid
            FROM t3sTAdm1n a
            WHERE {$whereSql}
            ORDER BY a.admin_no ASC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Enrich with spv names, CRC/BA status
        $clientIds = array_filter(array_unique(array_map('intval', array_column($rooms, 'client_id'))));
        $clientMap = $this->getClientMap($clientIds);

        $spvIds = array_filter(array_unique(array_map('intval', array_column($rooms, 'spv_recid'))));
        $spvMap = $this->getSPVMap($spvIds);

        // Bulk fetch CRC & BA status for all rooms today
        $adminNos = array_filter(array_column($rooms, 'admin_no'));
        $crcUploadedNos = $this->getCRCUploadedAdminNos($date);
        $baDoneNos = $this->getBADoneAdminNos($date);

        foreach ($rooms as &$room) {
            $roomId = (int) $room['rec_id'];
            $adminNo = $room['admin_no'];
            $totalP = (int) ($room['total_participants'] ?? 0);
            $finishedP = (int) ($room['finished_participants'] ?? 0);
            $activeP = (int) ($room['active_participants'] ?? 0);
            $hasIssues = (int) ($room['issues_count'] ?? 0) > 0;

            $room['client_nm'] = $clientMap[(int) ($room['client_id'] ?? 0)] ?? '-';
            $room['spv_name'] = $spvMap[(int) ($room['spv_recid'] ?? 0)] ?? null;
            $room['is_spv_assigned'] = (int) ($room['spv_recid'] ?? 0) > 0;
            $room['is_crc_uploaded'] = in_array($adminNo, $crcUploadedNos);
            $room['is_ba_done'] = in_array($adminNo, $baDoneNos);

            // Derive status
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

    public function getRoomDetail(string $adminNo): ?array
    {
        $stmt = $this->pdoWar->prepare("
            SELECT a.*,
                (SELECT COUNT(*) FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id) AS total_participants,
                (SELECT COUNT(*) FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.statrec IN ('7','8','9','c','C')) AS finished_participants,
                (SELECT COUNT(*) FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.statrec IN ('1','2','3','4','5','6','a','A')) AS active_participants
            FROM t3sTAdm1n a
            WHERE a.admin_no = ?
            LIMIT 1
        ");
        $stmt->execute([$adminNo]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$room) return null;

        $room['client_nm'] = $this->getClientName((int) ($room['client_id'] ?? 0));

        // Batches/SPVs
        $batchStmt = $this->pdoWar->prepare("
            SELECT s.*, ts.spv_name
            FROM t3sT5ub4dm1n s
            LEFT JOIN tad_supervisor ts ON ts.rec_id = s.spv_recid
            WHERE s.admin_id = ?
            ORDER BY s.batch_no ASC
        ");
        $batchStmt->execute([$room['rec_id']]);
        $room['batches'] = $batchStmt->fetchAll(PDO::FETCH_ASSOC);

        // CRC info
        $crcStmt = $this->pdoRun->prepare("
            SELECT ff.*, f.tanggal, f.input_by
            FROM runit_filing_system f
            JOIN runit_filing_files ff ON ff.filing_id = f.rec_id
            WHERE f.nomor_admin = ? AND ff.file_name LIKE '%CRC%'
            ORDER BY ff.uploaded_at DESC
            LIMIT 1
        ");
        $crcStmt->execute([$adminNo]);
        $room['crc'] = $crcStmt->fetch(PDO::FETCH_ASSOC);

        // BA info
        $baStmt = $this->pdoRun->prepare("
            SELECT ff.*, f.tanggal, f.input_by, f.spv_name, f.keterangan
            FROM runit_filing_system f
            LEFT JOIN runit_filing_files ff ON ff.filing_id = f.rec_id
            WHERE f.nomor_admin = ? AND f.keterangan LIKE '%Berita Acara%'
            ORDER BY f.rec_id DESC
            LIMIT 1
        ");
        $baStmt->execute([$adminNo]);
        $room['berita_acara'] = $baStmt->fetch(PDO::FETCH_ASSOC);

        // Participant list (paginated)
        $partStmt = $this->pdoWar->prepare("
            SELECT t.rec_id, t.authorize, t.statrec, t.regnm, t.sub_adm_id,
                t.start_time, t.end_time, t.remindtm, t.lupdt,
                (SELECT COUNT(*) FROM t3sTkNotes n WHERE n.ttaker_id = t.rec_id) AS issue_count
            FROM t3sTt4keR5 t
            WHERE t.admin_id = ?
            ORDER BY t.rec_id ASC
        ");
        $partStmt->execute([$room['rec_id']]);
        $room['participants'] = $partStmt->fetchAll(PDO::FETCH_ASSOC);

        return $room;
    }

    public function getIncidents(?string $date = null, ?int $clientId = null): array
    {
        $date = $date ?: date('Y-m-d');
        $incidents = [];

        // 1. Rooms with issues (error participants)
        $stmt = $this->pdoWar->prepare("
            SELECT a.admin_no, a.rec_id, COUNT(n.rec_id) AS cnt
            FROM t3sTkNotes n
            JOIN t3sTt4keR5 t ON n.ttaker_id = t.rec_id
            JOIN t3sTAdm1n a ON a.rec_id = t.admin_id
            WHERE DATE(a.testdt) = ?
            GROUP BY a.admin_no, a.rec_id
        ");
        $stmt->execute([$date]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $incidents[] = [
                'severity' => 'critical',
                'type' => 'participant_error',
                'message' => "{$row['cnt']} peserta error di room {$row['admin_no']}",
                'target_room' => $row['admin_no'],
                'room_id' => (int) $row['rec_id'],
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        }

        // 2. Rooms without SPV assignment (pending)
        $spvStmt = $this->pdoWar->prepare("
            SELECT a.admin_no, a.rec_id
            FROM t3sTAdm1n a
            WHERE DATE(a.testdt) = ?
              AND a.subadm_cd = '0'
              AND EXISTS (SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.sub_adm_id = 0)
        ");
        $spvStmt->execute([$date]);
        foreach ($spvStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $incidents[] = [
                'severity' => 'warning',
                'type' => 'spv_pending',
                'message' => "Room {$row['admin_no']} belum memiliki SPV assignment",
                'target_room' => $row['admin_no'],
                'room_id' => (int) $row['rec_id'],
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        }

        // 3. Rooms completed but CRC not uploaded
        $crcStmt = $this->pdoRun->prepare("
            SELECT a.admin_no, a.rec_id
            FROM t3sTAdm1n a
            WHERE DATE(a.testdt) = ?
              AND NOT EXISTS (
                SELECT 1 FROM runit_filing_system f
                JOIN runit_filing_files ff ON ff.filing_id = f.rec_id
                WHERE f.nomor_admin = a.admin_no AND ff.file_name LIKE '%CRC%'
              )
              AND NOT EXISTS (
                SELECT 1 FROM t3sTt4keR5 t
                WHERE t.admin_id = a.rec_id AND t.statrec NOT IN ('7','8','9','c','C')
              )
              AND EXISTS (SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id)
        ");
        $crcStmt->execute([$date]);
        foreach ($crcStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $incidents[] = [
                'severity' => 'warning',
                'type' => 'crc_overdue',
                'message' => "CRC belum diupload untuk room {$row['admin_no']} (room sudah selesai)",
                'target_room' => $row['admin_no'],
                'room_id' => (int) $row['rec_id'],
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        }

        // 4. Rooms completed but BA not submitted
        $baStmt = $this->pdoRun->prepare("
            SELECT a.admin_no, a.rec_id
            FROM t3sTAdm1n a
            WHERE DATE(a.testdt) = ?
              AND NOT EXISTS (
                SELECT 1 FROM runit_filing_system f
                WHERE f.nomor_admin = a.admin_no AND f.keterangan LIKE '%Berita Acara%'
              )
              AND NOT EXISTS (
                SELECT 1 FROM t3sTt4keR5 t
                WHERE t.admin_id = a.rec_id AND t.statrec NOT IN ('7','8','9','c','C')
              )
              AND EXISTS (SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id)
        ");
        $baStmt->execute([$date]);
        foreach ($baStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $incidents[] = [
                'severity' => 'info',
                'type' => 'ba_pending',
                'message' => "Berita Acara pending untuk room {$row['admin_no']} (room sudah selesai)",
                'target_room' => $row['admin_no'],
                'room_id' => (int) $row['rec_id'],
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        }

        // Sort: critical first, then warning, then info
        $severityOrder = ['critical' => 0, 'warning' => 1, 'info' => 2];
        usort($incidents, function ($a, $b) use ($severityOrder) {
            return ($severityOrder[$a['severity']] ?? 99) <=> ($severityOrder[$b['severity']] ?? 99);
        });

        return $incidents;
    }

    public function getRecentActivity(?string $date = null, int $limit = 20): array
    {
        $date = $date ?: date('Y-m-d');
        $activities = [];

        // Room completions
        $stmt = $this->pdoWar->prepare("
            SELECT a.admin_no, a.lupdt
            FROM t3sTAdm1n a
            WHERE DATE(a.testdt) = ?
              AND NOT EXISTS (
                SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.statrec NOT IN ('7','8','9','c','C')
              )
              AND EXISTS (SELECT 1 FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id)
            ORDER BY a.lupdt DESC
            LIMIT 5
        ");
        $stmt->execute([$date]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $activities[] = [
                'type' => 'room_completed',
                'message' => "Room {$row['admin_no']} selesai",
                'timestamp' => $row['lupdt'],
            ];
        }

        // Recent CRC uploads
        $crcStmt = $this->pdoRun->prepare("
            SELECT f.nomor_admin, ff.uploaded_at, f.input_by
            FROM runit_filing_system f
            JOIN runit_filing_files ff ON ff.filing_id = f.rec_id
            WHERE ff.file_name LIKE '%CRC%' AND DATE(ff.uploaded_at) = ?
            ORDER BY ff.uploaded_at DESC
            LIMIT 5
        ");
        $crcStmt->execute([$date]);
        foreach ($crcStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $activities[] = [
                'type' => 'crc_uploaded',
                'message' => "CRC uploaded {$row['nomor_admin']} oleh " . ($row['input_by'] ?? 'System'),
                'timestamp' => $row['uploaded_at'],
            ];
        }

        // Recent BA submissions
        $baStmt = $this->pdoRun->prepare("
            SELECT f.nomor_admin, ff.uploaded_at, f.input_by
            FROM runit_filing_system f
            JOIN runit_filing_files ff ON ff.filing_id = f.rec_id
            WHERE f.keterangan LIKE '%Berita Acara%' AND DATE(ff.uploaded_at) = ?
            ORDER BY ff.uploaded_at DESC
            LIMIT 5
        ");
        $baStmt->execute([$date]);
        foreach ($baStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $activities[] = [
                'type' => 'ba_submitted',
                'message' => "Berita Acara {$row['nomor_admin']} di-submit oleh " . ($row['input_by'] ?? 'System'),
                'timestamp' => $row['uploaded_at'],
            ];
        }

        // Sort by timestamp desc and limit
        usort($activities, fn($a, $b) => strtotime($b['timestamp']) - strtotime($a['timestamp']));
        return array_slice($activities, 0, $limit);
    }

    public function getDistinctClients(?string $date = null): array
    {
        $date = $date ?: date('Y-m-d');
        $stmt = $this->pdoWar->prepare("
            SELECT DISTINCT a.client_id FROM t3sTAdm1n a
            WHERE DATE(a.testdt) = ? AND a.client_id IS NOT NULL AND a.client_id > 0
            ORDER BY a.client_id
        ");
        $stmt->execute([$date]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $map = $this->getClientMap($ids);
        return $map;
    }

    public function getClientName(int $clientId): string
    {
        if ($clientId <= 0) return '-';
        $stmt = $this->pdo->prepare("SELECT clientnm FROM sys_mstclient WHERE rec_id = ? LIMIT 1");
        $stmt->execute([$clientId]);
        return $stmt->fetchColumn() ?: '-';
    }

    // --- Private helpers ---

    private function buildFilterWhere(string $date, ?int $clientId, ?string $location): array
    {
        $where = ["DATE(a.testdt) = ?"];
        $params = [$date];
        if ($clientId) {
            $where[] = "a.client_id = ?";
            $params[] = $clientId;
        }
        return [$where, $params];
    }

    private function getClientMap(array $clientIds): array
    {
        if (empty($clientIds)) return [];
        $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
        $stmt = $this->pdo->prepare("SELECT rec_id, clientnm FROM sys_mstclient WHERE rec_id IN ({$placeholders})");
        $stmt->execute($clientIds);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['rec_id']] = $row['clientnm'];
        }
        return $map;
    }

    private function getSPVMap(array $spvIds): array
    {
        if (empty($spvIds)) return [];
        $placeholders = implode(',', array_fill(0, count($spvIds), '?'));
        $stmt = $this->pdoRun->prepare("SELECT rec_id, spv_name FROM tad_supervisor WHERE rec_id IN ({$placeholders})");
        $stmt->execute($spvIds);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['rec_id']] = $row['spv_name'];
        }
        return $map;
    }

    private function getCRCUploadedAdminNos(string $date): array
    {
        $stmt = $this->pdoRun->prepare("
            SELECT DISTINCT f.nomor_admin
            FROM runit_filing_system f
            JOIN runit_filing_files ff ON ff.filing_id = f.rec_id
            WHERE ff.file_name LIKE '%CRC%' AND DATE(ff.uploaded_at) = ?
        ");
        $stmt->execute([$date]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getBADoneAdminNos(string $date): array
    {
        $stmt = $this->pdoRun->prepare("
            SELECT DISTINCT f.nomor_admin
            FROM runit_filing_system f
            WHERE f.keterangan LIKE '%Berita Acara%' AND DATE(f.tanggal) = ?
        ");
        $stmt->execute([$date]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
