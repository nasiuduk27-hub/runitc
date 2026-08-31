<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ReportingController extends Controller
{
    public function index(Request $request): View|JsonResponse|Response
    {
        abort_unless($this->isSuperadmin($request), 403);

        if ($request->query->has('preview')) {
            return response()->json($this->preview($request));
        }

        if ($request->query->has('export')) {
            return $this->export($request);
        }

        return view('admin.reporting.index', [
            'distinctActions' => $this->distinctActions(),
            'defaultFrom' => now()->subDays(7)->toDateString(),
            'defaultTo' => now()->toDateString(),
        ]);
    }

    private function preview(Request $request): array
    {
        return match ($request->query('type')) {
            'audit_log', 'user_activity' => ['rows' => $this->auditRows($request, 20), 'total' => $this->auditCount($request)],
            default => ['error' => 'Unknown type'],
        };
    }

    private function export(Request $request): Response
    {
        return match ($request->query('type')) {
            'audit_log' => $this->csv(['Timestamp', 'Actor', 'Action', 'Target Type', 'Target ID', 'IP Address', 'Metadata'], $this->auditExportRows($request), 'audit_log_export_'.now()->format('Ymd_His').'.csv'),
            'user_activity' => $this->csv(['Timestamp', 'User', 'Action', 'Resource', 'Status', 'IP Address'], $this->userActivityExportRows($request), 'user_activity_export_'.now()->format('Ymd_His').'.csv'),
            'operational' => response($this->operationalHtml($request))->header('Content-Type', 'text/html; charset=utf-8'),
            default => response('Unknown report type', 422),
        };
    }

    private function auditRows(Request $request, int $limit): array
    {
        return $this->auditQuery($request)
            ->orderByDesc('al.created_at')
            ->orderByDesc('al.rec_id')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function auditCount(Request $request): int
    {
        return (clone $this->auditQuery($request))->count();
    }

    private function auditQuery(Request $request)
    {
        $query = DB::connection('run')
            ->table('sys_audit_log as al')
            ->leftJoin('sysitc_users as u', 'u.rec_id', '=', 'al.actor_user_id')
            ->select('al.*', 'u.account_nm');

        if ($request->filled('action')) {
            $query->where('al.action', $request->query('action'));
        }
        if ($request->filled('date_from')) {
            $query->where('al.created_at', '>=', $request->query('date_from').' 00:00:00');
        }
        if ($request->filled('date_to')) {
            $query->where('al.created_at', '<=', $request->query('date_to').' 23:59:59');
        }

        return $query;
    }

    private function auditExportRows(Request $request): array
    {
        return array_map(fn ($r) => [$r['created_at'] ?? '', $r['account_nm'] ?? 'Unknown', $r['action'] ?? '', $r['target_type'] ?? '', $r['target_id'] ?? '', $r['ip_address'] ?? '', $r['metadata_json'] ?? ''], $this->auditRows($request, 99999));
    }

    private function userActivityExportRows(Request $request): array
    {
        return array_map(fn ($r) => [$r['created_at'] ?? '', $r['account_nm'] ?? 'Unknown', $r['action'] ?? '', ($r['target_type'] ?? '').(! empty($r['target_id']) ? '#'.$r['target_id'] : ''), 'success', $r['ip_address'] ?? ''], $this->auditRows($request, 99999));
    }

    private function csv(array $headers, array $rows, string $filename): Response
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($handle, $headers, ',', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '\\');
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    private function operationalHtml(Request $request): string
    {
        $date = e((string) $request->query('date', now()->toDateString()));

        return '<!doctype html><html><head><meta charset="utf-8"><title>Operational Report</title><style>body{font-family:sans-serif;padding:24px;font-size:13px}h1{color:#1D4ED8}.card{border:1px solid #ddd;border-radius:10px;padding:16px;margin:12px 0}</style></head><body><h1>Operational Summary Report</h1><p>Date: '.$date.' | Generated: '.now()->format('Y-m-d H:i:s').'</p><div class="card">Operational export shell is available. Detailed CBT operational metrics remain served by the legacy operational modules until that module is migrated.</div></body></html>';
    }

    private function distinctActions(): array
    {
        return DB::connection('run')->table('sys_audit_log')->distinct()->orderBy('action')->pluck('action')->all();
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
