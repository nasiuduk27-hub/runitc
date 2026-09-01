<?php

namespace App\Http\Controllers\CbtOps;

use App\Http\Controllers\Controller;
use App\Support\Legacy\ParticipantRecap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestAdminController extends Controller
{
    public function index(Request $request)
    {

        $legacyController = new \App\Support\Legacy\TestAdminController(
            DB::connection('mysql')->getPdo(),
            DB::connection('run')->getPdo(),
            DB::connection('war')->getPdo(),
        );

        if ($request->isMethod('POST') && $request->input('action')) {
            $legacyController->handle(false);

            $flash = $legacyController->getFlash();

            if ($flash) {
                $request->session()->flash('cbt_ops_test_admin_'.$flash['type'], $flash['text']);
            }

            $target = $legacyController->getRedirectTarget() ?: $request->fullUrl();

            return redirect($target);
        }

        $data = $legacyController->handle();

        return view('cbt-ops.test-admin.index', $data + [
            'buildPageUrl' => fn (int $page) => $this->buildPageUrl($request, $page),
        ]);
    }

    public function participantRecapPrint(Request $request)
    {

        $filters = [
            'date' => $request->query('recap_date', ''),
            'is_range' => $request->has('recap_is_range') ? 1 : 0,
            'start_date' => $request->query('recap_start_date', ''),
            'end_date' => $request->query('recap_end_date', ''),
            'exclude_admin_ids' => $request->query('recap_exclude_admin_ids', []),
            'exclude' => $request->query('recap_exclude', ''),
        ];

        try {
            $recap = ParticipantRecap::getRecap(
                DB::connection('mysql')->getPdo(),
                DB::connection('run')->getPdo(),
                DB::connection('war')->getPdo(),
                (int) auth_user_id(),
                null,
                $filters,
            );
        } catch (\Throwable) {
            $recap = [
                'rows' => [],
                'totals' => [],
                'can_view' => false,
            ];
        }

        return view('cbt-ops.test-admin.participant-recap-print', [
            'rows' => $recap['rows'] ?? [],
            'totals' => $recap['totals'] ?? [],
            'canView' => (bool) ($recap['can_view'] ?? false),
            'filters' => $filters,
            'printedBy' => (string) ($request->session()->get('user_name') ?: $request->session()->get('account_nm', 'User')),
        ]);
    }

    private function buildPageUrl(Request $request, int $page): string
    {
        return url('/cbt-ops/test-admin').'?'.http_build_query(array_merge($request->query(), ['page' => $page]));
    }
}
