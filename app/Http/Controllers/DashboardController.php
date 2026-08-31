<?php

namespace App\Http\Controllers;

use App\Support\Legacy\ParticipantRecap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $userId = (int) session('user_id', 0);
        $recap = $this->tadParticipantRecap($request, $userId);

        return view('dashboard', [
            'activeRooms' => $this->scalar('war', 'SELECT COUNT(*) FROM t3sTAdm1n WHERE DATE(testdt) = CURDATE()'),
            'totalParticipants' => $this->scalar('war', 'SELECT COUNT(*) FROM t3sTt4keR5 t JOIN t3sTAdm1n a ON a.rec_id = t.admin_id WHERE DATE(a.testdt) = CURDATE()'),
            'crcPending' => $this->scalar('run', 'SELECT COUNT(DISTINCT f.rec_id) FROM runit_filing_system f LEFT JOIN runit_filing_files ff ON ff.filing_id = f.rec_id WHERE ff.file_id IS NULL'),
            'profileCompletionPercentage' => $this->profileCompletionPercentage($userId),
            'isSuperAdmin' => $this->isSuperAdmin($userId),
            'canViewTadRecap' => $this->canViewTadRecap($userId),
            'tadWidgetMode' => $this->tadWidgetMode($userId),
            'tadWidgetRows' => $this->tadWidgetRows($userId),
            'tadWidgetTotal' => $this->tadWidgetTotal($userId),
            'tadRecapRows' => $recap['rows'],
            'tadRecapTotals' => $recap['totals'],
            'tadRecapCanView' => $recap['can_view'],
            'tadRecapFilters' => $recap['filters'],
            'tadRecapExcludeIds' => $this->normalizeExcludeIds($recap['filters']['exclude_admin_ids'] ?? []),
            'tadRecapExcludeOptions' => $recap['exclude_options'],
        ]);
    }

    private function tadParticipantRecap(Request $request, int $userId): array
    {
        $filters = [
            'date' => (string) $request->query('recap_date', ''),
            'is_range' => $request->query('recap_is_range') ? 1 : 0,
            'start_date' => (string) $request->query('recap_start_date', ''),
            'end_date' => (string) $request->query('recap_end_date', ''),
            'exclude' => (string) $request->query('recap_exclude', ''),
            'exclude_admin_ids' => (array) $request->query('recap_exclude_admin_ids', []),
        ];

        $empty = [
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
            'exclude_options' => [],
        ];

        if (! $this->canViewTadRecap($userId)) {
            return $empty + ['filters' => $filters];
        }

        try {
            $this->loadRecapDependencies();

            $recap = ParticipantRecap::getRecap(
                DB::connection('mysql')->getPdo(),
                DB::connection('run')->getPdo(),
                DB::connection('war')->getPdo(),
                $userId,
                10,
                $filters,
            );

            $recap['exclude_options'] = ParticipantRecap::getRecapExcludeOptions(
                DB::connection('mysql')->getPdo(),
                DB::connection('run')->getPdo(),
                DB::connection('war')->getPdo(),
                $userId,
                $filters,
            );

            return $recap + ['filters' => $filters];
        } catch (Throwable) {
            return $empty + ['filters' => $filters];
        }
    }

    private function normalizeExcludeIds($value): array
    {
        $values = is_array($value) ? $value : explode(',', (string) $value);
        $ids = [];

        foreach ($values as $item) {
            $id = (int) $item;

            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private function loadRecapDependencies(): void
    {
        if (! defined('BASE_PATH')) {
            define('BASE_PATH', base_path());
        }
    }

    private function tadWidgetMode(int $userId): ?string
    {
        if ($this->canManageTadDistribution($userId)) {
            return 'manage';
        }

        if ($this->isTadSpv($userId)) {
            return 'assigned';
        }

        return null;
    }

    private function canManageTadDistribution(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        try {
            return (int) DB::connection('run')->selectOne(
                "SELECT COUNT(*) AS aggregate
                 FROM sysitc_usracc ua
                 JOIN sysitc_grpacc g
                   ON g.grpaccess = ua.access_code
                  AND g.grpacc = ua.access_account
                 WHERE ua.user_rec_id = ?
                   AND (UPPER(g.grpdesc) LIKE '%TAD%ADMIN%'
                     OR UPPER(g.grpdesc) LIKE '%TAD%STAFF%'
                     OR (g.grpaccess = '03' AND g.grpacc = '999' AND UPPER(g.grpdesc) LIKE '%SUPER%ADMIN%'))",
                [$userId]
            )->aggregate > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function isTadSpv(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        try {
            return (int) DB::connection('run')->selectOne(
                "SELECT COUNT(*) AS aggregate
                 FROM sysitc_usracc ua
                 JOIN sysitc_grpacc g
                   ON g.grpaccess = ua.access_code
                  AND g.grpacc = ua.access_account
                 WHERE ua.user_rec_id = ?
                   AND UPPER(g.grpdesc) LIKE '%TAD%SPV%'",
                [$userId]
            )->aggregate > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function getTadSupervisorIdForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        try {
            return (int) DB::connection('run')->selectOne(
                'SELECT rec_id FROM tad_supervisor WHERE itc_usr_id = ? AND status = 1 LIMIT 1',
                [$userId]
            )->rec_id;
        } catch (Throwable) {
            return 0;
        }
    }

    private function tadWidgetRows(int $userId): array
    {
        $mode = $this->tadWidgetMode($userId);

        if (! $mode) {
            return [];
        }

        try {
            if ($mode === 'assigned') {
                $spvId = $this->getTadSupervisorIdForUser($userId);

                if ($spvId <= 0) {
                    return [];
                }

                $rows = DB::connection('war')->select(
                    "SELECT
                        a.rec_id,
                        a.admin_no,
                        a.client_id,
                        a.testdt,
                        sub.batch_no,
                        sub.authorize_amt,
                        (SELECT COUNT(*) FROM t3sTt4keR5 t WHERE t.sub_adm_id = sub.rec_id) AS assigned_takers
                     FROM t3sT5ub4dm1n sub
                     JOIN t3sTAdm1n a ON a.rec_id = sub.admin_id
                     WHERE a.statrec = '0'
                       AND sub.spv_recid = ?
                       AND DATE(a.testdt) >= CURDATE()
                     ORDER BY a.testdt ASC, a.admin_no ASC, sub.batch_no ASC
                     LIMIT 5",
                    [$spvId]
                );
            } else {
                $rows = DB::connection('war')->select(
                    "SELECT
                        a.rec_id,
                        a.admin_no,
                        a.client_id,
                        a.testdt,
                        (SELECT COUNT(*) FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id) AS total_takers,
                        GROUP_CONCAT(DISTINCT sub.spv_recid ORDER BY sub.batch_no ASC SEPARATOR ',') AS spv_ids
                     FROM t3sTAdm1n a
                     LEFT JOIN t3sT5ub4dm1n sub ON sub.admin_id = a.rec_id
                     WHERE a.statrec = '0'
                       AND DATE(a.testdt) >= CURDATE()
                     GROUP BY a.rec_id, a.admin_no, a.client_id, a.testdt
                     ORDER BY a.testdt ASC, a.admin_no ASC
                     LIMIT 5"
                );
            }

            return $this->hydrateTadWidgetRows($rows);
        } catch (Throwable) {
            return [];
        }
    }

    private function tadWidgetTotal(int $userId): int
    {
        $mode = $this->tadWidgetMode($userId);

        if (! $mode) {
            return 0;
        }

        try {
            if ($mode === 'assigned') {
                $spvId = $this->getTadSupervisorIdForUser($userId);

                if ($spvId <= 0) {
                    return 0;
                }

                return (int) DB::connection('war')->selectOne(
                    "SELECT COUNT(*)
                     FROM t3sT5ub4dm1n sub
                     JOIN t3sTAdm1n a ON a.rec_id = sub.admin_id
                     WHERE a.statrec = '0'
                       AND sub.spv_recid = ?
                       AND DATE(a.testdt) >= CURDATE()",
                    [$spvId]
                )->{'COUNT(*)'};
            }

            return (int) DB::connection('war')->selectOne(
                "SELECT COUNT(*)
                 FROM t3sTAdm1n a
                 WHERE a.statrec = '0'
                   AND DATE(a.testdt) >= CURDATE()"
            )->{'COUNT(*)'};
        } catch (Throwable) {
            return 0;
        }
    }

    private function hydrateTadWidgetRows(array $rows): array
    {
        $rows = array_map(static fn ($row) => (array) $row, $rows);

        if ($rows === []) {
            return [];
        }

        $clientIds = array_values(array_unique(array_filter(array_map(static fn ($row) => (int) ($row['client_id'] ?? 0), $rows))));
        $clientMap = [];

        if ($clientIds !== []) {
            try {
                $clientRows = DB::connection('mysql')->select(
                    'SELECT rec_id, clientnm FROM sys_mstclient WHERE rec_id IN ('.implode(',', array_fill(0, count($clientIds), '?')).')',
                    $clientIds
                );

                foreach ($clientRows as $client) {
                    $clientMap[(int) $client->rec_id] = $client->clientnm;
                }
            } catch (Throwable) {
            }
        }

        foreach ($rows as &$row) {
            $row['client_nm'] = $clientMap[(int) ($row['client_id'] ?? 0)] ?? '-';

            if (isset($row['spv_ids'])) {
                $spvIds = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $row['spv_ids'])))));
                $spvNames = [];

                if ($spvIds !== []) {
                    try {
                        $spvRows = DB::connection('run')->select(
                            'SELECT rec_id, spv_name FROM tad_supervisor WHERE rec_id IN ('.implode(',', array_fill(0, count($spvIds), '?')).')',
                            $spvIds
                        );

                        foreach ($spvRows as $spv) {
                            $spvNames[(int) $spv->rec_id] = $spv->spv_name;
                        }
                    } catch (Throwable) {
                    }
                }

                $row['spv_names'] = implode(', ', array_filter(array_map(
                    static fn ($id) => $spvNames[(int) $id] ?? null,
                    $spvIds
                )));
            }
        }
        unset($row);

        return $rows;
    }

    private function canViewTadRecap(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        try {
            return (int) DB::connection('run')->selectOne(
                "SELECT COUNT(*) AS aggregate
                 FROM sysitc_usracc ua
                 JOIN sysitc_grpacc g
                   ON g.grpaccess = ua.access_code
                  AND g.grpacc = ua.access_account
                 WHERE ua.user_rec_id = ?
                   AND (UPPER(g.grpdesc) LIKE '%TAD%ADMIN%'
                     OR UPPER(g.grpdesc) LIKE '%TAD%STAFF%'
                     OR (g.grpaccess = '03' AND g.grpacc = '999' AND UPPER(g.grpdesc) LIKE '%SUPER%ADMIN%'))",
                [$userId]
            )->aggregate > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function isSuperAdmin(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        try {
            return (int) DB::connection('run')->selectOne(
                "SELECT COUNT(*) AS aggregate
                 FROM sysitc_usracc ua
                 JOIN sysitc_grpacc g
                   ON g.grpaccess = ua.access_code
                  AND g.grpacc = ua.access_account
                 WHERE ua.user_rec_id = ?
                   AND g.grpaccess = '03'
                   AND g.grpacc = '999'
                   AND g.grpdesc LIKE '%SUPER%ADMIN%'",
                [$userId]
            )->aggregate > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function scalar(string $connection, string $sql): int
    {
        try {
            $row = DB::connection($connection)->selectOne($sql);

            return (int) collect((array) $row)->first();
        } catch (Throwable) {
            return 0;
        }
    }

    private function profileCompletionPercentage(int $userId): int
    {
        if ($userId <= 0) {
            return 100;
        }

        try {
            $profile = DB::connection('run')->selectOne(
                'SELECT log.account_id, mst.* FROM sysitc_users mst JOIN sysitc_login log ON mst.login_rec_id = log.rec_id WHERE mst.rec_id = ? LIMIT 1',
                [$userId]
            );

            if (! $profile) {
                return 100;
            }

            $profile = (array) $profile;
            $total = 0;
            $filled = 0;

            foreach (['account_id', 'account_nm', 'dob', 'sexmf', 'whatsapp', 'address', 'prov_cd', 'kotakabupaten'] as $field) {
                $total++;
                if (! empty($profile[$field]) && trim((string) $profile[$field]) !== '-') {
                    $filled++;
                }
            }

            $total++;
            foreach (['png', 'jpg', 'jpeg', 'gif'] as $ext) {
                if (file_exists(public_path('assets/personal/user_'.$userId.'.'.$ext))) {
                    $filled++;
                    break;
                }
            }

            $total++;
            $email = DB::connection('run')->table('sysitc_usermail')->where('user_recid', $userId)->orderByDesc('asdefault')->orderBy('email')->value('email');
            if (trim((string) $email) !== '') {
                $filled++;
            }

            $bank = (array) (DB::connection('run')->table('sysitc_userbank')->where('user_recid', $userId)->orderByDesc('asdefault')->orderBy('bnkcd')->orderBy('accno')->first() ?: []);
            foreach (['bnkcd', 'accnm', 'accno'] as $field) {
                $total++;
                if (! empty($bank[$field]) && trim((string) $bank[$field]) !== '-') {
                    $filled++;
                }
            }

            return $total > 0 ? (int) round(($filled / $total) * 100) : 100;
        } catch (Throwable) {
            return 100;
        }
    }
}
