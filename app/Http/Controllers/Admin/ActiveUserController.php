<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActiveUserController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($this->isSuperadmin($request), 403);

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'role_id' => trim((string) $request->query('role_id', '')),
            'status' => trim((string) $request->query('status', 'active')),
        ];

        $page = max(1, (int) $request->query('page', 1));
        $limit = 15;
        $offset = ($page - 1) * $limit;

        $usersResult = $this->getUsersWithRoles($filters, $limit, $offset);
        $summary = [
            'totalAll' => $this->getUsersWithRoles([], 1, 0)['total'],
            'totalActive' => $this->getUsersWithRoles(['status' => 'active'], 1, 0)['total'],
            'totalInactive' => $this->getUsersWithRoles(['status' => 'inactive'], 1, 0)['total'],
            'totalUnassigned' => $this->getUsersWithRoles(['unassigned' => true], 1, 0)['total'],
        ];

        return view('admin.system-access.active-users', [
            'filters' => $filters,
            'users' => $usersResult['users'],
            'totalUsers' => $usersResult['total'],
            'totalPages' => (int) ceil($usersResult['total'] / $limit),
            'page' => $page,
            'roles' => $this->getRoles(),
            'summary' => $summary,
        ]);
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        abort_unless($this->isSuperadmin($request), 403);

        $validated = $request->validate([
            'user_rec_id' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'integer', 'in:0,1'],
        ]);

        DB::connection('run')
            ->table('sysitc_users')
            ->where('rec_id', $validated['user_rec_id'])
            ->update(['status' => (int) $validated['status']]);

        return back()->with('success_msg', (int) $validated['status'] === 1 ? 'User berhasil diaktifkan.' : 'User berhasil dinonaktifkan.');
    }

    private function getUsersWithRoles(array $filters, int $limit, int $offset): array
    {
        $query = DB::connection('run')
            ->table('sysitc_users as u')
            ->join('sysitc_login as l', 'u.login_rec_id', '=', 'l.rec_id')
            ->select('u.rec_id', 'u.account_nm', 'u.status', 'u.cmpcd', 'l.account_id', 'l.email_id', 'l.lastlogin');

        $this->applyUserFilters($query, $filters);

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

    private function applyUserFilters($query, array $filters): void
    {
        if (($filters['status'] ?? '') === 'active') {
            $query->where('u.status', 1);
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $query->where('u.status', '!=', 1);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search): void {
                $q->where('u.account_nm', 'like', $search)
                    ->orWhere('l.account_id', 'like', $search)
                    ->orWhere('l.email_id', 'like', $search);
            });
        }

        if (! empty($filters['role_id'])) {
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

        if (! empty($filters['unassigned'])) {
            $query->whereNotExists(function ($q): void {
                $q->selectRaw('1')
                    ->from('sysitc_usracc as ua3')
                    ->whereColumn('ua3.user_rec_id', 'u.rec_id')
                    ->whereIn('ua3.access_code', ['01', '03', '04']);
            });
        }
    }

    private function getRoles(): array
    {
        return DB::connection('run')
            ->table('sysitc_grpacc')
            ->orderBy('grpaccess')
            ->orderBy('grpacc')
            ->get()
            ->all();
    }

    private function getUserRoles(int $userId): array
    {
        return DB::connection('run')
            ->table('sysitc_usracc as ua')
            ->join('sysitc_grpacc as g', function ($join): void {
                $join->on('g.grpaccess', '=', 'ua.access_code')
                    ->on('g.grpacc', '=', 'ua.access_account');
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
                $join->on('g.grpaccess', '=', 'ua.access_code')
                    ->on('g.grpacc', '=', 'ua.access_account');
            })
            ->where('ua.user_rec_id', $userId)
            ->where('g.grpaccess', '03')
            ->where('g.grpacc', '999')
            ->where('g.grpdesc', 'like', '%SUPER%ADMIN%')
            ->exists();
    }
}
