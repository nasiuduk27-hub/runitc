<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($this->isSuperadmin($request), 403);

        return view('admin.system-access.roles', [
            'roles' => $this->getRoles(),
            'users' => $this->getUsersWithRoles(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->isSuperadmin($request), 403);

        $validated = $request->validate([
            'grpaccess' => ['required', 'string', 'max:10'],
            'grpacc' => ['required', 'string', 'max:10'],
            'grpdesc' => ['required', 'string', 'max:100'],
        ]);

        DB::connection('run')->table('sysitc_grpacc')->insert([
            'grpaccess' => trim($validated['grpaccess']),
            'grpacc' => trim($validated['grpacc']),
            'grpdesc' => trim($validated['grpdesc']),
        ]);

        return back()->with('success_msg', 'Role berhasil ditambahkan.');
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($this->isSuperadmin($request), 403);

        $validated = $request->validate([
            'role_id' => ['required', 'integer', 'min:1'],
            'grpdesc' => ['required', 'string', 'max:100'],
        ]);

        DB::connection('run')->table('sysitc_grpacc')->where('rec_id', $validated['role_id'])->update([
            'grpdesc' => trim($validated['grpdesc']),
        ]);

        return back()->with('success_msg', 'Role berhasil diperbarui.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        abort_unless($this->isSuperadmin($request), 403);

        $validated = $request->validate(['role_id' => ['required', 'integer', 'min:1']]);
        $role = DB::connection('run')->table('sysitc_grpacc')->where('rec_id', $validated['role_id'])->first();

        if (! $role) {
            return back()->with('error_msg', 'Role tidak ditemukan.');
        }

        if ($this->isSuperadminRole($role) || $this->getRoleUserCount($role->grpaccess, $role->grpacc) > 0 || $this->getRoleMenuCount((int) $role->rec_id) > 0) {
            return back()->with('error_msg', 'Role tidak bisa dihapus karena masih digunakan atau memiliki mapping menu.');
        }

        DB::connection('run')->table('sysitc_grpacc')->where('rec_id', $role->rec_id)->delete();

        return back()->with('success_msg', 'Role berhasil dihapus.');
    }

    public function legacyAction(Request $request): RedirectResponse
    {
        return match ($request->input('action')) {
            'create_role' => $this->store($request),
            'update_role' => $this->update($request),
            'delete_role' => $this->destroy($request),
            default => back()->with('error_msg', 'Action role tidak valid.'),
        };
    }

    private function getRoles(): array
    {
        return DB::connection('run')
            ->table('sysitc_grpacc as g')
            ->select('g.*')
            ->selectSub(function ($q): void {
                $q->from('sysitc_usracc as ua')
                    ->selectRaw('COUNT(DISTINCT ua.user_rec_id)')
                    ->whereColumn('ua.access_code', 'g.grpaccess')
                    ->whereColumn('ua.access_account', 'g.grpacc');
            }, 'user_count')
            ->selectSub(function ($q): void {
                $q->from('sys_menu_access as ma')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('ma.grpacc_id', 'g.rec_id');
            }, 'menu_count')
            ->orderBy('g.grpaccess')
            ->orderBy('g.grpacc')
            ->get()
            ->all();
    }

    private function getUsersWithRoles(int $limit): array
    {
        return DB::connection('run')
            ->table('sysitc_users as u')
            ->join('sysitc_login as l', 'u.login_rec_id', '=', 'l.rec_id')
            ->select('u.rec_id', 'u.account_nm', 'u.status', 'l.account_id', 'l.email_id')
            ->orderBy('u.account_nm')
            ->limit($limit)
            ->get()
            ->map(function ($user) {
                $user->roles = DB::connection('run')
                    ->table('sysitc_usracc as ua')
                    ->join('sysitc_grpacc as g', function ($join): void {
                        $join->on('g.grpaccess', '=', 'ua.access_code')->on('g.grpacc', '=', 'ua.access_account');
                    })
                    ->select('g.grpaccess', 'g.grpacc', 'g.grpdesc')
                    ->where('ua.user_rec_id', $user->rec_id)
                    ->orderBy('g.grpaccess')
                    ->get()
                    ->all();

                return $user;
            })
            ->all();
    }

    private function getRoleUserCount(string $grpaccess, string $grpacc): int
    {
        return DB::connection('run')->table('sysitc_usracc')
            ->where('access_code', $grpaccess)
            ->where('access_account', $grpacc)
            ->distinct('user_rec_id')
            ->count('user_rec_id');
    }

    private function getRoleMenuCount(int $roleId): int
    {
        return DB::connection('run')->table('sys_menu_access')->where('grpacc_id', $roleId)->count();
    }

    private function isSuperadmin(Request $request): bool
    {
        $userId = (int) auth_user_id();
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
