<?php

namespace App\Support\Legacy;

class ParticipantRecap
{
    public static function getRecap(\PDO $pdo, \PDO $pdoRun, \PDO $pdoWar, int $userId, ?int $limit = null, array $filters = []): array
    {
        $canManage = TadAccess::canManageDistribution($pdoRun, $userId);
        $spvId = $canManage ? 0 : TadAccess::getSupervisorIdForUser($pdoRun, $userId);

        if (! $canManage && $spvId <= 0) {
            return [
                'rows' => [],
                'totals' => [
                    'total_dates' => 0,
                    'total_admins' => 0,
                    'total_participants' => 0,
                    'finished_participants' => 0,
                    'period_start' => null,
                    'period_end' => null,
                ],
                'can_view' => false,
            ];
        }

        [$dateWhere, $dateParams] = self::dateFilter($filters);
        $excludeAdminIds = self::normalizeAdminIds($filters['exclude_admin_ids'] ?? []);
        $excludeKeywords = self::parseExcludeKeywords($filters['exclude'] ?? '');

        $params = [];
        $scopeJoin = '';
        $scopeWhere = '';

        if (! $canManage) {
            $scopeJoin = 'JOIN t3sT5ub4dm1n sub_scope ON sub_scope.admin_id = a.rec_id AND sub_scope.spv_recid = ?';
            $scopeWhere = 'AND t.sub_adm_id = sub_scope.rec_id';
            $params[] = $spvId;
        }

        $params = array_merge($params, $dateParams);

        $stmt = $pdoWar->prepare("
            SELECT
                a.rec_id,
                a.admin_no,
                a.client_id,
                DATE(a.testdt) AS test_date,
                COUNT(t.rec_id) AS total_participants,
                SUM(CASE WHEN t.sub_adm_id != 0 AND t.statrec IN ('7','8','9','c','C') THEN 1 ELSE 0 END) AS finished_participants
            FROM t3sTAdm1n a
            {$scopeJoin}
            LEFT JOIN t3sTt4keR5 t ON t.admin_id = a.rec_id {$scopeWhere}
            WHERE a.statrec = '0'
              AND a.testdt IS NOT NULL
              {$dateWhere}
            GROUP BY a.rec_id, a.admin_no, a.client_id, DATE(a.testdt)
            ORDER BY DATE(a.testdt) ASC, a.admin_no ASC
        ");
        $stmt->execute($params);
        $adminRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $clientIds = array_values(array_unique(array_filter(array_map('intval', array_column($adminRows, 'client_id')))));
        $clientMap = [];

        if (! empty($clientIds)) {
            $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
            $stmtClients = $pdo->prepare("SELECT rec_id, clientnm FROM sys_mstclient WHERE rec_id IN ({$placeholders})");
            $stmtClients->execute($clientIds);

            foreach ($stmtClients->fetchAll(\PDO::FETCH_ASSOC) as $client) {
                $clientMap[(int) $client['rec_id']] = $client['clientnm'];
            }
        }

        $grouped = [];
        $totals = [
            'total_dates' => 0,
            'total_admins' => 0,
            'total_participants' => 0,
            'finished_participants' => 0,
            'period_start' => null,
            'period_end' => null,
        ];

        foreach ($adminRows as $admin) {
            $date = (string) ($admin['test_date'] ?? '');
            if ($date === '') {
                continue;
            }

            $clientName = $clientMap[(int) ($admin['client_id'] ?? 0)] ?? '-';
            $adminNo = trim((string) ($admin['admin_no'] ?? '-'));
            $itemText = $clientName.' / '.$adminNo;

            if (in_array((int) ($admin['rec_id'] ?? 0), $excludeAdminIds, true)) {
                continue;
            }

            if (self::isExcluded($itemText, $excludeKeywords)) {
                continue;
            }

            if (! isset($grouped[$date])) {
                $grouped[$date] = [
                    'date' => $date,
                    'items' => [],
                    'admin_count' => 0,
                    'total_participants' => 0,
                    'finished_participants' => 0,
                ];
            }

            $totalParticipants = (int) ($admin['total_participants'] ?? 0);
            $finishedParticipants = (int) ($admin['finished_participants'] ?? 0);

            $grouped[$date]['items'][] = [
                'admin_id' => (int) ($admin['rec_id'] ?? 0),
                'admin_no' => $adminNo,
                'client_name' => $clientName,
                'item_text' => $itemText,
                'total_participants' => $totalParticipants,
                'finished_participants' => $finishedParticipants,
            ];
            $grouped[$date]['admin_count']++;
            $grouped[$date]['total_participants'] += $totalParticipants;
            $grouped[$date]['finished_participants'] += $finishedParticipants;

            $totals['total_admins']++;
            $totals['total_participants'] += $totalParticipants;
            $totals['finished_participants'] += $finishedParticipants;
        }

        $rows = array_values($grouped);
        $totals['total_dates'] = count($rows);
        $totals['period_start'] = $rows[0]['date'] ?? null;
        $totals['period_end'] = ! empty($rows) ? $rows[count($rows) - 1]['date'] : null;

        foreach ($rows as &$row) {
            $row['items_text'] = implode(', ', array_map(static function ($item) {
                return $item['item_text'] ?? '-';
            }, $row['items']));
        }
        unset($row);

        if ($limit !== null && $limit > 0) {
            $rows = array_slice($rows, 0, $limit);
        }

        return [
            'rows' => $rows,
            'totals' => $totals,
            'can_view' => true,
        ];
    }

    public static function getRecapExcludeOptions(\PDO $pdo, \PDO $pdoRun, \PDO $pdoWar, int $userId, array $filters = []): array
    {
        $canManage = TadAccess::canManageDistribution($pdoRun, $userId);
        $spvId = $canManage ? 0 : TadAccess::getSupervisorIdForUser($pdoRun, $userId);

        if (! $canManage && $spvId <= 0) {
            return [];
        }

        [$dateWhere, $dateParams] = self::dateFilter($filters);
        $params = [];
        $scopeJoin = '';

        if (! $canManage) {
            $scopeJoin = 'JOIN t3sT5ub4dm1n sub_scope ON sub_scope.admin_id = a.rec_id AND sub_scope.spv_recid = ?';
            $params[] = $spvId;
        }

        $params = array_merge($params, $dateParams);

        $stmt = $pdoWar->prepare("
            SELECT DISTINCT a.rec_id, a.admin_no, a.client_id, DATE(a.testdt) AS test_date
            FROM t3sTAdm1n a
            {$scopeJoin}
            WHERE a.statrec = '0'
              AND a.testdt IS NOT NULL
              {$dateWhere}
            ORDER BY DATE(a.testdt) ASC, a.admin_no ASC
            LIMIT 300
        ");
        $stmt->execute($params);
        $admins = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $clientIds = array_values(array_unique(array_filter(array_map('intval', array_column($admins, 'client_id')))));
        $clientMap = [];

        if (! empty($clientIds)) {
            $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
            $stmtClients = $pdo->prepare("SELECT rec_id, clientnm FROM sys_mstclient WHERE rec_id IN ({$placeholders})");
            $stmtClients->execute($clientIds);

            foreach ($stmtClients->fetchAll(\PDO::FETCH_ASSOC) as $client) {
                $clientMap[(int) $client['rec_id']] = $client['clientnm'];
            }
        }

        $options = [];
        foreach ($admins as $admin) {
            $clientName = $clientMap[(int) ($admin['client_id'] ?? 0)] ?? '-';
            $adminNo = trim((string) ($admin['admin_no'] ?? '-'));
            $date = ! empty($admin['test_date']) ? date('d M Y', strtotime($admin['test_date'])) : '-';

            $options[] = [
                'rec_id' => (int) $admin['rec_id'],
                'label' => $clientName.' / '.$adminNo.' ('.$date.')',
            ];
        }

        return $options;
    }

    private static function dateFilter(array $filters): array
    {
        $isRange = ! empty($filters['is_range']);

        if (! $isRange) {
            $date = self::normalizeDate($filters['date'] ?? null);

            if ($date !== null) {
                return ['AND DATE(a.testdt) = ?', [$date]];
            }

            return ['AND DATE(a.testdt) >= CURDATE()', []];
        }

        $startDate = self::normalizeDate($filters['start_date'] ?? null);
        $endDate = self::normalizeDate($filters['end_date'] ?? null);

        if ($startDate !== null && $endDate !== null && $startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        if ($startDate !== null && $endDate !== null) {
            return ['AND DATE(a.testdt) BETWEEN ? AND ?', [$startDate, $endDate]];
        }

        if ($startDate !== null) {
            return ['AND DATE(a.testdt) >= ?', [$startDate]];
        }

        if ($endDate !== null) {
            return ['AND DATE(a.testdt) <= ?', [$endDate]];
        }

        return ['AND DATE(a.testdt) >= CURDATE()', []];
    }

    private static function normalizeAdminIds($value): array
    {
        $values = is_array($value) ? $value : explode(',', (string) $value);
        $ids = [];

        foreach ($values as $id) {
            $id = (int) $id;

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private static function normalizeDate($value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    private static function parseExcludeKeywords($value): array
    {
        $keywords = [];

        foreach (explode(',', (string) $value) as $keyword) {
            $keyword = strtolower(trim($keyword));

            if ($keyword !== '') {
                $keywords[] = $keyword;
            }
        }

        return array_values(array_unique($keywords));
    }

    private static function isExcluded(string $text, array $excludeKeywords): bool
    {
        if (empty($excludeKeywords)) {
            return false;
        }

        $haystack = strtolower($text);

        foreach ($excludeKeywords as $keyword) {
            if ($keyword !== '' && str_contains($haystack, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
