<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleMenuController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($this->isSuperadmin($request), 403);

        $roles = $this->getRoles();
        $selectedRoleId = (int) $request->query('role_id', $roles[0]->rec_id ?? 0);
        $selectedRole = $selectedRoleId > 0 ? DB::connection('run')->table('sysitc_grpacc')->where('rec_id', $selectedRoleId)->first() : null;

        return view('admin.system-access.role-menu', [
            'roles' => $roles,
            'selectedRole' => $selectedRole,
            'selectedRoleId' => $selectedRoleId,
            'menuTree' => $this->getMenuTree(),
            'checkedMenuIds' => $selectedRoleId > 0 ? $this->getMenuAccessByRole($selectedRoleId) : [],
            'adminMenuIds' => $selectedRole && $this->isSuperadminRole($selectedRole) ? $this->getAdminMenuIds() : [],
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        abort_unless($this->isSuperadmin($request), 403);

        $validated = $request->validate([
            'grpacc_id' => ['required', 'integer', 'min:1'],
            'menu_ids' => ['array'],
            'menu_ids.*' => ['integer', 'min:1'],
        ]);

        $role = DB::connection('run')->table('sysitc_grpacc')->where('rec_id', $validated['grpacc_id'])->first();
        if (! $role) {
            return back()->with('error_msg', 'Role tidak ditemukan.');
        }

        $menuIds = array_map('intval', $validated['menu_ids'] ?? []);
        $menuIds = $this->ensureParentMenuIds($menuIds);

        if ($this->isSuperadminRole($role)) {
            $menuIds = array_values(array_unique(array_merge($menuIds, $this->getAdminMenuIds())));
        }

        $roleIds = $this->getRoleIdsByCode($role->grpaccess, $role->grpacc);
        if (empty($roleIds)) {
            $roleIds = [(int) $role->rec_id];
        }

        DB::connection('run')->transaction(function () use ($roleIds, $menuIds): void {
            DB::connection('run')->table('sys_menu_access')->whereIn('grpacc_id', $roleIds)->delete();

            $rows = [];
            foreach ($roleIds as $roleId) {
                foreach ($menuIds as $menuId) {
                    $rows[] = ['grpacc_id' => $roleId, 'menu_id' => $menuId];
                }
            }

            if (! empty($rows)) {
                DB::connection('run')->table('sys_menu_access')->insert($rows);
            }
        });

        return back()->with('success_msg', 'Akses menu berhasil disimpan.');
    }

    private function getRoles(): array
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

    private function getMenuTree(): array
    {
        $menus = DB::connection('run')
            ->table('sys_menus')
            ->select('rec_id', 'mst_id', 'title', 'url', 'icon', 'is_global', 'is_active')
            ->where('is_active', 1)
            ->orderBy('mst_id')
            ->orderBy('sort_order')
            ->orderBy('rec_id')
            ->get()
            ->map(fn ($row) => (array) $row + ['children' => []])
            ->all();

        return $this->buildMenuTree($menus);
    }

    private function buildMenuTree(array $menus, int $parentId = 0): array
    {
        $branch = [];
        foreach ($menus as $menu) {
            if ((int) $menu['mst_id'] === $parentId) {
                $menu['children'] = $this->buildMenuTree($menus, (int) $menu['rec_id']);
                $branch[] = $menu;
            }
        }

        return $branch;
    }

    private function getMenuAccessByRole(int $roleId): array
    {
        $role = DB::connection('run')->table('sysitc_grpacc')->where('rec_id', $roleId)->first();
        if (! $role) {
            return [];
        }

        $roleIds = $this->getRoleIdsByCode($role->grpaccess, $role->grpacc);
        if (empty($roleIds)) {
            $roleIds = [$roleId];
        }

        return DB::connection('run')->table('sys_menu_access')->whereIn('grpacc_id', $roleIds)->distinct()->pluck('menu_id')->map(fn ($id) => (int) $id)->all();
    }

    private function ensureParentMenuIds(array $menuIds): array
    {
        if (empty($menuIds)) {
            return [];
        }

        $parents = DB::connection('run')->table('sys_menus')->pluck('mst_id', 'rec_id')->map(fn ($id) => (int) $id)->all();
        $result = array_values(array_unique($menuIds));

        foreach ($menuIds as $menuId) {
            $current = (int) $menuId;
            while (! empty($parents[$current])) {
                $parent = (int) $parents[$current];
                if (! in_array($parent, $result, true)) {
                    $result[] = $parent;
                }
                $current = $parent;
            }
        }

        return array_values(array_unique($result));
    }

    private function getAdminMenuIds(): array
    {
        return DB::connection('run')
            ->table('sys_menus')
            ->where('is_active', 1)
            ->where(function ($q): void {
                $q->where('url', 'like', '/modules/admin/%')->orWhere('url', 'like', 'modules/admin/%');
            })
            ->pluck('rec_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function getRoleIdsByCode(string $grpaccess, string $grpacc): array
    {
        return DB::connection('run')->table('sysitc_grpacc')
            ->where('grpaccess', $grpaccess)
            ->where('grpacc', $grpacc)
            ->pluck('rec_id')
            ->map(fn ($id) => (int) $id)
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

    private function isSuperadminRole(object $role): bool
    {
        return ($role->grpaccess ?? '') === '03'
            && ($role->grpacc ?? '') === '999'
            && stripos((string) ($role->grpdesc ?? ''), 'SUPER') !== false
            && stripos((string) ($role->grpdesc ?? ''), 'ADMIN') !== false;
    }
}
