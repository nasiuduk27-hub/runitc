<?php

namespace App\Http\Controllers\Cooperative;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CooperativeAuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'action' => trim((string) $request->query('action', '')),
            'search' => trim((string) $request->query('search', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
        ];

        $query = DB::connection('run')
            ->table('sys_audit_log as al')
            ->leftJoin('sysitc_users as u', 'u.rec_id', '=', 'al.actor_user_id')
            ->where('al.action', 'like', 'cooperative.%')
            ->select('al.*', 'u.account_nm');

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

        return view('cooperative.audit-log.index', [
            'filters' => $filters,
            'logs' => $query->orderByDesc('al.created_at')->orderByDesc('al.rec_id')->paginate(50)->withQueryString(),
            'distinctActions' => DB::connection('run')->table('sys_audit_log')
                ->where('action', 'like', 'cooperative.%')->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
