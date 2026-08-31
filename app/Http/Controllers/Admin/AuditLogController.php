<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($this->isSuperadmin($request), 403);
        $this->ensureTable();

        $filters = [
            'action' => trim((string) $request->query('action', '')),
            'search' => trim((string) $request->query('search', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            'actor_user_id' => trim((string) $request->query('actor_user_id', '')),
        ];

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;
        $query = DB::connection('run')
            ->table('sys_audit_log as al')
            ->leftJoin('sysitc_users as u', 'u.rec_id', '=', 'al.actor_user_id')
            ->select('al.*', 'u.account_nm');

        $this->applyFilters($query, $filters);

        $totalQuery = clone $query;
        $total = $totalQuery->count();
        $logs = $query
            ->orderByDesc('al.created_at')
            ->orderByDesc('al.rec_id')
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get()
            ->all();

        return view('admin.system-access.audit-log', [
            'filters' => $filters,
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'totalPages' => max(1, (int) ceil($total / $perPage)),
            'distinctActions' => $this->getDistinctActions(),
        ]);
    }

    private function applyFilters($query, array $filters): void
    {
        if ($filters['action'] !== '') {
            $query->where('al.action', $filters['action']);
        }
        if ($filters['search'] !== '') {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search): void {
                $q->where('al.action', 'like', $search)
                    ->orWhere('al.target_type', 'like', $search)
                    ->orWhere('u.account_nm', 'like', $search)
                    ->orWhere('al.metadata_json', 'like', $search);
            });
        }
        if ($filters['date_from'] !== '') {
            $query->where('al.created_at', '>=', $filters['date_from'].' 00:00:00');
        }
        if ($filters['date_to'] !== '') {
            $query->where('al.created_at', '<=', $filters['date_to'].' 23:59:59');
        }
        if ($filters['actor_user_id'] !== '') {
            $query->where('al.actor_user_id', (int) $filters['actor_user_id']);
        }
    }

    private function getDistinctActions(): array
    {
        return DB::connection('run')
            ->table('sys_audit_log')
            ->distinct()
            ->whereNotNull('action')
            ->orderBy('action')
            ->pluck('action')
            ->all();
    }

    private function ensureTable(): void
    {
        DB::connection('run')->statement(
            'CREATE TABLE IF NOT EXISTS sys_audit_log (
                rec_id INT AUTO_INCREMENT PRIMARY KEY,
                actor_user_id INT NOT NULL,
                action VARCHAR(100) NOT NULL,
                target_type VARCHAR(80) NULL,
                target_id INT NULL,
                metadata_json TEXT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(500) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_actor (actor_user_id),
                INDEX idx_action (action),
                INDEX idx_created_at (created_at),
                INDEX idx_target (target_type, target_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
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
