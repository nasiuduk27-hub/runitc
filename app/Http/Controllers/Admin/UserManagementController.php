<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($this->isSuperadmin($request), 403);

        $search = trim((string) $request->query('search', ''));
        $roleFilter = trim((string) $request->query('role_id', ''));
        $statusFilter = trim((string) $request->query('status', ''));
        $showUnassigned = $request->boolean('unassigned');
        $page = max(1, (int) $request->query('page', 1));
        $limit = 20;

        $filters = [];
        if ($search !== '') {
            $filters['search'] = $search;
        }
        if ($roleFilter !== '') {
            $filters['role_id'] = $roleFilter;
        }
        if ($statusFilter !== '') {
            $filters['status'] = $statusFilter;
        }
        if ($showUnassigned) {
            $filters['unassigned'] = true;
        }

        $result = $this->getUserList($filters, $limit, ($page - 1) * $limit);

        return view('admin.system-access.users', [
            'filters' => [
                'search' => $search,
                'role_id' => $roleFilter,
                'status' => $statusFilter,
                'unassigned' => $showUnassigned,
            ],
            'users' => $result['users'],
            'totalUsers' => $result['total'],
            'totalPages' => (int) ceil($result['total'] / $limit),
            'page' => $page,
            'summary' => [
                'totalAll' => $this->countTotalUsers(),
                'totalActive' => $this->countActiveUsers(),
                'totalInactive' => $this->countInactiveUsers(),
                'totalUnassigned' => $this->countUsersWithoutRole(),
            ],
            'roles' => $this->getRoles(),
        ]);
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        abort_unless($this->isSuperadmin($request), 403);

        $validated = $request->validate([
            'user_rec_id' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'integer', 'in:0,1'],
        ]);

        $userId = (int) $validated['user_rec_id'];
        $status = (int) $validated['status'];

        if ($status === 0 && $this->isSuperadminUser($userId) && $this->countActiveSuperadmins() <= 1) {
            return back()->with('error_msg', 'Tidak dapat menonaktifkan Superadmin terakhir yang masih aktif.');
        }

        DB::connection('run')->table('sysitc_users')->where('rec_id', $userId)->update(['status' => $status]);

        $this->logAudit(
            $status === 1 ? 'USER_ACTIVATED' : 'USER_DEACTIVATED',
            'sysitc_users',
            $userId,
            ['new_status' => $status],
            (int) $request->session()->get('user_id', 0)
        );

        return back()->with('success_msg', $status === 1 ? 'User berhasil diaktifkan.' : 'User berhasil dinonaktifkan.');
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        abort_unless($this->isSuperadmin($request), 403);

        $validated = $request->validate(['user_rec_id' => ['required', 'integer', 'min:1']]);
        $userId = (int) $validated['user_rec_id'];

        $loginRecId = DB::connection('run')
            ->table('sysitc_users as u')
            ->join('sysitc_login as l', 'u.login_rec_id', '=', 'l.rec_id')
            ->where('u.rec_id', $userId)
            ->value('l.rec_id');

        if (! $loginRecId) {
            return back()->with('error_msg', 'User tidak ditemukan.');
        }

        $tempPassword = bin2hex(random_bytes(4));
        DB::connection('run')->table('sysitc_login')
            ->where('rec_id', $loginRecId)
            ->update(['password_id' => DB::raw("MD5('".$tempPassword."')")]);

        $this->logAudit(
            'USER_PASSWORD_RESET',
            'sysitc_users',
            $userId,
            ['reset_by' => (int) $request->session()->get('user_id', 0)],
            (int) $request->session()->get('user_id', 0)
        );

        return back()->with('success_msg', 'Password berhasil di-reset. Password sementara: '.$tempPassword);
    }

    public function destroy(Request $request): RedirectResponse
    {
        abort_unless($this->isSuperadmin($request), 403);

        $validated = $request->validate(['user_rec_id' => ['required', 'integer', 'min:1']]);
        $userId = (int) $validated['user_rec_id'];

        $detail = DB::connection('run')
            ->table('sysitc_users as u')
            ->join('sysitc_login as l', 'u.login_rec_id', '=', 'l.rec_id')
            ->select('u.rec_id', 'u.account_nm', 'l.account_id', 'l.rec_id as login_rec_id')
            ->where('u.rec_id', $userId)
            ->first();

        if (! $detail) {
            return back()->with('error_msg', 'User tidak ditemukan.');
        }

        if ($this->isSuperadminUser($userId) && $this->countActiveSuperadmins() <= 1) {
            return back()->with('error_msg', 'Tidak dapat menghapus Superadmin terakhir yang masih aktif.');
        }

        DB::connection('run')->transaction(function () use ($userId, $detail): void {
            DB::connection('run')->table('sysitc_usracc')->where('user_rec_id', $userId)->delete();
            DB::connection('run')->table('sysitc_usermail')->where('user_recid', $userId)->delete();
            DB::connection('run')->table('sysitc_users')->where('rec_id', $userId)->delete();
            if ((int) $detail->login_rec_id > 0) {
                DB::connection('run')->table('sysitc_login')->where('rec_id', (int) $detail->login_rec_id)->delete();
            }
        });

        $this->logAudit(
            'USER_DELETED',
            'sysitc_users',
            $userId,
            ['account_nm' => $detail->account_nm, 'account_id' => $detail->account_id],
            (int) $request->session()->get('user_id', 0)
        );

        return back()->with('success_msg', 'User berhasil dihapus.');
    }

    public function detail(Request $request): JsonResponse
    {
        abort_unless($this->isSuperadmin($request), 403);

        $userId = (int) $request->query('user_id', 0);
        if ($userId <= 0) {
            return response()->json(['error' => 'ID user tidak valid.'], 400);
        }

        $user = $this->getUser($userId);
        if (! $user) {
            return response()->json(['error' => 'User tidak ditemukan.'], 404);
        }

        $user->lastlogin = ! empty($user->lastlogin) && $user->lastlogin !== '0000-00-00 00:00:00'
            ? date('d M Y H:i', strtotime($user->lastlogin))
            : '-';

        return response()->json($user);
    }

    public function legacyAction(Request $request): RedirectResponse
    {
        return match ($request->input('action')) {
            'update_user_status' => $this->updateStatus($request),
            'reset_password' => $this->resetPassword($request),
            'delete_user' => $this->destroy($request),
            default => back()->with('error_msg', 'Action user tidak valid.'),
        };
    }

    private function getUserList(array $filters, int $limit, int $offset): array
    {
        $query = DB::connection('run')
            ->table('sysitc_users as u')
            ->join('sysitc_login as l', 'u.login_rec_id', '=', 'l.rec_id')
            ->select('u.rec_id', 'u.account_nm', 'u.alias_nm', 'u.status', 'u.cmpcd', 'u.dob', 'l.account_id', 'l.email_id', 'l.lastlogin', 'l.rec_id as login_rec_id');

        $this->applyUserFilters($query, $filters);

        $total = (clone $query)->distinct('u.rec_id')->count('u.rec_id');

        $users = $query
            ->orderBy('u.account_nm')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(function ($user): object {
                $user->roles = $this->getUserRoles((int) $user->rec_id);

                return $user;
            })
            ->all();

        return ['users' => $users, 'total' => $total];
    }

    private function getUser(int $userId): ?object
    {
        $user = DB::connection('run')
            ->table('sysitc_users as u')
            ->join('sysitc_login as l', 'u.login_rec_id', '=', 'l.rec_id')
            ->select('u.rec_id', 'u.account_nm', 'u.alias_nm', 'u.status', 'u.cmpcd', 'u.dob', 'u.whatsapp', 'u.sexmf', 'l.account_id', 'l.email_id', 'l.lastlogin', 'l.rec_id as login_rec_id')
            ->where('u.rec_id', $userId)
            ->first();

        if (! $user) {
            return null;
        }

        $user->roles = $this->getUserRoles($userId);

        return $user;
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
                $join->on('g.grpaccess', '=', 'ua.access_code')->on('g.grpacc', '=', 'ua.access_account');
            })
            ->select('g.rec_id', 'g.grpaccess', 'g.grpacc', 'g.grpdesc')
            ->where('ua.user_rec_id', $userId)
            ->orderBy('g.grpaccess')
            ->get()
            ->all();
    }

    private function countTotalUsers(): int
    {
        return DB::connection('run')->table('sysitc_users')->count();
    }

    private function countActiveUsers(): int
    {
        return DB::connection('run')->table('sysitc_users')->where('status', 1)->count();
    }

    private function countInactiveUsers(): int
    {
        return DB::connection('run')->table('sysitc_users')->where('status', '!=', 1)->count();
    }

    private function countUsersWithoutRole(): int
    {
        return DB::connection('run')
            ->table('sysitc_users as u')
            ->whereNotExists(function ($q): void {
                $q->selectRaw('1')
                    ->from('sysitc_usracc as ua')
                    ->whereColumn('ua.user_rec_id', 'u.rec_id')
                    ->whereIn('ua.access_code', ['01', '03', '04']);
            })
            ->distinct('u.rec_id')
            ->count('u.rec_id');
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

    private function isSuperadminUser(int $userId): bool
    {
        return DB::connection('run')
            ->table('sysitc_usracc as ua')
            ->join('sysitc_grpacc as g', function ($join): void {
                $join->on('g.grpaccess', '=', 'ua.access_code')->on('g.grpacc', '=', 'ua.access_account');
            })
            ->where('ua.user_rec_id', $userId)
            ->where('g.grpdesc', 'like', '%SUPER%ADMIN%')
            ->exists();
    }

    private function countActiveSuperadmins(): int
    {
        return DB::connection('run')
            ->table('sysitc_usracc as ua')
            ->join('sysitc_grpacc as g', function ($join): void {
                $join->on('g.grpaccess', '=', 'ua.access_code')->on('g.grpacc', '=', 'ua.access_account');
            })
            ->join('sysitc_users as u', 'u.rec_id', '=', 'ua.user_rec_id')
            ->where('g.grpdesc', 'like', '%SUPER%ADMIN%')
            ->where('u.status', 1)
            ->distinct('ua.user_rec_id')
            ->count('ua.user_rec_id');
    }

    private function logAudit(string $action, string $targetType, ?int $targetId, array $metadata, int $actorUserId): void
    {
        try {
            DB::connection('run')->table('sys_audit_log')->insert([
                'actor_user_id' => $actorUserId,
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                'ip_address' => request()->ip() ?: '127.0.0.1',
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Audit logging must not block user management.
        }
    }
}
