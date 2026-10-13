<?php

namespace App\Http\Controllers\CbtOps;

use App\Http\Controllers\Controller;
use App\Repositories\FilingSystem\FileGroup;
use App\Repositories\FilingSystem\FileSystemDrive;
use App\Support\Legacy\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDO;

class FilingGroupController extends Controller
{
    private PDO $pdo;

    private FileGroup $groups;

    public function __construct()
    {
        $this->pdo = DB::connection('run')->getPdo();
        $this->groups = new FileGroup($this->pdo);
    }

    private function userId(): int
    {
        return (int) auth_user_id();
    }

    private function guardUser(): void
    {
        if ($this->userId() <= 0) {
            abort(401);
        }
    }

    private function groupOr404(int $groupId): array
    {
        $group = $this->groups->getGroup($groupId);
        if (! $group) {
            abort(404, 'Grup tidak ditemukan.');
        }

        return $group;
    }

    private function requireMember(int $groupId): string
    {
        $role = $this->groups->roleFor($groupId, $this->userId());
        if ($role === null) {
            abort(403, 'Anda bukan anggota grup ini.');
        }

        return $role;
    }

    private function requireManager(int $groupId): void
    {
        if ($this->requireMember($groupId) !== 'manager') {
            abort(403, 'Hanya Pengelola yang dapat melakukan aksi ini.');
        }
    }

    private function fileOwnerId(int $filingId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT userid FROM file_system WHERE rec_id = ? LIMIT 1');
        $stmt->execute([$filingId]);
        $owner = $stmt->fetchColumn();

        return $owner === false ? null : (int) $owner;
    }

    public function index()
    {
        $this->guardUser();
        $data = $this->groups->listForUser($this->userId(), 500);

        return view('filing-system.groups.index', [
            'groups' => $data['groups'],
        ]);
    }

    public function create()
    {
        $this->guardUser();

        return view('filing-system.groups.create');
    }

    public function store(Request $request)
    {
        $this->guardUser();

        $name = trim((string) $request->input('group_name', ''));
        $description = trim((string) $request->input('description', ''));

        if ($name === '' || mb_strlen($name) > 100) {
            return back()->with('error_msg', 'Nama grup wajib diisi (maksimal 100 karakter).')->withInput();
        }

        $groupId = $this->groups->createGroup($this->userId(), $name, mb_substr($description, 0, 1000));
        AuditLog::logAudit($this->pdo, 'FILING_GROUP_CREATE', 'filing_group', $groupId, [
            'group_name' => $name,
        ], $this->userId());

        return redirect()->route('filing-system.groups.members', $groupId)
            ->with('success_msg', 'Grup "'.$name.'" berhasil dibuat.');
    }

    public function show(Request $request, int $group)
    {
        $this->guardUser();
        $groupRow = $this->groupOr404($group);
        $role = $this->requireMember($group);

        if (! FileSystemDrive::isEnabled()) {
            abort(404, 'Fitur grup memerlukan tabel baru (FILING_USE_NEW_TABLES).');
        }

        $filters = [
            'folder' => 'group',
            'group_id' => $group,
            'search' => (string) $request->query('search', ''),
            'page' => max(1, (int) $request->query('page', 1)),
            'limit' => max(10, min(100, (int) $request->query('limit', 20))),
            'sort' => (string) $request->query('sort', 'latest'),
        ];

        $drive = new FilingDriveController($this->pdo, []);
        $data = $drive->fetchList($filters);

        $viewMode = $request->cookie('filing_view') === 'grid' ? 'grid' : 'list';

        $divisionOnly = $role !== 'manager'
            && (int) $groupRow['created_by'] !== $this->userId()
            && ! $this->groups->hasPersonalMembership($group, $this->userId());

        return view('filing-system.groups.show', [
            'group' => $groupRow,
            'role' => $role,
            'canManage' => $role === 'manager',
            'canContribute' => in_array($role, ['manager', 'contributor'], true),
            'divisionOnly' => $divisionOnly,
            'filters' => $filters,
            'viewMode' => $viewMode,
            'items' => $data['items'] ?? [],
            'pagination' => $data['pagination'] ?? [
                'current_page' => 1,
                'total_pages' => 1,
                'total_items' => 0,
                'limit' => $filters['limit'],
            ],
            'myFiles' => $this->myActiveFiles(),
        ]);
    }

    public function update(Request $request, int $group)
    {
        $this->guardUser();
        $this->groupOr404($group);
        $this->requireManager($group);

        $name = trim((string) $request->input('group_name', ''));
        $description = trim((string) $request->input('description', ''));

        if ($name === '' || mb_strlen($name) > 100) {
            return back()->with('error_msg', 'Nama grup wajib diisi (maksimal 100 karakter).')->withInput();
        }

        $this->groups->updateGroup($group, $name, mb_substr($description, 0, 1000));
        AuditLog::logAudit($this->pdo, 'FILING_GROUP_UPDATE', 'filing_group', $group, [
            'group_name' => $name,
        ], $this->userId());

        return back()->with('success_msg', 'Grup berhasil diperbarui.');
    }

    public function archive(int $group)
    {
        $this->guardUser();
        $this->groupOr404($group);
        $this->requireManager($group);

        $this->groups->archiveGroup($group);
        AuditLog::logAudit($this->pdo, 'FILING_GROUP_ARCHIVE', 'filing_group', $group, [], $this->userId());

        return redirect()->route('filing-system.groups.index')
            ->with('success_msg', 'Grup berhasil diarsipkan.');
    }

    public function leave(int $group)
    {
        $this->guardUser();
        $groupRow = $this->groupOr404($group);
        $this->requireMember($group);

        if ((int) $groupRow['created_by'] === $this->userId()) {
            return back()->with('error_msg', 'Pemilik grup tidak dapat keluar. Arsipkan grup bila tidak digunakan lagi.');
        }

        if (! $this->groups->hasPersonalMembership($group, $this->userId())) {
            return back()->with('error_msg', 'Keanggotaan Anda berasal dari divisi, sehingga tidak dapat keluar sendiri. Hubungi Pengelola grup untuk mengubah keanggotaan divisi Anda.');
        }

        $this->groups->leaveGroup($group, $this->userId());
        AuditLog::logAudit($this->pdo, 'FILING_GROUP_LEAVE', 'filing_group', $group, [], $this->userId());

        return redirect()->route('filing-system.groups.index')
            ->with('success_msg', 'Anda telah keluar dari grup.');
    }

    public function members(int $group)
    {
        $this->guardUser();
        $groupRow = $this->groupOr404($group);
        $role = $this->requireMember($group);

        return view('filing-system.groups.members', [
            'group' => $groupRow,
            'role' => $role,
            'canManage' => $role === 'manager',
            'members' => $this->groups->getMembers($group),
            'userOptions' => $this->groups->userOptions(),
            'divisionOptions' => $this->groups->divisionOptions(),
        ]);
    }

    public function addMember(Request $request, int $group)
    {
        $this->guardUser();
        $this->groupOr404($group);
        $this->requireManager($group);

        $type = (string) $request->input('member_type', 'user');
        $value = trim((string) $request->input('member_value', ''));
        $role = (string) $request->input('role', 'contributor');

        if ($value === '' || ! in_array($type, ['user', 'division'], true)) {
            return back()->with('error_msg', 'Pilih anggota (user atau divisi) yang akan ditambahkan.');
        }

        $this->groups->addMember($group, $type, $value, $role, $this->userId());
        AuditLog::logAudit($this->pdo, 'FILING_GROUP_MEMBER_ADD', 'filing_group', $group, [
            'member_type' => $type,
            'member_value' => $value,
            'role' => $role,
        ], $this->userId());

        return back()->with('success_msg', 'Anggota berhasil ditambahkan.');
    }

    public function updateMemberRole(Request $request, int $group, int $member)
    {
        $this->guardUser();
        $this->groupOr404($group);
        $this->requireManager($group);

        $role = (string) $request->input('role', 'contributor');
        $this->groups->updateMemberRole($group, $member, $role);
        AuditLog::logAudit($this->pdo, 'FILING_GROUP_MEMBER_ROLE', 'filing_group', $group, [
            'member_id' => $member,
            'role' => $role,
        ], $this->userId());

        return back()->with('success_msg', 'Peran anggota diperbarui.');
    }

    public function removeMember(int $group, int $member)
    {
        $this->guardUser();
        $this->groupOr404($group);
        $this->requireManager($group);

        $this->groups->removeMember($group, $member);
        AuditLog::logAudit($this->pdo, 'FILING_GROUP_MEMBER_REMOVE', 'filing_group', $group, [
            'member_id' => $member,
        ], $this->userId());

        return back()->with('success_msg', 'Anggota dikeluarkan dari grup.');
    }

    public function moveFile(Request $request, int $group)
    {
        $this->guardUser();
        $this->groupOr404($group);
        $role = $this->requireMember($group);

        $filingId = (int) $request->input('filing_id', 0);
        $ownerId = $filingId > 0 ? $this->fileOwnerId($filingId) : null;

        if ($ownerId === null) {
            return back()->with('error_msg', 'File tidak ditemukan.');
        }
        if ($role !== 'manager' && $ownerId !== $this->userId()) {
            abort(403, 'Anda hanya dapat memindahkan file milik sendiri.');
        }

        $this->groups->moveFileToGroup($group, $filingId, $this->userId());
        AuditLog::logAudit($this->pdo, 'FILING_GROUP_FILE_MOVE_IN', 'filing_group', $group, [
            'filing_id' => $filingId,
        ], $this->userId());

        return back()->with('success_msg', 'File dipindahkan ke grup.');
    }

    public function removeFile(Request $request, int $group)
    {
        $this->guardUser();
        $this->groupOr404($group);
        $role = $this->requireMember($group);

        $filingId = (int) $request->input('filing_id', 0);
        $ownerId = $filingId > 0 ? $this->fileOwnerId($filingId) : null;

        if ($ownerId === null) {
            return back()->with('error_msg', 'File tidak ditemukan.');
        }
        if ($role !== 'manager' && $ownerId !== $this->userId()) {
            abort(403, 'Anda hanya dapat mengeluarkan file milik sendiri.');
        }

        $this->groups->removeFileFromGroup($group, $filingId);
        AuditLog::logAudit($this->pdo, 'FILING_GROUP_FILE_REMOVE', 'filing_group', $group, [
            'filing_id' => $filingId,
        ], $this->userId());

        return back()->with('success_msg', 'File dikeluarkan dari grup.');
    }

    public function upload(Request $request, int $group)
    {
        $this->guardUser();
        $this->groupOr404($group);
        $role = $this->requireMember($group);

        if (! in_array($role, ['manager', 'contributor'], true)) {
            return response()->json(['success' => false, 'message' => 'Penonton tidak dapat mengunggah file.'], 403);
        }

        // Tandai upload agar otomatis dibagikan ke grup (share_cat=5).
        $_POST['group_id'] = (string) $group;
        $_POST['share_targets'] = json_encode([['type' => 'custom_group', 'value' => (string) $group]]);
        $_POST['security_level'] = $request->input('security_level', 'normal');

        try {
            $drive = new FilingDriveController($this->pdo, $this->ftpConfig());
            $response = $drive->handleUpload();
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }

        if (! empty($response['success'])) {
            AuditLog::logAudit($this->pdo, 'FILING_GROUP_FILE_UPLOAD', 'filing_group', $group, [
                'file_id' => $response['file_id'] ?? 0,
                'file_code' => $response['file_code'] ?? '',
            ], $this->userId());
        }

        return response()->json($response);
    }

    /** @return array<int, array{rec_id:int,file_name:string}> */
    private function myActiveFiles(): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT rec_id, file_name FROM file_system
                WHERE userid = ? AND status = 'active'
                ORDER BY create_dt DESC LIMIT 200
            ");
            $stmt->execute([$this->userId()]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function ftpConfig(): array
    {
        return [
            'host' => env('FTP_HOST', ''),
            'user' => env('FTP_USER', ''),
            'pass' => env('FTP_PASS', ''),
            'port' => (int) env('FTP_PORT', 21),
            'path' => env('FTP_PATH', ''),
            'root_path' => env('FTP_ROOT_PATH', ''),
            'ssl' => filter_var(env('FTP_SSL', false), FILTER_VALIDATE_BOOLEAN),
            'timeout' => (int) env('FTP_TIMEOUT', 60),
        ];
    }
}
