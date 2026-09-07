<?php

namespace App\Http\Controllers\CbtOps;

use App\Http\Controllers\Controller;
use App\Repositories\FilingSystem\FileSystemDrive;
use App\Repositories\FilingSystem\FilingAccess;
use App\Repositories\FilingSystem\FilingAudit;
use App\Repositories\FilingSystem\FilingShare;
use App\Repositories\FilingSystem\FilingSystem;
use App\Services\FilingSystem\FilingAdminService;
use App\Services\FilingSystem\FilingPermissionService;
use App\Services\FilingSystem\FilingStorageService;
use App\Services\FilingSystem\ShareCodeService;
use App\Services\FilingSystem\TestDocumentCrcB2Service;
use App\Services\FilingSystem\TestDocumentDataService;
use App\Services\FilingSystem\ZipInspectionService;
use App\Support\Legacy\FtpStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FilingSystemController extends Controller
{
    public function index(Request $request)
    {

        $folder = (string) $request->query('folder', 'my_drive');
        $allowedFolders = ['my_drive', 'shared', 'department', 'company', 'trash'];
        if (! in_array($folder, $allowedFolders, true)) {
            $folder = 'my_drive';
        }

        $filters = [
            'folder' => $folder,
            'search' => (string) $request->query('search', ''),
            'page' => max(1, (int) $request->query('page', 1)),
            'limit' => max(10, min(100, (int) $request->query('limit', 20))),
            'sort' => (string) $request->query('sort', 'latest'),
            'declared_file_type' => (string) $request->query('declared_file_type', ''),
            'security_level' => (string) $request->query('security_level', ''),
            'access_mode' => (string) $request->query('access_mode', ''),
            // Advanced filter
            'file_format' => $request->query('file_format', []),
            'owner_id' => (int) $request->query('owner_id', 0),
            'has_words' => (string) $request->query('has_words', ''),
            'location' => (string) $request->query('location', ''),
            'date_modify' => (string) $request->query('date_modify', 'anytime'),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
            'share_to' => (string) $request->query('share_to', ''),
        ];

        if (is_string($filters['file_format'])) {
            $filters['file_format'] = $filters['file_format'] === '' ? [] : explode(',', $filters['file_format']);
        }
        $filters['file_format'] = array_values(array_filter(array_map('strval', (array) $filters['file_format'])));

        $controller = new FilingDriveController(DB::connection('run')->getPdo(), $this->ftpConfig());
        $data = $controller->fetchList($filters);

        $folderCounts = [];
        $filterOptions = ['owners' => [], 'locations' => [], 'formats' => []];
        if (FileSystemDrive::isEnabled()) {
            $folderCounts = $controller->countFolderFilesNew((int) auth_user_id());
            $filterOptions = $controller->getFilterOptionsNew();
        }

        if ((string) $request->query('partial', '') === '1') {
            return view('filing-system._list', [
                'filters' => $filters,
                'folder' => $folder,
                'items' => $data['items'] ?? [],
                'pagination' => $data['pagination'] ?? [
                    'current_page' => 1,
                    'total_pages' => 1,
                    'total_items' => 0,
                    'limit' => $filters['limit'],
                ],
            ]);
        }

        return view('filing-system.index', [
            'filters' => $filters,
            'folder' => $folder,
            'items' => $data['items'] ?? [],
            'pagination' => $data['pagination'] ?? [
                'current_page' => 1,
                'total_pages' => 1,
                'total_items' => 0,
                'limit' => $filters['limit'],
            ],
            'folderCounts' => $folderCounts,
            'filterOptions' => $filterOptions,
            'trashRetentionDays' => max(1, (int) env('FILING_TRASH_RETENTION_DAYS', 30)),
            'useNewTables' => FileSystemDrive::isEnabled(),
            'modalHtml' => $this->modalHtml(),
        ]);
    }

    public function download(Request $request)
    {

        $filingId = (int) $request->query('id', 0);
        $shareHash = $request->query('share_hash');

        if ($filingId <= 0) {
            return response('ID File tidak valid.', 404);
        }

        $controller = new FilingDriveController(DB::connection('run')->getPdo(), $this->ftpConfig());

        if ($shareHash && session()->has('share_access_'.$shareHash)) {
            if (FileSystemDrive::isEnabled()) {
                $share = DB::connection('run')->table('file_share_link as s')
                    ->join('file_system as f', 'f.rec_id', '=', 's.filesys_id')
                    ->where('s.share_code_hash', $shareHash)
                    ->where('s.filesys_id', $filingId)
                    ->where('s.is_active', 1)
                    ->whereNull('s.revoked_at')
                    ->select('s.*', 'f.status', 'f.deleted_at')
                    ->first();

                if ($share && $share->allow_download && $share->status === 'active' && empty($share->deleted_at)) {
                    $controller->downloadNew($filingId);
                    exit;
                }

                return response('Share Code tidak mengizinkan unduhan atau tidak valid.', 403);
            }

            $share = DB::connection('run')->table('sys_filing_share as s')
                ->join('sys_filing as f', 'f.rec_id', '=', 's.filing_id')
                ->where('s.share_code_hash', $shareHash)
                ->where('s.filing_id', $filingId)
                ->where('s.is_active', 1)
                ->select('s.*', 'f.status', 'f.deleted_at')
                ->first();

            if ($share && $share->allow_download && $share->status === 'active' && empty($share->deleted_at)) {
                $controller->downloadViaShare($filingId, $shareHash, (int) auth_user_id());
                exit;
            }

            return response('Share Code tidak mengizinkan unduhan atau tidak valid.', 403);
        }

        $controller->download($filingId);
        exit;
    }

    public function shareAccess(Request $request)
    {

        if ((int) auth_user_id() <= 0) {
            return redirect()->route('login');
        }

        return view('filing-system.share-access');
    }

    public function audit(Request $request)
    {

        $userId = (int) auth_user_id();

        if ($userId <= 0) {
            return redirect()->route('login');
        }

        $pdoRun = DB::connection('run')->getPdo();
        $permService = new FilingPermissionService($pdoRun);
        $auditModel = new FilingAudit($pdoRun);
        $isAdmin = $permService->isAdmin($userId);

        $filters = [
            'filing_id' => (string) $request->query('filing_id', ''),
            'action' => (string) $request->query('action', ''),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
            'keyword' => (string) $request->query('keyword', ''),
        ];

        $page = max(1, (int) $request->query('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        if (! $isAdmin) {
            if (! empty($filters['filing_id'])) {
                $file = (array) DB::connection('run')->table('sys_filing')->where('rec_id', (int) $filters['filing_id'])->first();

                if (! $file || ! $permService->canViewAudit($file, $userId)) {
                    abort(403, 'Anda tidak memiliki izin untuk melihat audit file ini.');
                }
            } else {
                $attrs = $permService->resolveUserAttributes($userId);

                $accessOrs = ["(fa.access_type = 'user' AND fa.access_value = ?)"];
                $baseParams = [(string) $userId];

                $typeMapping = [
                    'company' => 'company',
                    'department' => 'department',
                    'division' => 'division',
                    'role' => 'role',
                    'custom_group' => 'custom_groups',
                ];

                foreach ($typeMapping as $dbType => $attrKey) {
                    $vals = $attrs[$attrKey] ?? [];
                    if (! empty($vals)) {
                        $placeholders = implode(',', array_fill(0, count($vals), '?'));
                        $accessOrs[] = "(fa.access_type = '{$dbType}' AND fa.access_value IN ({$placeholders}))";
                        foreach ($vals as $v) {
                            $baseParams[] = (string) $v;
                        }
                    }
                }

                $accessWhere = implode(' OR ', $accessOrs);

                $filters['base_security_where'] = "(
                    f.uploaded_by = ?
                    OR EXISTS (
                        SELECT 1 FROM sys_filing_access fa
                        WHERE fa.filing_id = f.rec_id
                        AND fa.can_manage = 1
                        AND ({$accessWhere})
                    )
                )";
                $filters['base_security_params'] = array_merge([$userId], $baseParams);
            }
        }

        $totalItems = $auditModel->countAuditLogs($filters);
        $totalPages = (int) ceil($totalItems / $limit);
        $logs = $auditModel->getAuditLogs($filters, $limit, $offset);
        $availableActions = $auditModel->getAuditActions();

        $fileTitle = null;
        if (! empty($filters['filing_id']) && ! empty($logs)) {
            $fileTitle = $logs[0]['file_name'];
        } elseif (! empty($filters['filing_id'])) {
            $fileTitle = DB::connection('run')->table('sys_filing')->where('rec_id', (int) $filters['filing_id'])->value('display_name');
        }

        return view('filing-system.audit', [
            'filters' => $filters,
            'logs' => $logs,
            'availableActions' => $availableActions,
            'totalItems' => $totalItems,
            'totalPages' => $totalPages,
            'page' => $page,
            'limit' => $limit,
            'isAdmin' => $isAdmin,
            'fileTitle' => $fileTitle,
            'formatActionLabel' => fn (string $action): string => $auditModel->formatActionLabel($action),
        ]);
    }

    public function action(Request $request)
    {

        $userId = (int) auth_user_id();

        if ($userId <= 0) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $action = (string) $request->input('action', '');
        $filingId = (int) ($request->input('filing_id', $request->input('file_id', $request->input('id', 0))));

        if ($action !== 'bulk' && $filingId <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid File ID']);
        }

        if (FileSystemDrive::isEnabled()) {
            return $this->actionNew($request, $action, $filingId);
        }

        $pdoRun = DB::connection('run')->getPdo();
        $permissionService = new FilingPermissionService($pdoRun);
        $storageService = new FilingStorageService($this->ftpConfig());
        $filingModel = new FilingSystem($pdoRun);

        $filingActionAudit = function (int $fid, int $uid, string $act, string $notes): void {
            DB::connection('run')->table('sys_filing_audit')->insert([
                'filing_id' => $fid,
                'user_id' => $uid,
                'action' => $act,
                'ip_address' => (string) request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
                'notes' => $notes,
            ]);
        };

        try {
            $file = null;
            if ($action !== 'bulk') {
                $file = $filingModel->getFileById($filingId);
                if (! $file) {
                    throw new \Exception('File tidak ditemukan.');
                }
            }

            switch ($action) {
                case 'get_file_info':
                    if (! $permissionService->canManage($file, $userId)) {
                        throw new \Exception('Permission denied.');
                    }

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'display_name' => $file['display_name'],
                            'declared_file_type' => $file['declared_file_type'],
                            'security_level' => $file['security_level'],
                            'keywords' => $file['keywords'],
                            'notes' => $file['notes'],
                            'expired_at' => $file['expired_at'],
                            'expired_action' => $file['expired_action'],
                        ],
                        'file_types' => $filingModel->getActiveFileTypes(),
                        'is_admin' => $permissionService->isAdmin($userId),
                    ]);

                case 'update_metadata':
                    if (in_array($file['status'], ['deleted', 'blocked', 'trashed'], true)) {
                        throw new \Exception("File dengan status {$file['status']} tidak dapat diedit. Restore file terlebih dahulu.");
                    }
                    if (! $permissionService->canManage($file, $userId)) {
                        $filingActionAudit($filingId, $userId, 'metadata_update_denied', 'Permission denied');
                        throw new \Exception('Anda tidak memiliki izin mengedit file ini.');
                    }

                    $displayName = trim((string) $request->input('display_name', ''));
                    if ($displayName === '') {
                        throw new \Exception('Nama Tampilan wajib diisi.');
                    }
                    if (strlen($displayName) > 255) {
                        throw new \Exception('Nama Tampilan maksimal 255 karakter.');
                    }
                    if (preg_match('/[\/\\\\]|\.\./', $displayName)) {
                        throw new \Exception('Format Nama Tampilan tidak aman (mengandung karakter slash atau path traversal).');
                    }

                    $secLevel = (string) $request->input('security_level', 'normal');
                    if (! in_array($secLevel, ['normal', 'restricted', 'confidential'], true)) {
                        $secLevel = 'normal';
                    }

                    $expEnabled = ! empty($request->input('expired_at'));
                    $expAt = $expEnabled ? date('Y-m-d H:i:s', strtotime((string) $request->input('expired_at'))) : null;
                    $expAction = (string) $request->input('expired_action', 'trash');

                    if ($expAction === 'delete' && ! $permissionService->isAdmin($userId)) {
                        throw new \Exception('Hanya admin yang dapat memilih aksi Hapus Permanen.');
                    }

                    $updateData = [
                        'display_name' => $displayName,
                        'declared_file_type' => (string) $request->input('declared_file_type', 'mixed'),
                        'security_level' => $secLevel,
                        'keywords' => (string) $request->input('keywords', ''),
                        'notes' => (string) $request->input('notes', ''),
                        'expired_at' => $expAt,
                        'expired_action' => $expAction,
                    ];

                    if ($expEnabled && $file['expired_at'] !== $expAt && $file['status'] === 'active') {
                        $updateData['expired_processed_at'] = null;
                    }
                    if (! $expEnabled) {
                        $updateData['expired_processed_at'] = null;
                    }

                    $pdoRun->beginTransaction();

                    try {
                        $updated = $filingModel->updateMetadata($filingId, $updateData, $userId);
                        if (! $updated) {
                            throw new \Exception('Gagal menyimpan perubahan ke database.');
                        }

                        $changes = [];
                        if ($file['display_name'] !== $updateData['display_name']) {
                            $changes[] = "Renamed from '{$file['display_name']}' to '{$updateData['display_name']}'";
                            $filingActionAudit($filingId, $userId, 'rename', end($changes));
                        }
                        if ($file['security_level'] !== $updateData['security_level']) {
                            $changes[] = "Security changed from '{$file['security_level']}' to '{$updateData['security_level']}'";
                            $filingActionAudit($filingId, $userId, 'security_update', end($changes));

                            if ($updateData['security_level'] === 'confidential') {
                                $stmtRevoke = $pdoRun->prepare('UPDATE sys_filing_share SET is_active = 0, revoked_at = NOW() WHERE filing_id = ? AND is_active = 1');
                                $stmtRevoke->execute([$filingId]);
                                if ($stmtRevoke->rowCount() > 0) {
                                    $filingActionAudit($filingId, $userId, 'share_auto_revoked_confidential', "Revoked {$stmtRevoke->rowCount()} shares due to confidential level.");
                                }
                            }
                        }
                        if ($file['expired_at'] !== $updateData['expired_at']) {
                            if ($updateData['expired_at'] === null) {
                                $changes[] = 'Expiry disabled';
                                $filingActionAudit($filingId, $userId, 'expiry_disable', end($changes));
                            } elseif ($file['expired_at'] === null) {
                                $changes[] = "Expiry set to '{$updateData['expired_at']}'";
                                $filingActionAudit($filingId, $userId, 'expiry_set', end($changes));
                            } else {
                                $changes[] = "Expiry updated from '{$file['expired_at']}' to '{$updateData['expired_at']}'";
                                if (strtotime((string) $updateData['expired_at']) > strtotime((string) $file['expired_at'])) {
                                    $filingActionAudit($filingId, $userId, 'expiry_extend', end($changes));
                                } else {
                                    $filingActionAudit($filingId, $userId, 'expiry_update', end($changes));
                                }
                            }
                        }

                        if (empty($changes)) {
                            $filingActionAudit($filingId, $userId, 'metadata_update', 'Updated file metadata (notes/keywords/type)');
                        }

                        $pdoRun->commit();
                    } catch (\Throwable $e) {
                        if ($pdoRun->inTransaction()) {
                            $pdoRun->rollBack();
                        }
                        throw $e;
                    }

                    return response()->json(['success' => true, 'message' => 'Info file berhasil diperbarui.']);

                case 'bulk':
                    return $this->filingBulkAction($request, $pdoRun, $permissionService, $storageService, $filingModel, $filingActionAudit, $userId);

                case 'move_trash':
                    if (! in_array($file['status'], ['active', 'archived'], true)) {
                        throw new \Exception('File tidak dalam status active/archived.');
                    }
                    if (! $permissionService->canManage($file, $userId)) {
                        $filingActionAudit($filingId, $userId, 'trash_denied', 'Permission denied');
                        throw new \Exception('Anda tidak memiliki izin untuk memindahkan file ini ke sampah.');
                    }

                    DB::connection('run')->table('sys_filing')->where('rec_id', $filingId)->update(['status' => 'trashed', 'deleted_at' => now()]);
                    $filingActionAudit($filingId, $userId, 'move_trash', 'Moved to trash');

                    return response()->json(['success' => true, 'message' => 'File berhasil dipindahkan ke Sampah.']);

                case 'restore':
                    if ($file['status'] !== 'trashed') {
                        throw new \Exception('File tidak berada di dalam Sampah.');
                    }
                    if (! $permissionService->canManage($file, $userId)) {
                        $filingActionAudit($filingId, $userId, 'restore_denied', 'Permission denied');
                        throw new \Exception('Anda tidak memiliki izin untuk me-restore file ini.');
                    }

                    DB::connection('run')->table('sys_filing')->where('rec_id', $filingId)->update(['status' => 'active', 'deleted_at' => null, 'trashed_at' => null]);
                    $filingActionAudit($filingId, $userId, 'restore', 'Restored from trash');

                    return response()->json(['success' => true, 'message' => 'File berhasil dikembalikan ke status Aktif.']);

                case 'archive':
                    if ($file['status'] !== 'active') {
                        throw new \Exception('Hanya file aktif yang bisa diarsipkan.');
                    }
                    if (! $permissionService->canManage($file, $userId)) {
                        $filingActionAudit($filingId, $userId, 'archive_denied', 'Permission denied');
                        throw new \Exception('Anda tidak memiliki izin untuk mengarsipkan file ini.');
                    }

                    DB::connection('run')->table('sys_filing')->where('rec_id', $filingId)->update(['status' => 'archived']);
                    $filingActionAudit($filingId, $userId, 'archive', 'Archived file');

                    return response()->json(['success' => true, 'message' => 'File berhasil diarsipkan.']);

                case 'restore_archive':
                    if ($file['status'] !== 'archived') {
                        throw new \Exception('File tidak dalam status archived.');
                    }
                    if (! $permissionService->canManage($file, $userId)) {
                        $filingActionAudit($filingId, $userId, 'restore_denied', 'Permission denied for archive restore');
                        throw new \Exception('Anda tidak memiliki izin untuk me-restore arsip ini.');
                    }

                    DB::connection('run')->table('sys_filing')->where('rec_id', $filingId)->update(['status' => 'active']);
                    $filingActionAudit($filingId, $userId, 'restore_archive', 'Restored from archive');

                    return response()->json(['success' => true, 'message' => 'Arsip berhasil diaktifkan kembali.']);

                case 'permanent_delete':
                    if (! in_array($file['status'], ['trashed', 'deleted'], true)) {
                        throw new \Exception('Hanya file di Sampah yang bisa dihapus permanen.');
                    }
                    if (! $permissionService->isAdmin($userId) && ! $permissionService->canManage($file, $userId)) {
                        $filingActionAudit($filingId, $userId, 'permanent_delete_denied', 'Permission denied');
                        throw new \Exception('Anda tidak memiliki izin untuk menghapus permanen file ini.');
                    }

                    $deletedPhysically = $storageService->deletePhysicalFile($file['storage_path']);
                    if (! $deletedPhysically) {
                        error_log('Failed to delete physical file: '.$file['storage_path']);
                    }

                    DB::connection('run')->table('sys_filing')->where('rec_id', $filingId)->update(['status' => 'deleted', 'deleted_at' => now()]);
                    $filingActionAudit($filingId, $userId, 'permanent_delete', 'Permanently deleted from system');

                    return response()->json(['success' => true, 'message' => 'File berhasil dihapus secara permanen.']);

                default:
                    throw new \Exception('Action tidak valid.');
            }
        } catch (\Throwable $e) {
            if ($pdoRun->inTransaction()) {
                $pdoRun->rollBack();
            }

            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Handler action untuk tabel baru (file_system + file_shareto).
     */
    private function actionNew(Request $request, string $action, int $filingId)
    {
        $pdoRun = DB::connection('run')->getPdo();
        $userId = (int) auth_user_id();
        $drive = new FileSystemDrive($pdoRun);
        $permissionService = new FilingPermissionService($pdoRun);
        $storageService = new FilingStorageService($this->ftpConfig());

        $audit = function (int $fid, int $uid, string $act, string $notes) use ($pdoRun): void {
            $stmt = $pdoRun->prepare('INSERT INTO sys_filing_audit (filing_id, user_id, action, ip_address, user_agent, notes) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $fid, $uid, $act,
                (string) request()->ip(),
                substr((string) request()->userAgent(), 0, 500),
                $notes,
            ]);
        };

        try {
            $file = null;
            if ($action !== 'bulk') {
                $file = $drive->getFileById($filingId);
                if (! $file) {
                    throw new \Exception('File tidak ditemukan.');
                }
            }

            $legacy = $file ? $drive->mapToLegacyShape($file) : null;

            switch ($action) {
                case 'get_file_info':
                    if (! $permissionService->canManage($legacy, $userId)) {
                        throw new \Exception('Permission denied.');
                    }

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'display_name' => $file['file_name'],
                            'declared_file_type' => $drive->mapExtToType($file['file_type'] ?? ''),
                            'security_level' => $file['security_level'] ?? 'normal',
                            'keywords' => $file['file_notes'] ?? '',
                            'notes' => $file['file_notes'] ?? '',
                            'expired_at' => $file['expired_at'],
                            'expired_action' => $file['expired_action'] ?? 'trash',
                        ],
                        'file_types' => $this->activeFileTypes($pdoRun),
                        'is_admin' => $permissionService->isAdmin($userId),
                    ]);

                case 'update_metadata':
                    if (in_array($file['status'] ?? 'active', ['deleted', 'blocked', 'trashed'], true)) {
                        throw new \Exception("File dengan status {$file['status']} tidak dapat diedit. Restore file terlebih dahulu.");
                    }
                    if (! $permissionService->canManage($legacy, $userId)) {
                        $audit($filingId, $userId, 'metadata_update_denied', 'Permission denied');
                        throw new \Exception('Anda tidak memiliki izin mengedit file ini.');
                    }

                    $displayName = trim((string) $request->input('display_name', ''));
                    if ($displayName === '') {
                        throw new \Exception('Nama Tampilan wajib diisi.');
                    }
                    if (strlen($displayName) > 255) {
                        throw new \Exception('Nama Tampilan maksimal 255 karakter.');
                    }
                    if (preg_match('/[\/\\\\]|\.\./', $displayName)) {
                        throw new \Exception('Format Nama Tampilan tidak aman (mengandung karakter slash atau path traversal).');
                    }

                    $secLevel = (string) $request->input('security_level', 'normal');
                    if (! in_array($secLevel, ['normal', 'restricted', 'confidential'], true)) {
                        $secLevel = 'normal';
                    }

                    $expEnabled = ! empty($request->input('expired_at'));
                    $expAt = $expEnabled ? date('Y-m-d H:i:s', strtotime((string) $request->input('expired_at'))) : null;

                    $updateData = [
                        'file_name' => $displayName,
                        'security_level' => $secLevel,
                        'file_notes' => (string) $request->input('notes', ''),
                        'expired_at' => $expAt,
                        'expired_action' => (string) $request->input('expired_action', 'trash'),
                    ];

                    $updated = $drive->updateMetadata($filingId, $updateData);
                    if (! $updated) {
                        throw new \Exception('Gagal menyimpan perubahan ke database.');
                    }

                    $audit($filingId, $userId, 'metadata_update', 'Updated file metadata (notes/security)');

                    return response()->json(['success' => true, 'message' => 'Info file berhasil diperbarui.']);

                case 'move_trash':
                    if (! in_array($file['status'] ?? 'active', ['active', 'archived'], true)) {
                        throw new \Exception('File tidak dalam status active/archived.');
                    }
                    if (! $permissionService->canManage($legacy, $userId)) {
                        $audit($filingId, $userId, 'trash_denied', 'Permission denied');
                        throw new \Exception('Anda tidak memiliki izin untuk memindahkan file ini ke sampah.');
                    }

                    $drive->updateMetadata($filingId, ['status' => 'trashed', 'deleted_at' => date('Y-m-d H:i:s')]);
                    $audit($filingId, $userId, 'move_trash', 'Moved to trash');

                    return response()->json(['success' => true, 'message' => 'File berhasil dipindahkan ke Sampah.']);

                case 'restore':
                    if ($file['status'] !== 'trashed') {
                        throw new \Exception('File tidak berada di dalam Sampah.');
                    }
                    if (! $permissionService->canManage($legacy, $userId)) {
                        $audit($filingId, $userId, 'restore_denied', 'Permission denied');
                        throw new \Exception('Anda tidak memiliki izin untuk me-restore file ini.');
                    }

                    $drive->updateMetadata($filingId, ['status' => 'active', 'deleted_at' => null, 'trashed_at' => null]);
                    $audit($filingId, $userId, 'restore', 'Restored from trash');

                    return response()->json(['success' => true, 'message' => 'File berhasil dikembalikan ke status Aktif.']);

                case 'archive':
                    if ($file['status'] !== 'active') {
                        throw new \Exception('Hanya file aktif yang bisa diarsipkan.');
                    }
                    if (! $permissionService->canManage($legacy, $userId)) {
                        $audit($filingId, $userId, 'archive_denied', 'Permission denied');
                        throw new \Exception('Anda tidak memiliki izin untuk mengarsipkan file ini.');
                    }

                    $drive->updateMetadata($filingId, ['status' => 'archived']);
                    $audit($filingId, $userId, 'archive', 'Archived file');

                    return response()->json(['success' => true, 'message' => 'File berhasil diarsipkan.']);

                case 'restore_archive':
                    if ($file['status'] !== 'archived') {
                        throw new \Exception('File tidak dalam status archived.');
                    }
                    if (! $permissionService->canManage($legacy, $userId)) {
                        $audit($filingId, $userId, 'restore_denied', 'Permission denied for archive restore');
                        throw new \Exception('Anda tidak memiliki izin untuk me-restore arsip ini.');
                    }

                    $drive->updateMetadata($filingId, ['status' => 'active']);
                    $audit($filingId, $userId, 'restore_archive', 'Restored from archive');

                    return response()->json(['success' => true, 'message' => 'Arsip berhasil diaktifkan kembali.']);

                case 'permanent_delete':
                    if (! in_array($file['status'] ?? 'active', ['trashed', 'deleted'], true)) {
                        throw new \Exception('Hanya file di Sampah yang bisa dihapus permanen.');
                    }
                    if (! $permissionService->isAdmin($userId) && ! $permissionService->canManage($legacy, $userId)) {
                        $audit($filingId, $userId, 'permanent_delete_denied', 'Permission denied');
                        throw new \Exception('Anda tidak memiliki izin untuk menghapus permanen file ini.');
                    }

                    $storagePath = $drive->buildStoragePath($file);
                    if ($storagePath !== '') {
                        $storageService->deletePhysicalFile($storagePath);
                    }

                    $drive->updateMetadata($filingId, ['status' => 'deleted', 'deleted_at' => date('Y-m-d H:i:s')]);
                    $audit($filingId, $userId, 'permanent_delete', 'Permanently deleted from system');

                    return response()->json(['success' => true, 'message' => 'File berhasil dihapus secara permanen.']);

                case 'bulk':
                    return $this->bulkActionNew($request, $drive, $permissionService, $storageService, $audit, $userId);

                default:
                    throw new \Exception('Action tidak valid.');
            }
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function activeFileTypes(\PDO $pdoRun): array
    {
        try {
            $stmt = $pdoRun->query('SELECT type_code, type_name FROM sys_filing_filetype WHERE is_active = 1 ORDER BY sort_order ASC');

            return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function filingBulkAction(Request $request, \PDO $pdoRun, FilingPermissionService $permissionService, FilingStorageService $storageService, FilingSystem $filingModel, callable $filingActionAudit, int $userId)
    {
        $bulkAction = (string) $request->input('bulk_action', '');
        $rawIds = $request->input('filing_ids', []);
        if (! is_array($rawIds)) {
            throw new \Exception('Format ID tidak valid.');
        }

        $ids = array_unique(array_filter(array_map('intval', $rawIds)));
        if (empty($ids)) {
            throw new \Exception('Tidak ada file yang dipilih.');
        }
        if (count($ids) > 50) {
            throw new \Exception('Maksimal 50 file untuk aksi massal.');
        }

        $validActions = ['archive', 'move_trash', 'restore', 'permanent_delete'];
        if (! in_array($bulkAction, $validActions, true)) {
            throw new \Exception('Aksi massal tidak valid.');
        }

        $files = $filingModel->getFilesByIds($ids);

        $stats = [
            'success' => true,
            'message' => 'Proses massal selesai.',
            'total_requested' => count($ids),
            'processed' => 0,
            'skipped' => 0,
            'failed' => 0,
            'items' => [],
        ];

        foreach ($ids as $id) {
            $itemRes = ['filing_id' => $id, 'status' => 'skipped', 'message' => ''];

            try {
                $pdoRun->beginTransaction();

                if (! isset($files[$id])) {
                    $itemRes['message'] = 'File tidak ditemukan.';
                    $stats['skipped']++;
                    $stats['items'][] = $itemRes;
                    $pdoRun->rollBack();

                    continue;
                }

                $currFile = $files[$id];

                if (! $permissionService->canManage($currFile, $userId)) {
                    $filingActionAudit($id, $userId, 'bulk_action_denied', 'Permission denied for bulk '.$bulkAction);
                    $itemRes['message'] = 'Permission denied.';
                    $stats['skipped']++;
                    $stats['items'][] = $itemRes;
                    $pdoRun->commit();

                    continue;
                }

                if ($bulkAction === 'archive') {
                    if ($currFile['status'] !== 'active') {
                        $itemRes['message'] = 'Status tidak valid untuk arsip.';
                        $stats['skipped']++;
                        $stats['items'][] = $itemRes;
                        $pdoRun->rollBack();

                        continue;
                    }
                    DB::connection('run')->table('sys_filing')->where('rec_id', $id)->update(['status' => 'archived']);
                    $filingActionAudit($id, $userId, 'bulk_archive', 'Bulk archived by user');
                    $itemRes['status'] = 'success';
                    $itemRes['message'] = 'Archived.';
                } elseif ($bulkAction === 'move_trash') {
                    if (! in_array($currFile['status'], ['active', 'archived'], true)) {
                        $itemRes['message'] = 'Status tidak valid untuk trash.';
                        $stats['skipped']++;
                        $stats['items'][] = $itemRes;
                        $pdoRun->rollBack();

                        continue;
                    }
                    DB::connection('run')->table('sys_filing')->where('rec_id', $id)->update(['status' => 'trashed', 'deleted_at' => now()]);
                    $filingActionAudit($id, $userId, 'bulk_move_trash', 'Bulk moved to trash');
                    $itemRes['status'] = 'success';
                    $itemRes['message'] = 'Moved to trash.';
                } elseif ($bulkAction === 'restore') {
                    if (! in_array($currFile['status'], ['trashed', 'archived'], true)) {
                        $itemRes['message'] = 'Status tidak valid untuk restore.';
                        $stats['skipped']++;
                        $stats['items'][] = $itemRes;
                        $pdoRun->rollBack();

                        continue;
                    }
                    DB::connection('run')->table('sys_filing')->where('rec_id', $id)->update(['status' => 'active', 'deleted_at' => null, 'trashed_at' => null]);
                    $auditAct = $currFile['status'] === 'trashed' ? 'bulk_restore' : 'bulk_restore_archive';
                    $filingActionAudit($id, $userId, $auditAct, 'Bulk restored by user');
                    $itemRes['status'] = 'success';
                    $itemRes['message'] = 'Restored.';
                } elseif ($bulkAction === 'permanent_delete') {
                    if (! $permissionService->isAdmin($userId) && ! $permissionService->canManage($currFile, $userId)) {
                        $filingActionAudit($id, $userId, 'bulk_action_denied', 'Permission denied for permanent delete');
                        $itemRes['message'] = 'Permission denied.';
                        $stats['skipped']++;
                        $stats['items'][] = $itemRes;
                        $pdoRun->commit();

                        continue;
                    }
                    if (! in_array($currFile['status'], ['trashed', 'deleted'], true)) {
                        $itemRes['message'] = 'Status tidak valid.';
                        $stats['skipped']++;
                        $stats['items'][] = $itemRes;
                        $pdoRun->rollBack();

                        continue;
                    }

                    $storageService->deletePhysicalFile($currFile['storage_path']);
                    DB::connection('run')->table('sys_filing')->where('rec_id', $id)->update(['status' => 'deleted', 'deleted_at' => now()]);
                    $filingActionAudit($id, $userId, 'bulk_permanent_delete', 'Bulk permanently deleted');
                    $itemRes['status'] = 'success';
                    $itemRes['message'] = 'Permanently deleted.';
                }

                if ($itemRes['status'] === 'success') {
                    $stats['processed']++;
                    $pdoRun->commit();
                }
                $stats['items'][] = $itemRes;

            } catch (\Throwable $e) {
                if ($pdoRun->inTransaction()) {
                    $pdoRun->rollBack();
                }

                try {
                    $filingActionAudit($id, $userId, 'bulk_action_failed', 'Error: '.substr($e->getMessage(), 0, 100));
                } catch (\Throwable) {
                }

                $itemRes['status'] = 'failed';
                $itemRes['message'] = $e->getMessage();
                $stats['failed']++;
                $stats['items'][] = $itemRes;
            }
        }

        return response()->json($stats);
    }

    private function bulkActionNew(Request $request, FileSystemDrive $drive, FilingPermissionService $permissionService, FilingStorageService $storageService, callable $audit, int $userId)
    {
        $bulkAction = (string) $request->input('bulk_action', '');
        $rawIds = $request->input('filing_ids', []);
        if (! is_array($rawIds)) {
            throw new \Exception('Format ID tidak valid.');
        }

        $ids = array_unique(array_filter(array_map('intval', $rawIds)));
        if (empty($ids)) {
            throw new \Exception('Tidak ada file yang dipilih.');
        }
        if (count($ids) > 50) {
            throw new \Exception('Maksimal 50 file untuk aksi massal.');
        }

        $validActions = ['archive', 'move_trash', 'restore', 'permanent_delete'];
        if (! in_array($bulkAction, $validActions, true)) {
            throw new \Exception('Aksi massal tidak valid.');
        }

        $files = $drive->getFilesByIds($ids);

        $stats = [
            'success' => true,
            'message' => 'Proses massal selesai.',
            'total_requested' => count($ids),
            'processed' => 0,
            'skipped' => 0,
            'failed' => 0,
            'items' => [],
        ];

        foreach ($ids as $id) {
            $itemRes = ['filing_id' => $id, 'status' => 'skipped', 'message' => ''];

            try {
                if (! isset($files[$id])) {
                    $itemRes['message'] = 'File tidak ditemukan.';
                    $stats['skipped']++;
                    $stats['items'][] = $itemRes;

                    continue;
                }

                $currFile = $files[$id];
                $legacy = $drive->mapToLegacyShape($currFile);

                if (! $permissionService->canManage($legacy, $userId)) {
                    $audit($id, $userId, 'bulk_action_denied', 'Permission denied for bulk '.$bulkAction);
                    $itemRes['message'] = 'Permission denied.';
                    $stats['skipped']++;
                    $stats['items'][] = $itemRes;

                    continue;
                }

                if ($bulkAction === 'archive') {
                    if ($currFile['status'] !== 'active') {
                        $itemRes['message'] = 'Status tidak valid untuk arsip.';
                        $stats['skipped']++;
                        $stats['items'][] = $itemRes;

                        continue;
                    }
                    $drive->updateMetadata($id, ['status' => 'archived']);
                    $audit($id, $userId, 'bulk_archive', 'Bulk archived by user');
                    $itemRes['status'] = 'success';
                    $itemRes['message'] = 'Archived.';
                } elseif ($bulkAction === 'move_trash') {
                    if (! in_array($currFile['status'], ['active', 'archived'], true)) {
                        $itemRes['message'] = 'Status tidak valid untuk trash.';
                        $stats['skipped']++;
                        $stats['items'][] = $itemRes;

                        continue;
                    }
                    $drive->updateMetadata($id, ['status' => 'trashed', 'deleted_at' => date('Y-m-d H:i:s')]);
                    $audit($id, $userId, 'bulk_move_trash', 'Bulk moved to trash');
                    $itemRes['status'] = 'success';
                    $itemRes['message'] = 'Moved to trash.';
                } elseif ($bulkAction === 'restore') {
                    if (! in_array($currFile['status'], ['trashed', 'archived'], true)) {
                        $itemRes['message'] = 'Status tidak valid untuk restore.';
                        $stats['skipped']++;
                        $stats['items'][] = $itemRes;

                        continue;
                    }
                    $drive->updateMetadata($id, ['status' => 'active', 'deleted_at' => null, 'trashed_at' => null]);
                    $audit($id, $userId, 'bulk_restore', 'Bulk restored by user');
                    $itemRes['status'] = 'success';
                    $itemRes['message'] = 'Restored.';
                } elseif ($bulkAction === 'permanent_delete') {
                    if (! $permissionService->isAdmin($userId) && ! $permissionService->canManage($legacy, $userId)) {
                        $audit($id, $userId, 'bulk_action_denied', 'Permission denied for permanent delete');
                        $itemRes['message'] = 'Permission denied.';
                        $stats['skipped']++;
                        $stats['items'][] = $itemRes;

                        continue;
                    }
                    if (! in_array($currFile['status'], ['trashed', 'deleted'], true)) {
                        $itemRes['message'] = 'Status tidak valid.';
                        $stats['skipped']++;
                        $stats['items'][] = $itemRes;

                        continue;
                    }

                    $storagePath = $drive->buildStoragePath($currFile);
                    if ($storagePath !== '') {
                        $storageService->deletePhysicalFile($storagePath);
                    }
                    $drive->updateMetadata($id, ['status' => 'deleted', 'deleted_at' => date('Y-m-d H:i:s')]);
                    $audit($id, $userId, 'bulk_permanent_delete', 'Bulk permanently deleted');
                    $itemRes['status'] = 'success';
                    $itemRes['message'] = 'Permanently deleted.';
                }

                if ($itemRes['status'] === 'success') {
                    $stats['processed']++;
                }
                $stats['items'][] = $itemRes;

            } catch (\Throwable $e) {
                try {
                    $audit($id, $userId, 'bulk_action_failed', 'Error: '.substr($e->getMessage(), 0, 100));
                } catch (\Throwable) {
                }

                $itemRes['status'] = 'failed';
                $itemRes['message'] = $e->getMessage();
                $stats['failed']++;
                $stats['items'][] = $itemRes;
            }
        }

        return response()->json($stats);
    }

    public function testDocument(Request $request)
    {
        $this->syncLegacyRequestSuperglobals($request);

        $testDocumentData = new TestDocumentDataService(
            DB::connection('mysql')->getPdo(),
            DB::connection('run')->getPdo(),
            DB::connection('war')->getPdo(),
            $this->ftpConfig(),
        );

        if ($request->query('debug_ftp') === '1') {
            try {
                $ftpTest = new FtpStorage($this->ftpConfig());
                $ftpTest->connect();

                return response()->json([
                    'status' => 'success',
                    'message' => 'FTP connection OK',
                    'host' => $this->ftpConfig()['host'] ?? '',
                    'user' => substr((string) ($this->ftpConfig()['user'] ?? ''), 0, -10).'****',
                    'port' => $this->ftpConfig()['port'] ?? 21,
                    'ssl' => $this->ftpConfig()['ssl'] ?? false,
                ]);
            } catch (\Throwable $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($request->isMethod('post') && $request->request->has('save_input')) {
            $result = $testDocumentData->saveInput($request->request->all(), session()->all());

            if (($result['type'] ?? '') === 'redirect') {
                return redirect($result['url']);
            }

            return response($result['message'] ?? 'Gagal menyimpan data Berita Acara.', (int) ($result['status'] ?? 500));
        }

        if ($request->isMethod('post') && $request->request->has('file_action')) {
            $result = $testDocumentData->fileAction($request->request->all(), session()->all(), $request->files->all());

            if ($request->expectsJson()) {
                return response()->json($result['payload'] ?? [], (int) ($result['status'] ?? 200));
            }

            $payload = $result['payload'] ?? [];
            if (is_array($payload) && ($payload['status'] ?? '') === 'success') {
                $request->session()->flash('success_msg', (string) ($payload['msg'] ?? 'Upload berhasil.'));
            } else {
                $request->session()->flash('error_msg', (string) ($payload['msg'] ?? 'Upload gagal.'));
            }

            return redirect()->route('filing-system.berita-acara');
        }

        if ($request->query->has('ajax_crc_b2_folders')) {
            try {
                return response()->json($this->testDocumentCrcB2Service()->folders([
                    'search' => $request->query('search', ''),
                    'date_start' => $request->query('date_start', ''),
                    'date_end' => $request->query('date_end', ''),
                    'page' => $request->query('page', 1),
                ]));
            } catch (\Throwable $e) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }

        if ($request->query->has('ajax_crc_b2_files')) {
            try {
                return response()->json($this->testDocumentCrcB2Service()->files((string) $request->query('admin_no', '')));
            } catch (\Throwable $e) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }

        if ($request->query->has('ajax_crc_b2_collect')) {
            try {
                return response()->json($this->testDocumentCrcB2Service()->collect((string) $request->query('admin_no', '')));
            } catch (\Throwable $e) {
                error_log('[CRC_B2_AJAX_ERROR] '.$e->getMessage());

                return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }

        if ($request->query->has('ajax_get_submenu')) {
            try {
                return response()->json($testDocumentData->submenu((int) $request->query('ajax_get_submenu', 0)));
            } catch (\Throwable $e) {
                return response()->json(['error' => $e->getMessage()]);
            }
        }

        if ($request->query->has('ajax_get_entry')) {
            return response()->json($testDocumentData->entry((int) $request->query('ajax_get_entry', 0)));
        }

        if ($request->query->has('ajax_admin_info')) {
            try {
                return response()->json($testDocumentData->adminInfo(
                    (string) $request->query('admin_val', ''),
                    (int) $request->query('filing_id', 0),
                ));
            } catch (\Throwable $e) {
                return response()->json(['error' => $e->getMessage()]);
            }
        }

        if ($request->query->has('debug_outbound')) {
            return response()->json(
                $testDocumentData->debugOutbound((string) $request->query('admin', '')),
                200,
                [],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            );
        }

        if ($request->query->has('download_issues')) {
            $csv = $testDocumentData->issueCsv((int) $request->query('download_issues', 0));
            if ($csv === null) {
                return response('Data filing tidak ditemukan.', 404);
            }

            return response($csv['content'], 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$csv['filename'].'"',
            ]);
        }

        if ($request->query->has('download_crc_raw')) {
            $url = $testDocumentData->crcRawDownloadUrl(
                (int) $request->query('download_crc_raw', 0),
                (string) $request->query('file', '')
            );

            if ($url === null) {
                return response('File CRC RAW tidak valid atau data filing tidak ditemukan.', 404);
            }

            return redirect()->away($url);
        }

        if ($request->query->has('download_outbound')) {
            $download = $testDocumentData->outboundDownload(
                (int) $request->query('download_outbound', 0),
                (string) $request->query('file', '')
            );

            if (($download['type'] ?? '') === 'file') {
                return response()->download($download['path'], $download['filename'], [
                    'Content-Type' => 'application/zip',
                ])->deleteFileAfterSend(true);
            }

            if (($download['type'] ?? '') === 'content') {
                return response($download['content'], 200, [
                    'Content-Type' => 'application/zip',
                    'Content-Disposition' => 'attachment; filename="'.$download['filename'].'"',
                ]);
            }

            if (($download['type'] ?? '') === 'redirect') {
                return redirect()->away($download['url']);
            }

            return response($download['message'] ?? 'Gagal download outbound.', (int) ($download['status'] ?? 500));
        }

        if ($request->query->has('download_file')) {
            $download = $testDocumentData->filingFileDownload((int) $request->query('download_file', 0));

            if (($download['type'] ?? '') === 'file') {
                return response()->download($download['path'], $download['filename'], [
                    'Content-Type' => 'application/octet-stream',
                ])->deleteFileAfterSend(true);
            }

            if (($download['type'] ?? '') === 'redirect') {
                return redirect()->away($download['url']);
            }

            return response($download['message'] ?? 'Gagal download file.', (int) ($download['status'] ?? 500));
        }

        if ($request->query->has('download_admin_folder')) {
            $download = $testDocumentData->adminFolderDownload((int) $request->query('download_admin_folder', 0));

            if (($download['type'] ?? '') === 'content') {
                return response($download['content'], 200, [
                    'Content-Type' => 'application/zip',
                    'Content-Disposition' => 'attachment; filename="'.$download['filename'].'"',
                ]);
            }

            return response($download['message'] ?? 'Gagal download folder admin.', (int) ($download['status'] ?? 500));
        }

        if ($request->query->has('download_crc_b2')) {
            $adminNo = $testDocumentData->filingAdminNo((int) $request->query('download_crc_b2', 0));
            if ($adminNo === null) {
                return response('Data filing tidak ditemukan.', 404);
            }

            return $this->crcB2DownloadResponse($this->testDocumentCrcB2Service()->downloadZipByAdmin($adminNo));
        }

        if ($request->query->has('download_crc_b2_admin')) {
            return $this->crcB2DownloadResponse(
                $this->testDocumentCrcB2Service()->downloadZipByAdmin((string) $request->query('download_crc_b2_admin', ''))
            );
        }

        if ($request->query->has('delete_entry')) {
            $result = $testDocumentData->deleteEntry((int) $request->query('delete_entry', 0), session()->all());

            if (($result['type'] ?? '') === 'redirect') {
                return redirect($result['url']);
            }

            return response($result['message'] ?? 'Gagal menghapus data Berita Acara.', (int) ($result['status'] ?? 500));
        }

        if ($request->query('sync_assignments') === '1') {
            $testDocumentData->syncAssignments();

            return redirect()->route('filing-system.berita-acara');
        } else {
            $data = $testDocumentData->getPageData($request->query(), session()->all());
        }

        $search = $data['search'] ?? $request->query('search', '');
        $fDate = $data['f_date'] ?? $request->query('f_date', '');
        $fSpv = $data['f_spv'] ?? $request->query('f_spv', '');
        $fClient = $data['f_client'] ?? $request->query('f_client', '');
        $fAdmin = $data['f_admin'] ?? $request->query('f_admin', '');
        $activeView = $request->query('view', 'main');
        $userId = (int) auth_user_id();
        $pdoRun = DB::connection('run')->getPdo();

        $canManageBeritaAcara = $testDocumentData->userHasTadRole($userId, ['TAD ADMIN', 'TAD STAFF', 'SUPER ADMIN'])
            || ! $testDocumentData->userHasTadRole($userId, ['TAD SPV']);
        $canUploadBeritaAcaraFiles = $canManageBeritaAcara || $testDocumentData->userHasTadRole($userId, ['TAD SPV']);

        $uploadForId = (int) $request->query('upload_for', 0);
        $uploadTarget = null;
        $testDocumentAllRows = is_array($data['data_list'] ?? null) ? $data['data_list'] : [];
        $testDocumentPage = max(1, (int) $request->query('page', 1));
        $testDocumentPerPage = 25;
        $testDocumentTotal = count($testDocumentAllRows);
        $testDocumentTotalPages = max(1, (int) ceil($testDocumentTotal / $testDocumentPerPage));

        if ($testDocumentPage > $testDocumentTotalPages) {
            $testDocumentPage = $testDocumentTotalPages;
        }

        $data['data_list'] = array_slice($testDocumentAllRows, ($testDocumentPage - 1) * $testDocumentPerPage, $testDocumentPerPage);
        $pageQueryBase = $request->query();
        unset($pageQueryBase['page']);

        if ($uploadForId > 0) {
            foreach ($testDocumentAllRows as $candidateRow) {
                if ((int) ($candidateRow['rec_id'] ?? 0) === $uploadForId) {
                    $uploadTarget = $candidateRow;
                    break;
                }
            }
        }

        $successMsg = (string) ($request->session()->pull('success_msg', ''));
        $errorMsg = (string) ($request->session()->pull('error_msg', ''));
        $request->session()->forget(['success_msg', 'error_msg']);

        return view('filing-system.berita-acara', $data + [
            'search' => $search,
            'f_date' => $fDate,
            'f_spv' => $fSpv,
            'f_client' => $fClient,
            'f_admin' => $fAdmin,
            'activeView' => $activeView,
            'userId' => $userId,
            'canManageBeritaAcara' => $canManageBeritaAcara,
            'canUploadBeritaAcaraFiles' => $canUploadBeritaAcaraFiles,
            'uploadForId' => $uploadForId,
            'uploadTarget' => $uploadTarget,
            'testDocumentAllRows' => $testDocumentAllRows,
            'testDocumentPage' => $testDocumentPage,
            'testDocumentPerPage' => $testDocumentPerPage,
            'testDocumentTotal' => $testDocumentTotal,
            'testDocumentTotalPages' => $testDocumentTotalPages,
            'pageQueryBase' => $pageQueryBase,
            'successMsg' => $successMsg,
            'errorMsg' => $errorMsg,
        ]);
    }

    private function testDocumentCrcB2Service(): TestDocumentCrcB2Service
    {
        return new TestDocumentCrcB2Service(
            $this->ftpConfig(),
            DB::connection('collector')->getPdo(),
        );
    }

    private function crcB2DownloadResponse(array $download)
    {
        if (($download['type'] ?? '') === 'content') {
            return response($download['content'], 200, [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="'.$download['filename'].'"',
            ]);
        }

        if (($download['type'] ?? '') === 'redirect') {
            return redirect()->away($download['url']);
        }

        return response($download['message'] ?? 'Gagal download CRC B2.', (int) ($download['status'] ?? 500));
    }

    private function syncLegacyRequestSuperglobals(Request $request): void
    {
        $_SERVER['REQUEST_METHOD'] = strtoupper($request->method());
        $_GET = $request->query();
        $_POST = $request->isMethod('get') ? [] : $request->request->all();
        $_REQUEST = array_merge($_GET, $_POST);
    }

    private function normalizeLegacyLocation(string $location, string $currentDir): string
    {
        if ($location === '') {
            return url('/modules/'.$currentDir.'/index.php');
        }

        if (preg_match('/^https?:\/\//i', $location) || str_starts_with($location, '/')) {
            return $location;
        }

        $parts = explode('?', $location, 2);
        $path = $parts[0];
        $query = isset($parts[1]) ? '?'.$parts[1] : '';

        if (! str_ends_with(strtolower($path), '.php')) {
            $path .= '.php';
        }

        return url('/modules/'.trim($currentDir.'/'.$path, '/')).$query;
    }

    public function upload(Request $request)
    {
        @set_time_limit(600);
        @ini_set('max_execution_time', '600');
        @ini_set('default_socket_timeout', '120');

        if ($request->query('action') === 'access_options') {
            try {
                $accessModel = new FilingAccess(DB::connection('run')->getPdo());
                $data = $accessModel->getAccessOptions();

                if (FileSystemDrive::isEnabled()) {
                    $userId = (int) auth_user_id();
                    $permService = new FilingPermissionService(DB::connection('run')->getPdo());
                    $attrs = $permService->resolveUserAttributes($userId);
                    $data['current_departments'] = $attrs['department'] ?? [];
                }

                return response()->json(['success' => true, 'data' => $data]);
            } catch (\Throwable $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()]);
            }
        }

        if (! $request->isMethod('post')) {
            return response()->json(['success' => false, 'message' => 'Invalid request method.']);
        }

        // Debug: struktur $_FILES mentah (berapa file yang dikirim browser)
        try {
            $rfName = $_FILES['raw_files']['name'] ?? null;
            $rfErr = $_FILES['raw_files']['error'] ?? null;
            $rfSize = $_FILES['raw_files']['size'] ?? null;
            $msg = '[UploadRawFiles] name_type='.gettype($rfName).' count='.(is_array($rfName) ? count($rfName) : 1)
                .' names='.json_encode(is_array($rfName) ? $rfName : [$rfName])
                .' errors='.json_encode(is_array($rfErr) ? $rfErr : [$rfErr])
                .' sizes='.json_encode(is_array($rfSize) ? $rfSize : [$rfSize])
                .' post_bytes='.($request->server('CONTENT_LENGTH', 0))
                .' max_file_uploads='.ini_get('max_file_uploads')
                .' upload_max_filesize='.ini_get('upload_max_filesize')
                .' post_max_size='.ini_get('post_max_size')
                .' raw_keys='.json_encode(array_keys($_FILES['raw_files'] ?? []))
                .' debug_file_count='.$request->input('_debug_file_count', '?')
                .' debug_files='.json_encode(array_map(fn ($i) => $request->input('_debug_file_'.$i, '?'), range(0, 10)))
                .' all_files='.json_encode($_FILES)
                .' all_post_keys='.json_encode(array_slice(array_keys($_POST), 0, 30));
            error_log($msg);
            $this->writeFilingDebug($msg);
        } catch (\Throwable $e) {
            error_log('[UploadRawFiles] debug error: '.$e->getMessage());
        }

        if (empty($_FILES) && empty($_POST) && (int) ($request->server('CONTENT_LENGTH', 0)) > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Upload gagal: Ukuran file melebihi batas server (post_max_size='.ini_get('post_max_size').', upload_max_filesize='.ini_get('upload_max_filesize').').',
            ]);
        }

        try {
            $controller = new FilingDriveController(DB::connection('run')->getPdo(), $this->ftpConfig());
            $response = $controller->handleUpload();
        } catch (\Throwable $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
        }

        return response()->json($response);
    }

    public function httpUploadReceiver(Request $request)
    {
        if (! $request->isMethod('post')) {
            return response()->json(['success' => false, 'message' => 'Method tidak diizinkan.'], 405);
        }

        $storageRoot = trim((string) (env('FILING_HTTP_UPLOAD_STORAGE_ROOT') ?: ''));
        $token = (string) (env('FILING_HTTP_UPLOAD_TOKEN') ?: '');

        if ($storageRoot === '' || $token === '') {
            return response()->json(['success' => false, 'message' => 'HTTP upload receiver belum dikonfigurasi.'], 503);
        }

        $authHeader = $request->header('Authorization', '') ?: (string) $request->server('REDIRECT_HTTP_AUTHORIZATION', '');
        if (! preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches) || ! hash_equals($token, trim($matches[1]))) {
            return response()->json(['success' => false, 'message' => 'Token tidak valid.'], 401);
        }

        if (! $request->hasFile('file')) {
            return response()->json(['success' => false, 'message' => 'File belum dikirim.'], 400);
        }

        $file = $request->file('file');
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return response()->json(['success' => false, 'message' => 'Upload file gagal. Error: '.$file->getError()], 400);
        }

        $storagePath = $this->normalizeHttpUploadPath((string) $request->input('storage_path', ''));
        if ($storagePath === '' || strtolower(pathinfo($storagePath, PATHINFO_EXTENSION)) !== 'zip') {
            return response()->json(['success' => false, 'message' => 'Storage path harus file ZIP.'], 400);
        }

        $storageRoot = rtrim(str_replace('\\', '/', $storageRoot), '/');
        $targetPath = $storageRoot.'/'.$storagePath;
        $targetDir = dirname($targetPath);

        if (! is_dir($targetDir) && ! @mkdir($targetDir, 0775, true) && ! is_dir($targetDir)) {
            return response()->json(['success' => false, 'message' => 'Gagal membuat folder tujuan.'], 500);
        }

        if (! is_writable($targetDir)) {
            return response()->json(['success' => false, 'message' => 'Folder tujuan tidak writable.'], 500);
        }

        try {
            $file->move($targetDir, basename($targetPath));
        } catch (\Throwable) {
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan file.'], 500);
        }

        $localSize = file_exists($targetPath) ? filesize($targetPath) : 0;
        $expectedSize = $request->input('file_size') !== null && is_numeric($request->input('file_size')) ? (int) $request->input('file_size') : 0;
        if ($expectedSize > 0 && $localSize !== $expectedSize) {
            @unlink($targetPath);

            return response()->json(['success' => false, 'message' => 'Ukuran file tidak sesuai.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'File berhasil diterima.',
            'storage_path' => $storagePath,
            'size' => $localSize,
        ]);
    }

    private function normalizeHttpUploadPath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#/+#', '/', $path);
        $path = ltrim((string) $path, '/');

        $parts = [];
        foreach (explode('/', $path) as $part) {
            $part = trim($part);
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                return '';
            }

            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    public function crcB2Receiver(Request $request)
    {
        $token = (string) (env('CRC_B2_RECEIVER_TOKEN') ?: '');

        if ($token !== '') {
            $authHeader = $request->header('Authorization', '') ?: (string) $request->server('REDIRECT_HTTP_AUTHORIZATION', '');
            if (! preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches) || ! hash_equals($token, trim($matches[1]))) {
                return response()->json(['success' => false, 'message' => 'Token tidak valid.'], 401);
            }
        }

        $action = (string) ($request->query('action', $request->input('action', '')));

        if ($action === 'list_folders') {
            $root = $this->crcB2Root();
            if (! is_dir($root) || ! is_readable($root)) {
                return response()->json(['success' => true, 'folders' => []]);
            }

            $folders = [];
            foreach (scandir($root) ?: [] as $folderName) {
                if ($folderName === '.' || $folderName === '..' || ! is_dir($root.'/'.$folderName)) {
                    continue;
                }
                $files = $this->crcB2Files($folderName);
                if (empty($files)) {
                    continue;
                }
                $latest = null;
                foreach ($files as $file) {
                    $ts = $file['modified_at_ts'] ?? null;
                    if (is_int($ts) && ($latest === null || $ts > $latest)) {
                        $latest = $ts;
                    }
                }
                $folders[] = [
                    'admin_no' => $folderName,
                    'count' => count($files),
                    'processed_at' => $latest ? date('d M Y H:i', $latest) : '',
                ];
            }

            usort($folders, static fn (array $a, array $b): int => strcasecmp($a['admin_no'], $b['admin_no']));

            return response()->json(['success' => true, 'folders' => $folders]);
        }

        if ($action === 'list_files') {
            $adminNo = (string) ($request->query('admin_no', $request->input('admin_no', '')));

            return response()->json(['success' => true, 'files' => $this->crcB2Files($adminNo)]);
        }

        if ($action === 'download_zip') {
            $adminNo = $this->crcB2SafeAdmin((string) ($request->query('admin_no', $request->input('admin_no', ''))));
            $dir = $this->crcB2AdminDir($adminNo);

            if ($dir === null) {
                return response()->json(['success' => false, 'message' => 'Folder nomor admin tidak ditemukan.'], 404);
            }

            if (! class_exists('ZipArchive')) {
                return response()->json(['success' => false, 'message' => 'ZipArchive belum aktif.'], 500);
            }

            $zipPath = tempnam(sys_get_temp_dir(), 'crc_b2_');
            if ($zipPath === false) {
                return response()->json(['success' => false, 'message' => 'Gagal membuat temporary ZIP.'], 500);
            }

            $zip = new \ZipArchive;
            if ($zip->open($zipPath, \ZipArchive::OVERWRITE) !== true) {
                @unlink($zipPath);

                return response()->json(['success' => false, 'message' => 'Gagal membuat ZIP.'], 500);
            }

            foreach (scandir($dir) ?: [] as $fileName) {
                if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'crc') {
                    continue;
                }
                $path = $dir.'/'.$fileName;
                if (is_file($path) && filesize($path) > 0) {
                    $zip->addFile($path, $fileName);
                }
            }

            $zip->close();
            $zipSize = file_exists($zipPath) ? filesize($zipPath) : 0;
            if ($zipSize === false || $zipSize <= 0) {
                @unlink($zipPath);

                return response()->json(['success' => false, 'message' => 'File CRC tidak ditemukan.'], 404);
            }

            return response()->download($zipPath, 'CRC_B2_'.$adminNo.'.zip', [
                'Content-Type' => 'application/zip',
                'Content-Transfer-Encoding' => 'binary',
                'Cache-Control' => 'must-revalidate',
                'Pragma' => 'public',
            ])->deleteFileAfterSend();
        }

        return response()->json(['success' => false, 'message' => 'Action tidak valid.'], 400);
    }

    private function crcB2Root(): string
    {
        $envRoot = (string) (env('CRC_B2_STORAGE_ROOT') ?: '');
        $collectorFinal = realpath(base_path('modules/cbt_ops/collector/final'));
        $defaultRoot = $collectorFinal && is_dir($collectorFinal)
            ? $collectorFinal
            : (is_dir(base_path('modules/cbt_ops/filing_system/final')) ? base_path('modules/cbt_ops/filing_system/final') : base_path('modules/cbt_ops/filing_system'));

        return rtrim(str_replace('\\', '/', $envRoot !== '' ? $envRoot : $defaultRoot), '/');
    }

    private function crcB2SafeAdmin(string $adminNo): string
    {
        $adminNo = basename($adminNo);
        $adminNo = preg_replace('/\s+/', '_', $adminNo);
        $adminNo = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $adminNo);

        return trim((string) $adminNo, '._-');
    }

    private function crcB2AdminDir(string $adminNo): ?string
    {
        $root = $this->crcB2Root();
        $safeAdmin = $this->crcB2SafeAdmin($adminNo);
        if ($safeAdmin === '') {
            return null;
        }

        $realRoot = realpath($root);
        $realDir = realpath($root.'/'.$safeAdmin);
        if (! $realRoot || ! $realDir) {
            return null;
        }

        $realRoot = str_replace('\\', '/', $realRoot);
        $realDir = str_replace('\\', '/', $realDir);

        if (strpos($realDir, $realRoot.'/') !== 0 || ! is_dir($realDir)) {
            return null;
        }

        return $realDir;
    }

    private function crcB2Files(string $adminNo): array
    {
        $dir = $this->crcB2AdminDir($adminNo);
        if ($dir === null) {
            return [];
        }

        $safeAdmin = $this->crcB2SafeAdmin($adminNo);
        $files = [];
        foreach (scandir($dir) ?: [] as $fileName) {
            if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'crc') {
                continue;
            }
            $path = $dir.'/'.$fileName;
            if (! is_file($path)) {
                continue;
            }
            $modifiedAt = filemtime($path) ?: null;
            $files[] = [
                'file_name' => $fileName,
                'file_type' => 'crc',
                'relative_path' => 'CRC_B2/'.$safeAdmin.'/'.$fileName,
                'modified_at_ts' => $modifiedAt,
                'uploaded_at' => $modifiedAt ? date('d M Y H:i', $modifiedAt) : '',
            ];
        }

        usort($files, static fn (array $a, array $b): int => strcasecmp($a['file_name'], $b['file_name']));

        return $files;
    }

    public function adminIndex(Request $request)
    {

        $userId = (int) auth_user_id();

        if ($userId <= 0) {
            return redirect()->route('login');
        }

        $pdoRun = DB::connection('run')->getPdo();
        $storageService = new FilingStorageService($this->ftpConfig());
        $permissionService = new FilingPermissionService($pdoRun);
        $adminService = new FilingAdminService($pdoRun, $storageService, $permissionService);

        try {
            $adminService->requireAdmin($userId);
        } catch (\Throwable $e) {
            abort(403, $e->getMessage());
        }

        try {
            DB::connection('run')->table('sys_filing_audit')->insert([
                'user_id' => $userId,
                'action' => 'admin_view',
                'notes' => 'Accessed admin panel',
                'user_agent' => (string) $request->userAgent(),
                'ip_address' => (string) $request->ip(),
            ]);
        } catch (\Throwable) {
        }

        $filters = [
            'search' => (string) $request->query('search', ''),
            'page' => max(1, (int) $request->query('page', 1)),
            'limit' => 20,
            'status' => (string) $request->query('status', ''),
            'security_level' => (string) $request->query('security_level', ''),
            'access_mode' => (string) $request->query('access_mode', ''),
            'declared_file_type' => (string) $request->query('declared_file_type', ''),
        ];

        $totalItems = $adminService->countAdminFiles($filters);
        $totalPages = (int) ceil($totalItems / $filters['limit']);
        $offset = ($filters['page'] - 1) * $filters['limit'];
        $items = $adminService->getAdminFiles($filters, $filters['limit'], $offset);

        return view('filing-system.admin', [
            'filters' => $filters,
            'items' => $items,
            'totalItems' => $totalItems,
            'totalPages' => $totalPages,
            'adminActionUrl' => url('/modules/cbt_ops/filing_system/admin_action.php'),
            'auditBaseUrl' => url('/modules/cbt_ops/filing_system/audit.php'),
        ]);
    }

    public function adminAction(Request $request)
    {

        if (! $request->isMethod('post')) {
            return response()->json(['success' => false, 'message' => 'Invalid request method.']);
        }

        $userId = (int) auth_user_id();

        if ($userId <= 0) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $action = (string) $request->input('action', '');
        $filingId = (int) $request->input('filing_id', 0);

        if ($filingId <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid File ID']);
        }

        $pdoRun = DB::connection('run')->getPdo();
        $storageService = new FilingStorageService($this->ftpConfig());
        $permissionService = new FilingPermissionService($pdoRun);
        $adminService = new FilingAdminService($pdoRun, $storageService, $permissionService);

        if (! $adminService->isFilingSystemAdmin($userId)) {
            return response()->json(['success' => false, 'message' => 'Access Denied: Admin only'], 403);
        }

        try {
            $res = match ($action) {
                'block' => $adminService->blockFile($filingId, $userId),
                'unblock' => $adminService->unblockFile($filingId, $userId),
                'restore' => $adminService->restoreFile($filingId, $userId),
                'permanent_delete' => $adminService->permanentDeleteFile($filingId, $userId, (string) $request->input('confirm_text', '')),
                'diagnose' => $adminService->runStorageDiagnostics($filingId, $userId),
                default => throw new \Exception('Unknown admin action.'),
            };

            return response()->json($res);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function info(Request $request)
    {

        $userId = (int) auth_user_id();

        if ($userId <= 0) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $filingId = (int) ($request->query('id', $request->input('id', 0)));

        if ($filingId <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid File ID']);
        }

        if (FileSystemDrive::isEnabled()) {
            return $this->infoNew($request, $filingId, $userId);
        }

        $pdoRun = DB::connection('run')->getPdo();
        $permService = new FilingPermissionService($pdoRun);
        $filingModel = new FilingSystem($pdoRun);
        $accessModel = new FilingAccess($pdoRun);
        $shareModel = new FilingShare($pdoRun);
        $auditModel = new FilingAudit($pdoRun);

        $isAdmin = $permService->isAdmin($userId);

        try {
            $file = $filingModel->getFileById($filingId);

            if (! $file) {
                throw new \Exception('File tidak ditemukan.');
            }

            if (! $permService->canView($file, $userId)) {
                throw new \Exception('Anda tidak memiliki izin untuk melihat detail file ini.');
            }

            $owner = $filingModel->getFileOwnerInfo($file['uploaded_by']);

            $permissionResult = $permService->getPermissionResult($file, $userId);
            $canSeeFullInfo = $isAdmin || (int) $file['uploaded_by'] === $userId || $permissionResult['can_manage'];

            $permissions = $canSeeFullInfo ? $accessModel->getPermissionSummary($filingId) : [];
            $shares = $canSeeFullInfo ? $shareModel->getShareSummary($filingId) : ['stats' => ['active_links' => 0, 'total_access' => 0], 'details' => []];
            $audits = $canSeeFullInfo ? $auditModel->getRecentAuditByFile($filingId, 10) : [];

            $response = [
                'success' => true,
                'is_admin' => $isAdmin,
                'can_see_full_info' => $canSeeFullInfo,
                'file' => [
                    'rec_id' => $file['rec_id'],
                    'display_name' => $file['display_name'],
                    'original_name' => $file['original_name'],
                    'zip_size' => $file['zip_size'],
                    'formatted_size' => $filingModel->formatFileSize($file['zip_size'] ?: 0),
                    'file_count' => $file['file_count'],
                    'detected_file_type' => $file['detected_file_type'],
                    'status' => $file['status'],
                    'status_label' => $filingModel->formatStatusLabel($file['status']),
                    'security_level' => $file['security_level'],
                    'security_label' => $filingModel->formatSecurityLabel($file['security_level']),
                    'created_at' => $file['created_at'],
                    'updated_at' => $file['updated_at'],
                    'owner_name' => $owner['account_nm'] ?? 'Unknown',
                    'owner_alias' => $owner['alias_nm'] ?? '',
                ],
                'permissions' => $permissions,
                'shares' => $shares,
                'audits' => array_map(function ($a) use ($auditModel, $isAdmin) {
                    return [
                        'created_at' => $a['created_at'],
                        'action' => $a['action'],
                        'action_label' => $auditModel->formatActionLabel($a['action']),
                        'user_name' => $a['user_id'] ? ($a['user_name'] ?? 'Unknown') : 'SYSTEM/CRON',
                        'notes' => $a['notes'],
                        'ip_address' => $isAdmin ? ($a['ip_address'] ?? '-') : null,
                        'user_agent' => $isAdmin ? ($a['user_agent'] ?? '-') : null,
                    ];
                }, $audits),
            ];

            if ($canSeeFullInfo) {
                $response['file'] += [
                    'file_code' => $file['file_code'],
                    'total_uncompressed_size' => $file['total_uncompressed_size'],
                    'formatted_uncompressed_size' => $filingModel->formatFileSize($file['total_uncompressed_size'] ?: 0),
                    'declared_file_type' => $file['declared_file_type'],
                    'access_mode' => $file['access_mode'],
                    'is_share_enabled' => (bool) $file['is_share_enabled'],
                    'expired_at' => $file['expired_at'],
                    'expired_action' => $file['expired_action'],
                    'expired_processed_at' => $file['expired_processed_at'],
                    'notes' => $file['notes'],
                    'keywords' => $file['keywords'],
                ];
            }

            if ($isAdmin) {
                $response['file']['storage_root'] = $file['storage_root'];
                $response['file']['storage_dir'] = $file['storage_dir'];
                $response['file']['storage_name'] = $file['storage_name'];
                $response['file']['storage_path'] = $file['storage_path'];
            }

            return response()->json($response);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Info drawer untuk tabel baru (file_system + file_shareto + file_share_link).
     */
    private function infoNew(Request $request, int $filingId, int $userId)
    {
        $pdoRun = DB::connection('run')->getPdo();
        $drive = new FileSystemDrive($pdoRun);
        $permService = new FilingPermissionService($pdoRun);

        try {
            $file = $drive->getFileById($filingId);
            if (! $file) {
                throw new \Exception('File tidak ditemukan.');
            }

            $legacy = $drive->mapToLegacyShape($file);
            if (! $permService->canView($legacy, $userId)) {
                throw new \Exception('Anda tidak memiliki izin untuk melihat detail file ini.');
            }

            $isAdmin = $permService->isAdmin($userId);
            $permissionResult = $permService->getPermissionResult($legacy, $userId);
            $canSeeFullInfo = $isAdmin || (int) $file['userid'] === $userId || $permissionResult['can_manage'];

            $owner = $drive->getOwnerInfo((int) $file['userid']);
            $shares = $drive->getSharesByFileId($filingId);

            $sharedToTargets = [];
            foreach ($shares as $share) {
                $cat = (int) ($share['share_cat'] ?? 0);
                if (! in_array($cat, [2, 3], true)) {
                    continue;
                }
                $value = (string) ($share['othercode'] ?? '');
                if ($value === '') {
                    continue;
                }
                $sharedToTargets[] = [
                    'share_cat' => $cat,
                    'type' => $cat === 2 ? 'divisi' : 'company',
                    'code' => $value,
                    'label' => $this->shareLabel($pdoRun, $cat, $value),
                ];
            }

            $shareLinks = [];
            if ($canSeeFullInfo) {
                try {
                    $stmt = $pdoRun->prepare('SELECT * FROM file_share_link WHERE filesys_id = ? ORDER BY created_at DESC');
                    $stmt->execute([$filingId]);
                    $shareLinks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                } catch (\Throwable $e) {
                    $shareLinks = [];
                }
            }

            $audits = [];
            if ($canSeeFullInfo) {
                try {
                    $stmt = $pdoRun->prepare('
                        SELECT a.*, u.account_nm AS user_name
                        FROM sys_filing_audit a
                        LEFT JOIN sysitc_users u ON a.user_id = u.rec_id
                        WHERE a.filing_id = ?
                        ORDER BY a.created_at DESC
                        LIMIT 10
                    ');
                    $stmt->execute([$filingId]);
                    $audits = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                } catch (\Throwable $e) {
                    $audits = [];
                }
            }

            $payload = [
                'success' => true,
                'is_admin' => $isAdmin,
                'can_see_full_info' => $canSeeFullInfo,
                'file' => [
                    'rec_id' => (int) $file['rec_id'],
                    'display_name' => $file['file_name'],
                    'original_name' => $file['file_name'],
                    'file_code' => $file['trxno'],
                    'zip_size' => (int) $file['file_zip_size'],
                    'formatted_size' => $drive->formatFileSize((int) $file['file_zip_size']),
                    'file_count' => 1,
                    'file_type' => $file['file_type'],
                    'declared_file_type' => $drive->mapExtToType($file['file_type'] ?? ''),
                    'detected_file_type' => $drive->mapExtToType($file['file_type'] ?? ''),
                    'status' => $file['status'] ?? 'active',
                    'status_label' => ucfirst($file['status'] ?? 'active'),
                    'security_level' => $file['security_level'] ?? 'normal',
                    'security_label' => ucfirst($file['security_level'] ?? 'normal'),
                    'access_mode' => $file['access_mode'] ?? 'private',
                    'created_at' => $file['create_dt'],
                    'updated_at' => $file['lupdt'],
                    'owner_name' => $owner['account_nm'] ?? 'Unknown',
                    'owner_alias' => $owner['alias_nm'] ?? '',
                    'notes' => $file['file_notes'],
                    'keywords' => $file['file_notes'],
                    'folder_loc' => $file['folder_loc'],
                    'client_nm' => $file['client_nm'],
                    'expired_at' => $file['expired_at'],
                    'expired_action' => $file['expired_action'] ?? 'trash',
                    'expired_processed_at' => $file['expired_processed_at'],
                    'is_share_enabled' => false,
                ],
                'permissions' => [],
                'shares' => [
                    'stats' => [
                        'active_links' => count(array_filter($shareLinks, fn ($s) => ! empty($s['is_active']) && empty($s['revoked_at']))),
                        'total_access' => array_sum(array_map(fn ($s) => (int) $s['access_count'], $shareLinks)),
                    ],
                    'details' => $shareLinks,
                ],
                'audits' => array_map(function (array $a): array {
                    return [
                        'created_at' => $a['created_at'],
                        'action' => $a['action'],
                        'action_label' => ucwords(str_replace('_', ' ', (string) $a['action'])),
                        'user_name' => $a['user_id'] ? ($a['user_name'] ?? 'Unknown') : 'SYSTEM/CRON',
                        'notes' => $a['notes'],
                        'ip_address' => null,
                        'user_agent' => null,
                    ];
                }, $audits),
                'shareto' => $shares,
                'shared_to' => $sharedToTargets,
            ];

            if ($isAdmin) {
                $payload['file']['storage_root'] = rtrim((string) ($drive->getFolderByLocation((string) $file['folder_loc'])['notes'] ?? ''), '/');
                $payload['file']['storage_path'] = $drive->buildStoragePath($file);
                $payload['file']['storage_name'] = $file['upload_flnm'];
            }

            return response()->json($payload);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function share(Request $request)
    {

        $userId = (int) auth_user_id();

        if ($userId <= 0) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $action = (string) ($request->input('action', $request->query('action', '')));

        $pdoRun = DB::connection('run')->getPdo();
        $permissionService = new FilingPermissionService($pdoRun);
        $shareService = new ShareCodeService;
        $shareModel = new FilingShare($pdoRun);

        if (FileSystemDrive::isEnabled()) {
            return $this->shareNew($request, $action, $pdoRun, $permissionService, $shareService, $userId);
        }

        try {
            switch ($action) {
                case 'create':
                    $filingId = (int) $request->input('filing_id', 0);

                    $stmt = $pdoRun->prepare("SELECT * FROM sys_filing WHERE rec_id = ? AND status = 'active' AND deleted_at IS NULL LIMIT 1");
                    $stmt->execute([$filingId]);
                    $file = $stmt->fetch(\PDO::FETCH_ASSOC);

                    if (! $file) {
                        throw new \Exception('File tidak ditemukan atau tidak aktif.');
                    }

                    if (! $permissionService->canShare($file, $userId)) {
                        $shareModel->logAudit($filingId, $userId, 'share_create_denied', 'Permission denied');
                        throw new \Exception('Anda tidak memiliki izin untuk membagikan file ini.');
                    }

                    if ($file['security_level'] === 'confidential') {
                        throw new \Exception('File confidential tidak dapat dibagikan via Share Code.');
                    }

                    $expiredAt = $request->input('expired_at') ?: null;
                    if ($file['security_level'] === 'restricted' && empty($expiredAt)) {
                        throw new \Exception('File restricted wajib memiliki Expired Date saat dibagikan.');
                    }

                    $rawCode = $shareService->generateShareCode();
                    $hash = $shareService->hashShareCode($rawCode);
                    $preview = $shareService->maskShareCode($rawCode);

                    $reqPassword = $request->input('requires_password') === '1';
                    $passwordHash = null;
                    if ($reqPassword && ! empty($request->input('share_password'))) {
                        $passwordHash = password_hash((string) $request->input('share_password'), PASSWORD_DEFAULT);
                    }

                    $data = [
                        'filing_id' => $filingId,
                        'share_code_preview' => $preview,
                        'share_code_hash' => $hash,
                        'created_by' => $userId,
                        'expired_at' => $expiredAt,
                        'max_access' => $request->input('max_access') ? (int) $request->input('max_access') : null,
                        'requires_password' => $reqPassword,
                        'password_hash' => $passwordHash,
                        'allow_download' => $request->has('allow_download') ? (int) $request->input('allow_download') : 1,
                    ];

                    $res = $shareModel->createShare($data);
                    if (! $res['success']) {
                        throw new \Exception($res['message']);
                    }

                    $pdoRun->prepare('UPDATE sys_filing SET is_share_enabled = 1 WHERE rec_id = ?')->execute([$filingId]);

                    $shareModel->logAudit($filingId, $userId, 'create_share', "Created share: {$preview}");

                    return response()->json([
                        'success' => true,
                        'message' => 'Share Code berhasil dibuat.',
                        'raw_code' => $rawCode,
                    ]);

                case 'list':
                    $filingId = (int) $request->input('filing_id', $request->query('filing_id', 0));
                    $shares = $shareModel->getSharesByFilingId($filingId);

                    return response()->json(['success' => true, 'data' => $shares]);

                case 'revoke':
                    $shareId = (int) $request->input('share_id', 0);
                    $stmt = $pdoRun->prepare('
                        SELECT s.filing_id, f.uploaded_by
                        FROM sys_filing_share s
                        JOIN sys_filing f ON s.filing_id = f.rec_id
                        WHERE s.rec_id = ?
                    ');
                    $stmt->execute([$shareId]);
                    $share = $stmt->fetch(\PDO::FETCH_ASSOC);

                    if (! $share) {
                        throw new \Exception('Share tidak ditemukan.');
                    }

                    $stmt = $pdoRun->prepare('SELECT * FROM sys_filing WHERE rec_id = ?');
                    $stmt->execute([$share['filing_id']]);
                    $file = $stmt->fetch(\PDO::FETCH_ASSOC);

                    if (! $permissionService->canManage($file, $userId) && ! $permissionService->canShare($file, $userId)) {
                        throw new \Exception('Anda tidak berhak me-revoke share ini.');
                    }

                    if ($shareModel->revokeShare($shareId, $userId)) {
                        $shareModel->logAudit($share['filing_id'], $userId, 'revoke_share', "Share ID {$shareId} revoked.");

                        return response()->json(['success' => true, 'message' => 'Share berhasil di-revoke.']);
                    }

                    throw new \Exception('Gagal revoke share.');
                case 'validate':
                    $rawCode = (string) $request->input('share_code', '');
                    if (! $shareService->validateShareCodeFormat($rawCode)) {
                        throw new \Exception('Format share code tidak valid.');
                    }

                    $hash = $shareService->hashShareCode($rawCode);
                    $share = $shareModel->findActiveShareByCodeHash($hash);

                    if (! $share) {
                        throw new \Exception('Share Code tidak ditemukan, sudah dicabut, atau tidak valid.');
                    }

                    $filingId = $share['filing_id'];

                    if ($share['file_status'] !== 'active' || ! empty($share['file_deleted_at'])) {
                        $shareModel->logAudit($filingId, $userId, 'share_access_denied', 'File inactive for share access');
                        throw new \Exception('File sudah tidak tersedia.');
                    }

                    if (! empty($share['expired_at']) && strtotime((string) $share['expired_at']) < time()) {
                        $shareModel->logAudit($filingId, $userId, 'share_access_denied', 'Share code expired');
                        throw new \Exception('Share Code sudah kadaluarsa.');
                    }

                    if (! empty($share['max_access']) && $share['access_count'] >= $share['max_access']) {
                        $shareModel->logAudit($filingId, $userId, 'share_access_denied', 'Share code max access reached');
                        throw new \Exception('Batas maksimal penggunaan Share Code telah tercapai.');
                    }

                    if ($share['requires_password']) {
                        $inputPassword = (string) $request->input('share_password', '');
                        if ($inputPassword === '' || ! password_verify($inputPassword, (string) $share['password_hash'])) {
                            $shareModel->logAudit($filingId, $userId, 'share_access_denied', 'Invalid password');
                            throw new \Exception('Password yang dimasukkan salah atau kosong.');
                        }
                    }

                    $shareModel->incrementAccessCount($share['rec_id']);
                    $shareModel->logAudit($filingId, $userId, 'share_access', 'Share code accessed');

                    $stmt = $pdoRun->prepare('SELECT display_name, zip_size, created_at FROM sys_filing WHERE rec_id = ?');
                    $stmt->execute([$filingId]);
                    $fileInfo = $stmt->fetch(\PDO::FETCH_ASSOC);

                    session()->put('share_access_'.$hash, true);

                    return response()->json([
                        'success' => true,
                        'file_info' => $fileInfo,
                        'allow_download' => (bool) $share['allow_download'],
                        'share_hash' => $hash,
                        'filing_id' => $filingId,
                    ]);

                default:
                    throw new \Exception('Action tidak dikenali.');
            }
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Share link publik dengan tabel baru file_share_link.
     */
    private function shareNew(Request $request, string $action, \PDO $pdoRun, FilingPermissionService $permissionService, ShareCodeService $shareService, int $userId)
    {
        $drive = new FileSystemDrive($pdoRun);

        $audit = function (int $fid, int $uid, string $act, string $notes) use ($pdoRun): void {
            $stmt = $pdoRun->prepare('INSERT INTO sys_filing_audit (filing_id, user_id, action, ip_address, user_agent, notes) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $fid, $uid, $act,
                (string) request()->ip(),
                substr((string) request()->userAgent(), 0, 500),
                $notes,
            ]);
        };

        try {
            switch ($action) {
                case 'create':
                    $filesysId = (int) $request->input('filing_id', 0);
                    $file = $drive->getFileById($filesysId);
                    if (! $file || ($file['status'] ?? 'active') !== 'active' || ! empty($file['deleted_at'])) {
                        throw new \Exception('File tidak ditemukan atau tidak aktif.');
                    }

                    $legacy = $drive->mapToLegacyShape($file);
                    if (! $permissionService->canShare($legacy, $userId)) {
                        $audit($filesysId, $userId, 'share_create_denied', 'Permission denied');
                        throw new \Exception('Anda tidak memiliki izin untuk membagikan file ini.');
                    }

                    if (($file['security_level'] ?? 'normal') === 'confidential') {
                        throw new \Exception('File confidential tidak dapat dibagikan via Share Code.');
                    }

                    $expiredAt = $request->input('expired_at') ?: null;
                    if (($file['security_level'] ?? 'normal') === 'restricted' && empty($expiredAt)) {
                        throw new \Exception('File restricted wajib memiliki Expired Date saat dibagikan.');
                    }

                    $rawCode = $shareService->generateShareCode();
                    $hash = $shareService->hashShareCode($rawCode);
                    $preview = $shareService->maskShareCode($rawCode);

                    $reqPassword = $request->input('requires_password') === '1';
                    $passwordHash = null;
                    if ($reqPassword && ! empty($request->input('share_password'))) {
                        $passwordHash = password_hash((string) $request->input('share_password'), PASSWORD_DEFAULT);
                    }

                    $stmt = $pdoRun->prepare('
                        INSERT INTO file_share_link (
                            filesys_id, share_code_preview, share_code_hash, created_by,
                            password_hash, max_access, expired_at, allow_download
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    $stmt->execute([
                        $filesysId,
                        $preview,
                        $hash,
                        $userId,
                        $passwordHash,
                        $request->input('max_access') ? (int) $request->input('max_access') : 0,
                        $expiredAt ? date('Y-m-d H:i:s', strtotime((string) $expiredAt)) : null,
                        $request->has('allow_download') ? (int) $request->input('allow_download') : 1,
                    ]);

                    $audit($filesysId, $userId, 'create_share', "Created share: {$preview}");

                    return response()->json([
                        'success' => true,
                        'message' => 'Share Code berhasil dibuat.',
                        'raw_code' => $rawCode,
                    ]);

                case 'list':
                    $filesysId = (int) $request->input('filing_id', $request->query('filing_id', 0));
                    $stmt = $pdoRun->prepare('
                        SELECT s.*, u.account_nm AS creator_name
                        FROM file_share_link s
                        LEFT JOIN sysitc_users u ON s.created_by = u.rec_id
                        WHERE s.filesys_id = ?
                        ORDER BY s.created_at DESC
                    ');
                    $stmt->execute([$filesysId]);

                    return response()->json(['success' => true, 'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);

                case 'revoke':
                    $shareId = (int) $request->input('share_id', 0);
                    $stmt = $pdoRun->prepare('
                        SELECT s.filesys_id FROM file_share_link s WHERE s.rec_id = ?
                    ');
                    $stmt->execute([$shareId]);
                    $share = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if (! $share) {
                        throw new \Exception('Share tidak ditemukan.');
                    }

                    $file = $drive->getFileById((int) $share['filesys_id']);
                    $legacy = $file ? $drive->mapToLegacyShape($file) : null;
                    if (! $legacy || (! $permissionService->canManage($legacy, $userId) && ! $permissionService->canShare($legacy, $userId))) {
                        throw new \Exception('Anda tidak berhak me-revoke share ini.');
                    }

                    $upd = $pdoRun->prepare('UPDATE file_share_link SET is_active = 0, revoked_at = NOW() WHERE rec_id = ? AND is_active = 1');
                    $upd->execute([$shareId]);
                    if ($upd->rowCount() > 0) {
                        $audit((int) $share['filesys_id'], $userId, 'revoke_share', "Share ID {$shareId} revoked.");

                        return response()->json(['success' => true, 'message' => 'Share berhasil di-revoke.']);
                    }

                    throw new \Exception('Gagal revoke share.');
                case 'internal_get':
                    $filesysId = (int) ($request->input('filing_id', $request->query('filing_id', 0)));
                    $file = $drive->getFileById($filesysId);
                    if (! $file) {
                        throw new \Exception('File tidak ditemukan.');
                    }

                    $legacy = $drive->mapToLegacyShape($file);
                    if (! $permissionService->canShare($legacy, $userId)) {
                        throw new \Exception('Anda tidak memiliki izin untuk membagikan file ini.');
                    }

                    $rules = [];
                    foreach ($drive->getSharesByFileId($filesysId) as $s) {
                        $cat = (int) $s['share_cat'];
                        if ($cat === 0) {
                            continue; // owner rule
                        }
                        $rules[] = [
                            'share_cat' => $cat,
                            'othercode' => $s['othercode'],
                            'label' => $this->shareLabel($pdoRun, $cat, (string) $s['othercode']),
                        ];
                    }

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'rules' => $rules,
                            'access_options' => $this->shareAccessOptions($pdoRun),
                        ],
                    ]);

                case 'internal_save':
                    $filesysId = (int) ($request->input('filing_id', $request->query('filing_id', 0)));
                    $file = $drive->getFileById($filesysId);
                    if (! $file) {
                        throw new \Exception('File tidak ditemukan.');
                    }
                    if (($file['status'] ?? 'active') !== 'active' || ! empty($file['deleted_at'])) {
                        throw new \Exception('File tidak aktif.');
                    }

                    $legacy = $drive->mapToLegacyShape($file);
                    if (! $permissionService->canManage($legacy, $userId)) {
                        $audit($filesysId, $userId, 'share_internal_denied', 'Permission denied');
                        throw new \Exception('Anda tidak memiliki izin mengatur pembagian file ini.');
                    }

                    $rawRulesInput = $request->input('rules');
                    $rawRules = is_string($rawRulesInput) ? json_decode($rawRulesInput, true) : [];
                    if (! is_array($rawRules)) {
                        $rawRules = [];
                    }

                    $shares = [];
                    $seen = [];
                    foreach ($rawRules as $rule) {
                        if (! is_array($rule)) {
                            continue;
                        }
                        $type = (string) ($rule['type'] ?? $rule['access_type'] ?? '');
                        $catRaw = $rule['share_cat'] ?? null;
                        $cat = $catRaw !== null ? (int) $catRaw : $this->accessTypeToShareCat($type);
                        $value = (string) ($rule['value'] ?? $rule['access_value'] ?? $rule['othercode'] ?? '');
                        if ($cat < 1 || $cat > 4 || $value === '') {
                            continue;
                        }
                        if (! $this->validateShareTarget($cat, $value)) {
                            throw new \Exception('Target pembagian tidak valid.');
                        }
                        $key = $cat.'|'.$value;
                        if (isset($seen[$key])) {
                            continue;
                        }
                        $seen[$key] = true;
                        $shares[] = ['share_cat' => $cat, 'othercode' => $value];
                    }

                    // Owner rule selalu dipertahankan sebagai share_cat = 0.
                    $allShares = array_merge([['share_cat' => 0, 'othercode' => (string) $userId]], $shares);

                    $hasAll = false;
                    foreach ($shares as $s) {
                        if ((int) $s['share_cat'] === 4) {
                            $hasAll = true;
                            break;
                        }
                    }
                    $accessMode = $hasAll ? 'public_internal' : (empty($shares) ? 'private' : 'custom');

                    $pdoRun->beginTransaction();
                    try {
                        $drive->replaceShares($filesysId, $allShares, $userId);
                        $drive->updateMetadata($filesysId, ['access_mode' => $accessMode]);
                        $pdoRun->commit();
                    } catch (\Throwable $e) {
                        $pdoRun->rollBack();
                        throw $e;
                    }

                    $audit($filesysId, $userId, 'share_internal', 'Internal share updated ('.count($shares).' target(s)).');

                    return response()->json(['success' => true, 'message' => 'Pembagian berhasil diperbarui.']);

                case 'validate':
                    $rawCode = (string) $request->input('share_code', '');
                    if (! $shareService->validateShareCodeFormat($rawCode)) {
                        throw new \Exception('Format share code tidak valid.');
                    }

                    $hash = $shareService->hashShareCode($rawCode);
                    $stmt = $pdoRun->prepare('
                        SELECT s.*, f.status AS file_status, f.deleted_at AS file_deleted_at, f.file_name, f.file_zip_size, f.file_type
                        FROM file_share_link s
                        INNER JOIN file_system f ON s.filesys_id = f.rec_id
                        WHERE s.share_code_hash = ? AND s.is_active = 1 AND s.revoked_at IS NULL
                        LIMIT 1
                    ');
                    $stmt->execute([$hash]);
                    $share = $stmt->fetch(\PDO::FETCH_ASSOC);

                    if (! $share) {
                        throw new \Exception('Share Code tidak ditemukan, sudah dicabut, atau tidak valid.');
                    }

                    $filesysId = (int) $share['filesys_id'];

                    if ($share['file_status'] !== 'active' || ! empty($share['file_deleted_at'])) {
                        $audit($filesysId, $userId, 'share_access_denied', 'File inactive for share access');
                        throw new \Exception('File sudah tidak tersedia.');
                    }

                    if (! empty($share['expired_at']) && strtotime((string) $share['expired_at']) < time()) {
                        $audit($filesysId, $userId, 'share_access_denied', 'Share code expired');
                        throw new \Exception('Share Code sudah kadaluarsa.');
                    }

                    if (! empty($share['max_access']) && $share['access_count'] >= $share['max_access']) {
                        $audit($filesysId, $userId, 'share_access_denied', 'Share code max access reached');
                        throw new \Exception('Batas maksimal penggunaan Share Code telah tercapai.');
                    }

                    if (! empty($share['password_hash'])) {
                        $inputPassword = (string) $request->input('share_password', '');
                        if ($inputPassword === '' || ! password_verify($inputPassword, (string) $share['password_hash'])) {
                            $audit($filesysId, $userId, 'share_access_denied', 'Invalid password');
                            throw new \Exception('Password yang dimasukkan salah atau kosong.');
                        }
                    }

                    $inc = $pdoRun->prepare('UPDATE file_share_link SET access_count = access_count + 1 WHERE rec_id = ?');
                    $inc->execute([$share['rec_id']]);
                    $audit($filesysId, $userId, 'share_access', 'Share code accessed');

                    session()->put('share_access_'.$hash, true);

                    return response()->json([
                        'success' => true,
                        'file_info' => [
                            'display_name' => $share['file_name'],
                            'zip_size' => $share['file_zip_size'],
                            'created_at' => null,
                        ],
                        'allow_download' => (bool) $share['allow_download'],
                        'share_hash' => $hash,
                        'filing_id' => $filesysId,
                    ]);

                default:
                    throw new \Exception('Action tidak dikenali.');
            }
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function permission(Request $request)
    {

        $userId = (int) auth_user_id();

        if ($userId <= 0) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $action = (string) ($request->input('action', $request->query('action', '')));
        $filingId = (int) ($request->input('filing_id', $request->query('filing_id', 0)));

        if ($filingId <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid File ID']);
        }

        $pdoRun = DB::connection('run')->getPdo();
        $permissionService = new FilingPermissionService($pdoRun);
        $filingModel = new FilingSystem($pdoRun);
        $accessModel = new FilingAccess($pdoRun);

        if (FileSystemDrive::isEnabled()) {
            return $this->permissionNew($request, $action, $filingId, $pdoRun, $permissionService, $userId);
        }

        try {
            $file = $filingModel->getFileById($filingId);
            if (! $file) {
                throw new \Exception('File tidak ditemukan.');
            }

            switch ($action) {
                case 'get':
                    if (! $permissionService->canUpdatePermission($file, $userId)) {
                        throw new \Exception('Permission denied.');
                    }

                    $rules = $accessModel->getRulesByFilingId($filingId);

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'access_mode' => $file['access_mode'],
                            'security_level' => $file['security_level'],
                            'rules' => $rules,
                            'access_options' => $accessModel->getAccessOptions(),
                        ],
                        'is_admin' => $permissionService->isAdmin($userId),
                    ]);

                case 'save_all':
                    if (in_array($file['status'], ['deleted', 'trashed', 'blocked'], true)) {
                        throw new \Exception("File dengan status {$file['status']} tidak dapat diubah permission-nya.");
                    }

                    if (! $permissionService->canUpdatePermission($file, $userId)) {
                        $accessModel->logAudit($filingId, $userId, 'permission_update_denied', 'Permission denied');
                        throw new \Exception('Anda tidak memiliki izin mengatur permission file ini.');
                    }

                    $accessMode = (string) $request->input('access_mode', 'private');
                    $validModes = ['private', 'custom'];
                    if (! in_array($accessMode, $validModes, true)) {
                        throw new \Exception('Access Mode tidak valid.');
                    }

                    $rawRulesInput = $request->input('rules');
                    $rawRules = is_string($rawRulesInput) ? json_decode($rawRulesInput, true) : [];
                    if (! is_array($rawRules)) {
                        $rawRules = [];
                    }

                    $normalizedRules = [];
                    $seenPairs = [];
                    $confidentialShareRemoved = false;

                    foreach ($rawRules as $r) {
                        $norm = $permissionService->normalizePermissionRule($r, $file, $userId);

                        if (empty($norm['access_type']) || (empty($norm['access_value']) && $norm['access_value'] !== '0')) {
                            continue;
                        }

                        if (! $accessModel->validateAccessValue((string) $norm['access_type'], (string) $norm['access_value'])) {
                            throw new \Exception("Nilai akses {$norm['access_type']} tidak valid atau tidak terdaftar.");
                        }

                        $pairKey = $norm['access_type'].'_'.$norm['access_value'];
                        if (isset($seenPairs[$pairKey])) {
                            continue;
                        }
                        $seenPairs[$pairKey] = true;

                        if (! empty($r['can_share']) && $norm['can_share'] === 0 && $file['security_level'] === 'confidential') {
                            $confidentialShareRemoved = true;
                        }

                        $normalizedRules[] = $norm;
                    }

                    $pdoRun->beginTransaction();

                    try {
                        $stmtUpdate = $pdoRun->prepare('UPDATE sys_filing SET access_mode = ?, updated_at = NOW() WHERE rec_id = ?');
                        $stmtUpdate->execute([$accessMode, $filingId]);

                        $accessModel->replaceRules($filingId, $normalizedRules, $userId);

                        $notes = "Mode changed to {$accessMode}. Updated ".count($normalizedRules).' rules.';
                        $accessModel->logAudit($filingId, $userId, 'permission_update', $notes);

                        if ($confidentialShareRemoved) {
                            $accessModel->logAudit($filingId, $userId, 'permission_confidential_share_removed', 'can_share forced to 0 due to confidential level');
                        }

                        $pdoRun->commit();
                    } catch (\Throwable $e) {
                        if ($pdoRun->inTransaction()) {
                            $pdoRun->rollBack();
                        }
                        throw $e;
                    }

                    return response()->json(['success' => true, 'message' => 'Hak akses berhasil diperbarui.']);

                default:
                    throw new \Exception('Action tidak dikenali.');
            }
        } catch (\Throwable $e) {
            if ($pdoRun->inTransaction()) {
                $pdoRun->rollBack();
            }

            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Permission internal dengan tabel baru file_shareto.
     * share_cat: 0=Private, 1=Personal(user), 2=Department, 3=Company, 4=All User.
     */
    private function permissionNew(Request $request, string $action, int $filingId, \PDO $pdoRun, FilingPermissionService $permissionService, int $userId)
    {
        $drive = new FileSystemDrive($pdoRun);

        $audit = function (int $fid, int $uid, string $act, string $notes) use ($pdoRun): void {
            $stmt = $pdoRun->prepare('INSERT INTO sys_filing_audit (filing_id, user_id, action, ip_address, user_agent, notes) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $fid, $uid, $act,
                (string) request()->ip(),
                substr((string) request()->userAgent(), 0, 500),
                $notes,
            ]);
        };

        try {
            $file = $drive->getFileById($filingId);
            if (! $file) {
                throw new \Exception('File tidak ditemukan.');
            }

            $legacy = $drive->mapToLegacyShape($file);

            switch ($action) {
                case 'get':
                    if (! $permissionService->canUpdatePermission($legacy, $userId)) {
                        throw new \Exception('Permission denied.');
                    }

                    $shares = $drive->getSharesByFileId($filingId);
                    $rules = array_map(fn (array $s) => [
                        'access_type' => $this->shareCatToAccessType((int) $s['share_cat']),
                        'access_value' => $s['othercode'],
                        'share_cat' => (int) $s['share_cat'],
                        'can_view' => 1,
                        'can_download' => 1,
                        'can_share' => 0,
                        'can_manage' => 0,
                    ], $shares);

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'access_mode' => $file['access_mode'] ?? 'private',
                            'security_level' => $file['security_level'] ?? 'normal',
                            'rules' => $rules,
                            'access_options' => $this->shareAccessOptions($pdoRun),
                        ],
                        'is_admin' => $permissionService->isAdmin($userId),
                    ]);

                case 'save_all':
                    if (in_array($file['status'] ?? 'active', ['deleted', 'trashed', 'blocked'], true)) {
                        throw new \Exception("File dengan status {$file['status']} tidak dapat diubah permission-nya.");
                    }

                    if (! $permissionService->canUpdatePermission($legacy, $userId)) {
                        $audit($filingId, $userId, 'permission_update_denied', 'Permission denied');
                        throw new \Exception('Anda tidak memiliki izin mengatur permission file ini.');
                    }

                    $accessMode = (string) $request->input('access_mode', 'private');
                    $validModes = ['private', 'custom'];
                    if (! in_array($accessMode, $validModes, true)) {
                        throw new \Exception('Access Mode tidak valid.');
                    }

                    $rawRulesInput = $request->input('rules');
                    $rawRules = is_string($rawRulesInput) ? json_decode($rawRulesInput, true) : [];
                    if (! is_array($rawRules)) {
                        $rawRules = [];
                    }

                    $shares = [];
                    $seen = [];
                    foreach ($rawRules as $rule) {
                        if (! is_array($rule)) {
                            continue;
                        }
                        $cat = (int) ($rule['share_cat'] ?? $this->accessTypeToShareCat((string) ($rule['access_type'] ?? '')));
                        $value = (string) ($rule['access_value'] ?? '');
                        if ($cat < 0 || $cat > 4 || $value === '') {
                            continue;
                        }
                        if ($cat === 0) {
                            continue; // owner rule dikelola otomatis
                        }
                        $key = $cat.'|'.$value;
                        if (isset($seen[$key])) {
                            continue;
                        }
                        $seen[$key] = true;
                        $shares[] = ['share_cat' => $cat, 'othercode' => $value];
                    }

                    $pdoRun->beginTransaction();
                    try {
                        $drive->replaceShares($filingId, $shares, $userId);
                        $drive->updateMetadata($filingId, ['access_mode' => $accessMode]);
                        $pdoRun->commit();
                    } catch (\Throwable $e) {
                        $pdoRun->rollBack();
                        throw $e;
                    }

                    $audit($filingId, $userId, 'permission_update', 'Permission updated (shareto).');

                    return response()->json(['success' => true, 'message' => 'Hak akses berhasil diperbarui.']);

                default:
                    throw new \Exception('Action tidak dikenali.');
            }
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function shareCatToAccessType(int $cat): string
    {
        return match ($cat) {
            1 => 'user',
            2 => 'department',
            3 => 'company',
            4 => 'all',
            default => 'user',
        };
    }

    private function accessTypeToShareCat(string $type): int
    {
        return match ($type) {
            'user' => 1,
            'department' => 2,
            'company' => 3,
            'all' => 4,
            default => 1,
        };
    }

    /**
     * Label tampilan untuk target internal share (file_shareto).
     */
    private function shareLabel(\PDO $pdoRun, int $cat, string $value): string
    {
        if ($cat === 4) {
            return 'Semua Karyawan';
        }

        try {
            if ($cat === 1) {
                $stmt = $pdoRun->prepare("
                    SELECT COALESCE(NULLIF(u.account_nm, ''), l.account_id, CONCAT('User #', u.rec_id))
                    FROM sysitc_users u
                    LEFT JOIN sysitc_login l ON l.rec_id = u.login_rec_id
                    WHERE u.rec_id = ?
                ");
                $stmt->execute([$value]);
                $label = $stmt->fetchColumn();

                return $label ? (string) $label : ('User #'.$value);
            }

            if ($cat === 2) {
                $stmt = $pdoRun->prepare("
                    SELECT CONCAT(code, ' - ', descr)
                    FROM sys_msttable
                    WHERE tbl_code = '55' AND code = ?
                    LIMIT 1
                ");
                $stmt->execute([$value]);
                $label = $stmt->fetchColumn();

                return $label ? (string) $label : $value;
            }

            if ($cat === 3) {
                return 'Company '.$value;
            }
        } catch (\Throwable $e) {
        }

        return $value;
    }

    /**
     * Validasi target internal share terhadap master data.
     */
    private function validateShareTarget(int $cat, string $value): bool
    {
        if ($cat === 4) {
            return true;
        }

        try {
            if ($cat === 1) {
                return DB::connection('run')->table('sysitc_users')->where('rec_id', $value)->exists();
            }

            if ($cat === 2) {
                return DB::connection('run')->table('sys_msttable')
                    ->where('tbl_code', '55')
                    ->where('statrec', 1)
                    ->where('code', $value)
                    ->exists();
            }

            if ($cat === 3) {
                return DB::connection('run')->table('sysitc_users')
                    ->where('cmpcd', $value)
                    ->whereNotNull('cmpcd')
                    ->where('cmpcd', '<>', '')
                    ->where('cmpcd', '<>', '0')
                    ->exists();
            }

            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function shareAccessOptions(\PDO $pdoRun): array
    {
        try {
            $users = $pdoRun->query("
                SELECT u.rec_id AS value,
                       COALESCE(NULLIF(u.account_nm, ''), l.account_id, CONCAT('User #', u.rec_id)) AS label
                FROM sysitc_users u
                LEFT JOIN sysitc_login l ON l.rec_id = u.login_rec_id
                WHERE u.status = 1
                ORDER BY label ASC
            ")->fetchAll(\PDO::FETCH_ASSOC);

            $companies = $pdoRun->query("
                SELECT DISTINCT cmpcd AS value, cmpcd AS label
                FROM sysitc_users
                WHERE cmpcd IS NOT NULL AND cmpcd <> '' AND cmpcd <> '0'
                ORDER BY cmpcd ASC
            ")->fetchAll(\PDO::FETCH_ASSOC);

            $departments = $pdoRun->query("
                SELECT code AS value,
                       CONCAT(code, ' - ', descr) AS label
                FROM sys_msttable
                WHERE tbl_code = '55' AND statrec = 1
                ORDER BY code ASC
            ")->fetchAll(\PDO::FETCH_ASSOC);

            return [
                'user' => $users,
                'company' => $companies,
                'department' => $departments,
                'custom_group' => [],
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function inspect(Request $request)
    {

        $userId = (int) auth_user_id();

        if ($userId <= 0) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $filingId = (int) ($request->query('id', $request->input('id', 0)));

        if ($filingId <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid File ID']);
        }

        $pdoRun = DB::connection('run')->getPdo();
        $permService = new FilingPermissionService($pdoRun);
        $storageService = new FilingStorageService($this->ftpConfig());
        $inspectionService = new ZipInspectionService;
        $filingModel = new FilingSystem($pdoRun);

        $tmpPath = null;

        try {
            $file = $filingModel->getFileById($filingId);
            if (! $file) {
                throw new \Exception('File tidak ditemukan.');
            }

            if (! in_array($file['status'], ['active', 'archived'], true)) {
                if ($file['status'] === 'trashed' && ! $permService->canManage($file, $userId)) {
                    throw new \Exception('File berada di Sampah. Akses ditolak.');
                }
                if (in_array($file['status'], ['deleted', 'blocked'], true)) {
                    throw new \Exception('Status file tidak valid untuk inspeksi.');
                }
            }

            if (! $permService->canView($file, $userId)) {
                throw new \Exception('Anda tidak memiliki izin untuk melihat isi file ini.');
            }

            $tmpPath = $storageService->createTemporaryCopy($file['storage_path']);
            if (! $tmpPath || ! file_exists($tmpPath)) {
                throw new \Exception('Gagal menarik file dari storage server.');
            }

            $result = $inspectionService->inspect($tmpPath);

            @unlink($tmpPath);
            $tmpPath = null;

            if (! $result['success']) {
                $filingModel->logAudit($filingId, $userId, 'zip_inspect_failed', 'ZIP inspection failed: '.implode(', ', $result['warnings']));
                throw new \Exception($result['message']);
            }

            $metadataUpdated = false;
            if (
                (int) $file['file_count'] !== (int) $result['file_count'] ||
                (int) $file['total_uncompressed_size'] !== (int) $result['total_uncompressed_size'] ||
                ($file['detected_file_type'] !== $result['detected_file_type'])
            ) {
                $updateStmt = $pdoRun->prepare('
                    UPDATE sys_filing
                    SET file_count = ?, total_uncompressed_size = ?, detected_file_type = ?, updated_at = NOW()
                    WHERE rec_id = ?
                ');
                $updateStmt->execute([
                    $result['file_count'],
                    $result['total_uncompressed_size'],
                    $result['detected_file_type'],
                    $filingId,
                ]);
                $metadataUpdated = true;
            }

            $auditNote = 'ZIP inspected. Files: '.$result['file_count'].'.';
            if (! empty($result['warnings'])) {
                $auditNote .= ' Warnings: '.implode(', ', $result['warnings']);
            }
            if ($metadataUpdated) {
                $auditNote .= ' (Metadata synced)';
            }
            $filingModel->logAudit($filingId, $userId, 'zip_inspect', $auditNote);

            $result['file_info'] = [
                'display_name' => $file['display_name'],
                'file_code' => $file['file_code'],
                'zip_size' => $file['zip_size'],
            ];

            return response()->json($result);
        } catch (\Throwable $e) {
            if ($tmpPath && file_exists($tmpPath)) {
                @unlink($tmpPath);
            }

            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Preview dokumen inline (PDF & gambar). Hanya untuk file non-ZIP.
     */
    public function preview(Request $request)
    {

        $filingId = (int) $request->query('id', 0);
        if ($filingId <= 0) {
            return response('ID File tidak valid.', 404);
        }

        $pdoRun = DB::connection('run')->getPdo();
        $drive = new FileSystemDrive($pdoRun);
        $permissionService = new FilingPermissionService($pdoRun);

        $file = $drive->getFileById($filingId);
        if (! $file || ($file['status'] ?? 'active') !== 'active' || ! empty($file['deleted_at'])) {
            return response('File tidak ditemukan atau status tidak aktif.', 404);
        }

        $userId = (int) auth_user_id();
        if ($userId <= 0) {
            return response('Unauthorized.', 401);
        }

        $legacy = $drive->mapToLegacyShape($file);
        if (! $permissionService->canDownload($legacy, $userId)) {
            return response('Anda tidak memiliki izin untuk melihat file ini.', 403);
        }

        $fileType = strtoupper((string) ($file['file_type'] ?? ''));
        $previewable = in_array($fileType, ['PDF', 'JPG', 'JPEG', 'PNG', 'GIF', 'WEBP'], true);
        if (! $previewable) {
            return response('Tipe file tidak dapat di-preview.', 415);
        }

        $storagePath = $drive->buildStoragePath($file);
        if ($storagePath === '') {
            return response('Lokasi penyimpanan file tidak ditemukan.', 404);
        }

        $storageService = new FilingStorageService($this->ftpConfig());
        $remoteSize = $storageService->getFileSize($storagePath);
        if ($remoteSize <= 0) {
            return response('File fisik tidak tersedia.', 404);
        }

        $tmpFile = sys_get_temp_dir().'/prev_'.$userId.'_'.time().'_'.bin2hex(random_bytes(4)).'.tmp';
        $downloaded = $storageService->moveFromFtpToLocal($storagePath, $tmpFile);
        if (! $downloaded || ! file_exists($tmpFile)) {
            return response('Gagal mengambil file dari storage.', 500);
        }

        try {
            $stmtAudit = $pdoRun->prepare("
            INSERT INTO sys_filing_audit (
                filing_id, user_id, action, ip_address, user_agent, notes
            ) VALUES (?, ?, 'preview', ?, ?, ?)
        ");
            $stmtAudit->execute([
                $filingId,
                $userId,
                (string) $request->ip(),
                substr((string) $request->userAgent(), 0, 500),
                'File previewed: '.$file['file_name'],
            ]);
        } catch (\Throwable $e) {
            // Preview tetap berjalan meski audit gagal dicatat.
        }

        $size = filesize($tmpFile);
        $mime = match ($fileType) {
            'PDF' => 'application/pdf',
            'JPG', 'JPEG' => 'image/jpeg',
            'PNG' => 'image/png',
            'GIF' => 'image/gif',
            'WEBP' => 'image/webp',
            default => 'application/octet-stream',
        };

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: '.$mime);
        header('Content-Disposition: inline; filename="'.basename($file['file_name'] ?? 'preview').'"');
        header('Content-Length: '.$size);
        header('Cache-Control: private, max-age=300');

        readfile($tmpFile);
        @unlink($tmpFile);
        exit;
    }

    /**
     * Preserve legacy FilingStorageService constructor config without including config.php.
     */
    private function writeFilingDebug(string $message): void
    {
        try {
            $dir = base_path('storage/logs');
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (! is_dir($dir) || ! is_writable($dir)) {
                return;
            }
            @file_put_contents($dir.'/filing_upload_debug.log', date('c').' '.$message.PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // abaikan
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

    private function modalHtml(): string
    {
        $partials = [
            'filing-system.partials.upload-modal',
            'filing-system.partials.share-modal',
            'filing-system.partials.edit-metadata-modal',
            'filing-system.partials.permission-modal',
            'filing-system.partials.info-drawer',
            'filing-system.partials.zip-inspection-modal',
        ];

        $html = '';
        foreach ($partials as $partial) {
            $html .= "\n".view($partial)->render();
        }

        $base = url('/modules/cbt_ops/filing_system');

        return str_replace([
            "fetch('upload.php?action=access_options')",
            "xhr.open('POST', 'upload.php', true)",
            "fetch('info.php?id=' + id)",
            'fetch(`permission.php?action=get&filing_id=${filingId}`)',
            "fetch('permission.php', { method: 'POST', body: fd })",
            "fetch('action.php', { method: 'POST', body: fd })",
            "fetch('inspect.php?id=' + id)",
            "fetch('share.php', { method: 'POST', body: fd })",
            "fetch('share.php?action=list&filing_id=' + filingId)",
            "fetch('share.php?action=internal_get&filing_id=' + filingId)",
        ], [
            "fetch('{$base}/upload.php?action=access_options')",
            "xhr.open('POST', '{$base}/upload.php', true)",
            "fetch('{$base}/info.php?id=' + id)",
            'fetch(`'.$base.'/permission.php?action=get&filing_id=${filingId}`)',
            "fetch('{$base}/permission.php', { method: 'POST', body: fd })",
            "fetch('{$base}/action.php', { method: 'POST', body: fd })",
            "fetch('{$base}/inspect.php?id=' + id)",
            "fetch('{$base}/share.php', { method: 'POST', body: fd })",
            "fetch('{$base}/share.php?action=list&filing_id=' + filingId)",
            "fetch('{$base}/share.php?action=internal_get&filing_id=' + filingId)",
        ], $html);
    }
}
