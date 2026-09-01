<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserRoleController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($this->isSuperadmin($request), 403);

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'role_id' => trim((string) $request->query('role_id', '')),
        ];

        $page = max(1, (int) $request->query('page', 1));
        $limit = 15;
        $result = $this->getUsersWithRoles($filters, $limit, ($page - 1) * $limit);

        return view('admin.system-access.user-role', [
            'filters' => $filters,
            'users' => $result['users'],
            'totalUsers' => $result['total'],
            'totalPages' => (int) ceil($result['total'] / $limit),
            'page' => $page,
            'roles' => $this->getRoles(),
        ]);
    }

    public function assign(Request $request): RedirectResponse
    {
        abort_unless($this->isSuperadmin($request), 403);

        $validated = $request->validate([
            'user_rec_id' => ['required', 'integer', 'min:1'],
            'grpacc_id' => ['required', 'integer', 'min:1'],
        ]);

        $role = DB::connection('run')->table('sysitc_grpacc')->where('rec_id', $validated['grpacc_id'])->first();
        if (! $role) {
            return back()->with('error_msg', 'Role tidak ditemukan.');
        }

        $exists = DB::connection('run')->table('sysitc_usracc')
            ->where('user_rec_id', $validated['user_rec_id'])
            ->where('access_code', $role->grpaccess)
            ->where('access_account', $role->grpacc)
            ->exists();

        if (! $exists) {
            DB::connection('run')->table('sysitc_usracc')->insert([
                'user_rec_id' => $validated['user_rec_id'],
                'access_code' => $role->grpaccess,
                'access_account' => $role->grpacc,
            ]);
        }

        return back()->with('success_msg', 'Role user berhasil diperbarui.');
    }

    public function remove(Request $request): RedirectResponse
    {
        abort_unless($this->isSuperadmin($request), 403);

        $validated = $request->validate([
            'user_rec_id' => ['required', 'integer', 'min:1'],
            'access_code' => ['required', 'string'],
            'access_account' => ['required', 'string'],
        ]);

        if ($this->isSuperadminRole($validated['access_code'], $validated['access_account']) && $this->isLastActiveSuperadmin((int) $validated['user_rec_id'])) {
            return back()->with('error_msg', 'Tidak dapat menghapus role Superadmin dari user terakhir yang masih aktif.');
        }

        DB::connection('run')->table('sysitc_usracc')
            ->where('user_rec_id', $validated['user_rec_id'])
            ->where('access_code', $validated['access_code'])
            ->where('access_account', $validated['access_account'])
            ->delete();

        return back()->with('success_msg', 'Role berhasil dihapus dari user.');
    }

    private function getUsersWithRoles(array $filters, int $limit, int $offset): array
    {
        $query = DB::connection('run')
            ->table('sysitc_users as u')
            ->join('sysitc_login as l', 'u.login_rec_id', '=', 'l.rec_id')
            ->select('u.rec_id', 'u.account_nm', 'u.status', 'l.account_id', 'l.email_id');

        if ($filters['search'] !== '') {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search): void {
                $q->where('u.account_nm', 'like', $search)
                    ->orWhere('l.account_id', 'like', $search)
                    ->orWhere('l.email_id', 'like', $search);
            });
        }

        if ($filters['role_id'] !== '') {
            $role = DB::connection('run')->table('sysitc_grpacc')->where('rec_id', (int) $filters['role_id'])->first();
            if ($role) {
                $query->whereExists(function ($q) use ($role): void {
                    $q->selectRaw('1')
                        ->from('sysitc_usracc as ua2')
                        ->whereColumn('ua2.user_rec_id', 'u.rec_id')
                        ->where('ua2.access_code', $role->grpaccess)
                        ->where('ua2.access_account', $role->grpacc);
                });
            }
        }

        $totalQuery = clone $query;
        $total = $totalQuery->distinct('u.rec_id')->count('u.rec_id');

        $users = $query
            ->orderBy('u.account_nm')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(function ($user) {
                $user->roles = $this->getUserRoles((int) $user->rec_id);

                return $user;
            })
            ->all();

        return ['users' => $users, 'total' => $total];
    }

    private function getRoles(): array
    {
        return DB::connection('run')->table('sysitc_grpacc')->orderBy('grpaccess')->orderBy('grpacc')->get()->all();
    }

    private function getUserRoles(int $userId): array
    {
        return DB::connection('run')
            ->table('sysitc_usracc as ua')
            ->join('sysitc_grpacc as g', function ($join): void {
                $join->on('g.grpaccess', '=', 'ua.access_code')->on('g.grpacc', '=', 'ua.access_account');
            })
            ->select('g.rec_id', 'g.grpaccess', 'g.grpacc', 'g.grpdesc')
            ->where('ua.user_rec_id', $userId)
            ->orderBy('g.grpaccess')
            ->get()
            ->all();
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

    private function isSuperadminRole(string $accessCode, string $accessAccount): bool
    {
        return DB::connection('run')
            ->table('sysitc_grpacc')
            ->where('grpaccess', $accessCode)
            ->where('grpacc', $accessAccount)
            ->where('grpdesc', 'like', '%SUPER%ADMIN%')
            ->exists();
    }

    private function isLastActiveSuperadmin(int $userId): bool
    {
        $isUserSuperadmin = DB::connection('run')
            ->table('sysitc_usracc as ua')
            ->join('sysitc_grpacc as g', function ($join): void {
                $join->on('g.grpaccess', '=', 'ua.access_code')->on('g.grpacc', '=', 'ua.access_account');
            })
            ->where('ua.user_rec_id', $userId)
            ->where('g.grpdesc', 'like', '%SUPER%ADMIN%')
            ->exists();

        if (! $isUserSuperadmin) {
            return false;
        }

        $activeSuperadmins = DB::connection('run')
            ->table('sysitc_users as u')
            ->join('sysitc_usracc as ua', 'ua.user_rec_id', '=', 'u.rec_id')
            ->join('sysitc_grpacc as g', function ($join): void {
                $join->on('g.grpaccess', '=', 'ua.access_code')->on('g.grpacc', '=', 'ua.access_account');
            })
            ->where('u.status', 1)
            ->where('g.grpdesc', 'like', '%SUPER%ADMIN%')
            ->distinct('u.rec_id')
            ->count('u.rec_id');

        return $activeSuperadmins <= 1;
    }
}
