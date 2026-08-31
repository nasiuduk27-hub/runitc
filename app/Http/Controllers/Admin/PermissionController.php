<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($this->isSuperadmin($request), 403);

        return view('admin.system-access.permissions', [
            'roles' => $this->roles(),
            'permissions' => $this->permissions(),
        ]);
    }

    private function roles(): array
    {
        return DB::connection('run')
            ->table('sysitc_grpacc as g')
            ->select('g.*')
            ->selectSub(function ($q): void {
                $q->from('sys_menu_access as ma')->selectRaw('COUNT(*)')->whereColumn('ma.grpacc_id', 'g.rec_id');
            }, 'menu_count')
            ->orderBy('g.grpaccess')
            ->orderBy('g.grpacc')
            ->get()
            ->all();
    }

    private function permissions(): array
    {
        return DB::connection('run')
            ->table('sys_menus as m')
            ->leftJoin('sys_menu_access as ma', 'ma.menu_id', '=', 'm.rec_id')
            ->leftJoin('sysitc_grpacc as g', 'g.rec_id', '=', 'ma.grpacc_id')
            ->where('m.is_active', 1)
            ->groupBy('m.rec_id', 'm.mst_id', 'm.title', 'm.url', 'm.is_global')
            ->orderBy('m.mst_id')
            ->orderBy('m.sort_order')
            ->orderBy('m.rec_id')
            ->get([
                'm.rec_id as menu_id',
                'm.mst_id',
                'm.title',
                'm.url',
                'm.is_global',
                DB::raw("GROUP_CONCAT(DISTINCT g.grpdesc ORDER BY g.grpdesc SEPARATOR ', ') AS role_names"),
            ])
            ->all();
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
