<?php

namespace App\Http\Controllers\CreditUnion;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreditUnionAuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('credit-union.audit-log.index', [
            'filters' => $filters,
            'logs' => $this->buildQuery($filters)->orderByDesc('al.created_at')->orderByDesc('al.rec_id')->paginate(50)->withQueryString(),
            'distinctActions' => $this->distinctActions(),
        ]);
    }

    public function print(Request $request): View
    {
        $filters = $this->filters($request);

        return view('credit-union.audit-log.print', [
            'filters' => $filters,
            'logs' => $this->buildQuery($filters)->orderByDesc('al.created_at')->orderByDesc('al.rec_id')->get(),
            'generatedAt' => now(),
        ]);
    }

    /**
     * @return array{action: string, search: string, date_from: string, date_to: string}
     */
    private function filters(Request $request): array
    {
        return [
            'action' => trim((string) $request->query('action', '')),
            'search' => trim((string) $request->query('search', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
        ];
    }

    /**
     * @param  array{action: string, search: string, date_from: string, date_to: string}  $filters
     */
    private function buildQuery(array $filters)
    {
        $query = DB::connection('run')
            ->table('sys_audit_log as al')
            ->leftJoin('sysitc_users as u', 'u.rec_id', '=', 'al.actor_user_id')
            ->where('al.action', 'like', 'cu.%')
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

        return $query;
    }

    /**
     * @return Collection<int, string>
     */
    private function distinctActions()
    {
        return DB::connection('run')->table('sys_audit_log')
            ->where('action', 'like', 'cu.%')->distinct()->orderBy('action')->pluck('action');
    }
}
