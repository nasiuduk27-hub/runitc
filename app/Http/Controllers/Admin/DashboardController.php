<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($this->isSuperadmin($request), 403);

        return view('admin.dashboard', [
            'stats' => $this->stats(),
            'cbt' => $this->cbtSummary(),
            'filing' => $this->filingSummary(),
            'health' => $this->healthSummary(),
            'activities' => $this->recentActivities(),
        ]);
    }

    private function stats(): array
    {
        return [
            'total_users' => $this->count('sysitc_users'),
            'active_users' => $this->count('sysitc_users', ['status' => 1]),
            'inactive_users' => DB::connection('run')->table('sysitc_users')->where('status', '!=', 1)->count(),
            'users_without_role' => DB::connection('run')->table('sysitc_users as u')->whereNotExists(function ($q): void {
                $q->selectRaw('1')->from('sysitc_usracc as ua')->whereColumn('ua.user_rec_id', 'u.rec_id')->whereIn('ua.access_code', ['01', '03', '04']);
            })->distinct('u.rec_id')->count('u.rec_id'),
            'total_roles' => $this->count('sysitc_grpacc'),
            'active_menus' => $this->count('sys_menus', ['is_active' => 1]),
        ];
    }

    private function cbtSummary(): array
    {
        $summary = ['rooms' => 0, 'completed_rooms' => 0, 'participants' => 0, 'assigned' => 0, 'unassigned' => 0];
        try {
            $rooms = DB::connection('war')->select(
                "SELECT a.rec_id,
                        (SELECT COUNT(*) FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id) AS total_participants,
                        (SELECT COUNT(*) FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.sub_adm_id != 0) AS assigned_participants,
                        (SELECT COUNT(*) FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.statrec IN ('7','8','9','c','C')) AS finished_participants
                 FROM t3sTAdm1n a
                 WHERE DATE(a.testdt) = CURDATE()"
            );

            foreach ($rooms as $room) {
                $summary['rooms']++;
                $total = (int) $room->total_participants;
                $assigned = (int) $room->assigned_participants;
                $finished = (int) $room->finished_participants;
                $summary['participants'] += $total;
                $summary['assigned'] += $assigned;
                if ($total > 0 && $total === $finished) {
                    $summary['completed_rooms']++;
                }
            }
            $summary['unassigned'] = max(0, $summary['participants'] - $summary['assigned']);
        } catch (Throwable) {
        }

        return $summary;
    }

    private function filingSummary(): array
    {
        try {
            return (array) DB::connection('run')->selectOne('SELECT COUNT(*) AS total_uploads, SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today_uploads FROM runit_filing_files');
        } catch (Throwable) {
            return ['total_uploads' => 0, 'today_uploads' => 0];
        }
    }

    private function healthSummary(): array
    {
        $items = [];
        foreach (['mysql' => 'Main DB', 'bot' => 'Bot DB', 'run' => 'Run DB', 'war' => 'War DB'] as $connection => $label) {
            try {
                DB::connection($connection)->select('SELECT 1');
                $items[$label] = ['status' => 'ok', 'note' => 'Connected'];
            } catch (Throwable) {
                $items[$label] = ['status' => 'down', 'note' => 'Connection failed'];
            }
        }

        return $items;
    }

    private function recentActivities(): array
    {
        try {
            return DB::connection('run')
                ->table('sys_audit_log as al')
                ->leftJoin('sysitc_users as u', 'u.rec_id', '=', 'al.actor_user_id')
                ->select('al.action', 'al.target_type', 'al.target_id', 'al.created_at', 'u.account_nm')
                ->orderByDesc('al.created_at')
                ->limit(8)
                ->get()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function count(string $table, array $where = []): int
    {
        $query = DB::connection('run')->table($table);
        foreach ($where as $column => $value) {
            $query->where($column, $value);
        }

        return $query->count();
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
