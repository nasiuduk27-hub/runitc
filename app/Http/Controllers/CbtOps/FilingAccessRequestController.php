<?php

namespace App\Http\Controllers\CbtOps;

use App\Http\Controllers\Controller;
use App\Repositories\FilingSystem\FilingAccessRequest;
use App\Repositories\FilingSystem\FileGroup;
use App\Repositories\FilingSystem\FileSystemDrive;
use App\Services\FilingSystem\FilingLinkService;
use App\Services\FilingSystem\FilingPermissionService;
use App\Support\FilingSystem\FilingFolders;
use App\Support\Legacy\AuditLog;
use App\Support\Legacy\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

/**
 * Meja permintaan akses Filing System (Fase 3).
 *
 * Intake (3A):
 * - Link token mode 1 (hanya hak, bukan mode token): pemegang link tanpa hak
 *   minta akses ke file; wajib login, form mengirim token (bukan id file).
 * - Halaman grup: bukan anggota grup aktif minta akses ke pengelola grup.
 *
 * Keputusan (3B): halaman daftar permintaan + Setujui/Tolak.
 * - File: pemutus pemilik file; hak diberikan dengan menambah aturan akses
 *   per-user (file_shareto share_cat=1) secara aditif murni.
 * - Grup: pemutus semua Pengelola (pemilik implisit Pengelola); peminta masuk
 *   sebagai anggota dengan peran bawaan Penonton (viewer) eksplisit.
 * Semua keputusan teraudit; peminta menerima notifikasi balasan lewat
 * sys_notifications (aksi tinggal di halaman ini, notifikasi hanya menaut).
 *
 * Permukaan file langsung (preview/thumbnail/unduhan) sengaja TIDAK diberi
 * tombol minta akses: menampilkan nama file + pemilik di sana membuka oracle
 * baru bagi penebak id.
 */
class FilingAccessRequestController extends Controller
{
    /**
     * Shim login: simpan tujuan kembali (link show) lalu arahkan ke login.
     * Hanya alur link ini yang mengisi url.intended; perilaku login lain tetap.
     */
    public function loginIntent(string $token): RedirectResponse
    {
        if (! FileSystemDrive::isEnabled()) {
            abort(404);
        }

        session(['url.intended' => route('filing-system.link.show', ['token' => $token])]);

        return redirect()->route('login');
    }

    public function storeFromLink(Request $request, string $token): RedirectResponse
    {
        if (! FileSystemDrive::isEnabled()) {
            abort(404);
        }

        $linkUrl = route('filing-system.link.show', ['token' => $token]);
        $userId = (int) auth_user_id();

        if ($userId <= 0) {
            return redirect()->route('filing-system.link.login', ['token' => $token]);
        }

        $pdo = DB::connection('run')->getPdo();
        $linkService = new FilingLinkService;
        $drive = new FileSystemDrive($pdo);

        $share = $this->resolveShare($linkService, $pdo, $token);
        if ($share === null || $linkService->modeOf($share) !== FilingLinkService::MODE_RIGHTS) {
            return redirect($linkUrl);
        }

        $file = $drive->getFileById((int) $share['filesys_id']);
        if (! $this->fileIsActive($file)) {
            return redirect($linkUrl);
        }

        $fileId = (int) $file['rec_id'];

        if ((int) $file['userid'] === $userId) {
            return redirect($linkUrl)->with('error_msg', 'Anda adalah pemilik file ini.');
        }

        if (($file['security_level'] ?? 'normal') === 'confidential') {
            return redirect($linkUrl)->with('error_msg', 'File ini tidak menerima permintaan akses. Hubungi pemilik file secara langsung.');
        }

        $permissionService = new FilingPermissionService($pdo);
        if ($permissionService->canView($drive->mapToLegacyShape($file), $userId)) {
            return redirect($linkUrl)->with('error_msg', 'Anda sudah memiliki akses ke file ini.');
        }

        $message = mb_substr(trim((string) $request->input('message', '')), 0, 500);

        $requests = new FilingAccessRequest($pdo);
        $requestId = $requests->create(FilingAccessRequest::TYPE_FILE, $fileId, $userId, $message === '' ? null : $message);
        if ($requestId === null) {
            return redirect($linkUrl)->with('error_msg', 'Permintaan Anda untuk file ini masih menunggu keputusan.');
        }

        $this->audit($pdo, $fileId, $userId, 'access_request_create', 'Permintaan akses via link mode 1');

        $this->notifyRequestCreated($pdo, [
            'rec_id' => $requestId,
            'requester_id' => $userId,
            'message' => $message === '' ? null : $message,
        ], (string) ($file['file_name'] ?? 'File'), [(int) $file['userid']]);

        return redirect($linkUrl)->with('success_msg', 'Permintaan akses terkirim ke pemilik file.');
    }

    public function storeForGroup(Request $request, int $group): RedirectResponse
    {
        if (! FileSystemDrive::isEnabled()) {
            abort(404, 'Fitur grup memerlukan tabel baru (FILING_USE_NEW_TABLES).');
        }

        $userId = (int) auth_user_id();
        if ($userId <= 0) {
            abort(401);
        }

        $pdo = DB::connection('run')->getPdo();
        $groups = new FileGroup($pdo);
        $groupRow = $groups->getGroup($group);

        if (! $groupRow) {
            abort(404, 'Grup tidak ditemukan.');
        }

        if (! empty($groupRow['archived_at']) || ($groupRow['status'] ?? '') !== 'active') {
            abort(403, 'Anda bukan anggota grup ini.');
        }

        $showUrl = route('filing-system.groups.show', $group);

        if ($groups->roleFor($group, $userId) !== null) {
            return redirect($showUrl)->with('error_msg', 'Anda sudah menjadi anggota grup ini.');
        }

        $message = mb_substr(trim((string) $request->input('message', '')), 0, 500);

        $requests = new FilingAccessRequest($pdo);
        $requestId = $requests->create(FilingAccessRequest::TYPE_GROUP, $group, $userId, $message === '' ? null : $message);
        if ($requestId === null) {
            return redirect($showUrl)->with('error_msg', 'Permintaan Anda untuk grup ini masih menunggu keputusan.');
        }

        AuditLog::logAudit($pdo, 'FILING_GROUP_ACCESS_REQUEST_CREATE', 'filing_group', $group, [
            'requester_id' => $userId,
            'request_id' => $requestId,
        ], $userId);

        $this->notifyRequestCreated($pdo, [
            'rec_id' => $requestId,
            'requester_id' => $userId,
            'message' => $message === '' ? null : $message,
        ], (string) ($groupRow['group_name'] ?? 'Grup'), $this->groupManagerRecipients($pdo, $group));

        return redirect($showUrl)->with('success_msg', 'Permintaan akses terkirim ke pengelola grup.');
    }

    /**
     * Daftar permintaan pending yang berhak diputuskan pengguna:
     * file miliknya + grup yang ia kelola sebagai Pengelola.
     */
    public function index(Request $request)
    {
        $userId = (int) auth_user_id();
        if ($userId <= 0) {
            abort(401);
        }

        $pdo = DB::connection('run')->getPdo();
        $groupRoles = (new FilingPermissionService($pdo))->resolveGroupMembership($userId);

        $managedGroupIds = [];
        foreach ($groupRoles as $groupId => $role) {
            if ($role === 'manager') {
                $managedGroupIds[] = (int) $groupId;
            }
        }

        $requests = new FilingAccessRequest($pdo);

        return view('filing-system.requests.index', [
            'requests' => $requests->pendingForDecider($userId, $managedGroupIds),
        ]);
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        return $this->decide($id, 'approved');
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        return $this->decide($id, 'rejected');
    }

    private function decide(int $id, string $decision): RedirectResponse
    {
        $userId = (int) auth_user_id();
        if ($userId <= 0) {
            abort(401);
        }

        $pdo = DB::connection('run')->getPdo();
        $requests = new FilingAccessRequest($pdo);
        $row = $requests->find($id);
        $back = redirect()->route('filing-system.requests.index');

        if (! $row) {
            return $back->with('error_msg', 'Permintaan tidak ditemukan.');
        }

        if (($row['status'] ?? '') !== 'pending') {
            return $back->with('success_msg', 'Permintaan sudah diputuskan sebelumnya.');
        }

        if ($row['target_type'] === FilingAccessRequest::TYPE_FILE) {
            return $this->decideFile($pdo, $requests, $row, $userId, $decision, $back);
        }

        if ($row['target_type'] === FilingAccessRequest::TYPE_GROUP) {
            return $this->decideGroup($pdo, $requests, $row, $userId, $decision, $back);
        }

        return $back->with('error_msg', 'Jenis target tidak dikenal.');
    }

    private function decideFile(PDO $pdo, FilingAccessRequest $requests, array $row, int $userId, string $decision, RedirectResponse $back): RedirectResponse
    {
        $drive = new FileSystemDrive($pdo);
        $file = $drive->getFileById((int) $row['target_id']);

        if (! $file || (int) $file['userid'] !== $userId) {
            abort(403, 'Hanya pemilik file yang dapat memutuskan permintaan ini.');
        }

        $fileName = (string) ($file['file_name'] ?? 'File');

        if ($decision === 'rejected') {
            return $this->finalizeReject($pdo, $requests, $row, $userId, $back, $fileName);
        }

        if (($file['status'] ?? 'active') !== 'active' || ! empty($file['deleted_at'])) {
            return $back->with('error_msg', 'File sudah tidak aktif; permintaan tidak dapat disetujui.');
        }

        if (($file['security_level'] ?? 'normal') === 'confidential') {
            return $back->with('error_msg', 'File confidential tidak dapat diberi akses lewat permintaan.');
        }

        $requesterId = (int) $row['requester_id'];
        $permissionService = new FilingPermissionService($pdo);
        $alreadyHasAccess = $permissionService->canView($drive->mapToLegacyShape($file), $requesterId);

        $pdo->beginTransaction();
        try {
            if (! $requests->decide((int) $row['rec_id'], 'approved', $userId)) {
                $pdo->rollBack();

                return $back->with('success_msg', 'Permintaan sudah diputuskan sebelumnya.');
            }

            if (! $alreadyHasAccess) {
                $this->grantFileAccess($drive, (int) $row['target_id'], $requesterId, $userId);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[FilingAccessRequestController] Setujui file gagal: '.$e->getMessage());

            return $back->with('error_msg', 'Gagal memberi akses. Coba lagi.');
        }

        $this->audit($pdo, (int) $row['target_id'], $userId, 'access_request_approve', 'Permintaan akses disetujui');
        $this->notifyDecision($pdo, $row, true, $userId, $fileName);

        return $back->with('success_msg', 'Akses diberikan kepada peminta.');
    }

    /**
     * Pemberian hak aditif murni: salinan aturan apa adanya + satu baris
     * share_cat=1 untuk peminta (baris grup share_cat=5 tidak tersentuh).
     * access_mode disinkronkan dari himpunan hasil, meniru pasangan
     * replaceShares + updateMetadata pada jalur modal/upload.
     */
    private function grantFileAccess(FileSystemDrive $drive, int $fileId, int $requesterId, int $deciderId): void
    {
        $shares = $drive->getSharesByFileId($fileId);

        foreach ($shares as $share) {
            if ((int) ($share['share_cat'] ?? 0) === 1 && (string) ($share['othercode'] ?? '') === (string) $requesterId) {
                return;
            }
        }

        $shares[] = ['share_cat' => 1, 'othercode' => (string) $requesterId];
        $drive->replaceShares($fileId, $shares, $deciderId);

        // Paritas save_all/modal: metadata mode selalu sepasang dengan himpunan
        // aturan (FilingDriveController:401, FilingSystemController:2515).
        $drive->updateMetadata($fileId, ['access_mode' => $this->deriveAccessMode($shares)]);
    }

    /**
     * Turunan access_mode dari himpunan aturan HASIL, meniru dua jalur resmi:
     * share_cat=4 (Semua Karyawan) => public_internal; ada aturan non-pemilik
     * => custom; selain itu private. Baris owner (0) dan grup (5) tidak
     * dihitung sebagai aturan berbagi, persis seperti save_all/upload.
     * Catatan: share_cat=3 (Company) tetap aturan scoped => custom — hanya
     * cat=4 yang membuka file untuk semua user (FilingPermissionService:454).
     *
     * @param  array<int, array<string, mixed>>  $shares
     */
    private function deriveAccessMode(array $shares): string
    {
        $hasAll = false;
        $hasRule = false;

        foreach ($shares as $share) {
            $cat = (int) ($share['share_cat'] ?? 0);
            if ($cat === 4) {
                $hasAll = true;
            }
            if (in_array($cat, [1, 2, 3, 4], true)) {
                $hasRule = true;
            }
        }

        if ($hasAll) {
            return 'public_internal';
        }

        return $hasRule ? 'custom' : 'private';
    }

    private function decideGroup(PDO $pdo, FilingAccessRequest $requests, array $row, int $userId, string $decision, RedirectResponse $back): RedirectResponse
    {
        $groupId = (int) $row['target_id'];
        $groups = new FileGroup($pdo);
        $groupRow = $groups->getGroup($groupId);

        if (! $groupRow) {
            return $back->with('error_msg', 'Grup sudah tidak ada; permintaan tidak dapat diputuskan.');
        }

        // Pemilik selalu berhak; anggota Pengelola berhak selama grup aktif
        // (keanggotaan pada grup terarsip tidak lagi dihitung dinamis).
        $isOwner = (int) ($groupRow['created_by'] ?? 0) === $userId;
        if (! $isOwner && $groups->roleFor($groupId, $userId) !== 'manager') {
            abort(403, 'Hanya Pengelola grup yang dapat memutuskan permintaan ini.');
        }

        $groupName = (string) ($groupRow['group_name'] ?? 'Grup');

        if ($decision === 'rejected') {
            return $this->finalizeReject($pdo, $requests, $row, $userId, $back, $groupName);
        }

        if (! empty($groupRow['archived_at']) || ($groupRow['status'] ?? '') !== 'active') {
            return $back->with('error_msg', 'Grup sudah tidak aktif; permintaan tidak dapat disetujui.');
        }

        $requesterId = (int) $row['requester_id'];

        $pdo->beginTransaction();
        try {
            if (! $requests->decide((int) $row['rec_id'], 'approved', $userId)) {
                $pdo->rollBack();

                return $back->with('success_msg', 'Permintaan sudah diputuskan sebelumnya.');
            }

            // Jangan sentuh peran anggota eksisting (jebakan ON DUPLICATE KEY
            // UPDATE role=VALUES(role)); tambah hanya bila belum anggota, dan
            // teruskan peran viewer secara EKSPLISIT.
            if ($groups->roleFor($groupId, $requesterId) === null) {
                $groups->addMember($groupId, 'user', (string) $requesterId, 'viewer', $userId);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[FilingAccessRequestController] Setujui grup gagal: '.$e->getMessage());

            return $back->with('error_msg', 'Gagal memberi akses. Coba lagi.');
        }

        AuditLog::logAudit($pdo, 'FILING_GROUP_ACCESS_REQUEST_APPROVE', 'filing_group', $groupId, [
            'requester_id' => $requesterId,
            'request_id' => (int) $row['rec_id'],
            'role' => 'viewer',
        ], $userId);

        $this->notifyDecision($pdo, $row, true, $userId, $groupName);

        return $back->with('success_msg', 'Peminta ditambahkan sebagai anggota dengan peran Penonton.');
    }

    private function finalizeReject(PDO $pdo, FilingAccessRequest $requests, array $row, int $userId, RedirectResponse $back, string $targetName): RedirectResponse
    {
        if (! $requests->decide((int) $row['rec_id'], 'rejected', $userId)) {
            return $back->with('success_msg', 'Permintaan sudah diputuskan sebelumnya.');
        }

        if ($row['target_type'] === FilingAccessRequest::TYPE_FILE) {
            $this->audit($pdo, (int) $row['target_id'], $userId, 'access_request_reject', 'Permintaan akses ditolak');
        } else {
            AuditLog::logAudit($pdo, 'FILING_GROUP_ACCESS_REQUEST_REJECT', 'filing_group', (int) $row['target_id'], [
                'requester_id' => (int) $row['requester_id'],
                'request_id' => (int) $row['rec_id'],
            ], $userId);
        }

        $this->notifyDecision($pdo, $row, false, $userId, $targetName);

        return $back->with('success_msg', 'Permintaan ditolak; peminta telah diberi tahu.');
    }

    /**
     * Notifikasi masuk ke pemutus saat permintaan dibuat (pola TestAdmin:
     * target_url menunjuk halaman daftar, ref_table/ref_id membawa konteks).
     *
     * @param  int[]  $recipientIds
     */
    private function notifyRequestCreated(PDO $pdo, array $request, string $targetName, array $recipientIds): void
    {
        $requesterId = (int) $request['requester_id'];
        $requesterName = $this->userName($pdo, $requesterId);

        $message = $requesterName.' meminta akses ke "'.$targetName.'".';
        if (! empty($request['message'])) {
            $message .= ' Pesan: "'.(string) $request['message'].'"';
        }

        foreach (array_unique(array_map('intval', $recipientIds)) as $recipientId) {
            if ($recipientId <= 0 || $recipientId === $requesterId) {
                continue;
            }

            try {
                (new Notification($pdo))->create(
                    $recipientId,
                    $requesterId,
                    'filing_access_request',
                    'Permintaan Akses',
                    $message,
                    route('filing-system.requests.index'),
                    'file_access_request',
                    (int) $request['rec_id']
                );
            } catch (Throwable $e) {
                error_log('[FilingAccessRequestController] Notifikasi permintaan gagal: '.$e->getMessage());
            }
        }
    }

    private function notifyDecision(PDO $pdo, array $row, bool $approved, int $deciderId, string $targetName): void
    {
        $requesterId = (int) $row['requester_id'];
        if ($requesterId <= 0) {
            return;
        }

        $title = $approved ? 'Akses Disetujui' : 'Akses Ditolak';
        $message = 'Permintaan akses Anda untuk "'.$targetName.'" '.($approved ? 'disetujui.' : 'ditolak.');

        // File: akses baru terlihat di folder Shared; grup: kembali ke grupnya.
        $targetUrl = $row['target_type'] === FilingAccessRequest::TYPE_GROUP
            ? route('filing-system.groups.show', (int) $row['target_id'])
            : route('filing-system.index', ['folder' => FilingFolders::SHARED]);

        try {
            (new Notification($pdo))->create(
                $requesterId,
                $deciderId,
                'filing_access_decided',
                $title,
                $message,
                $targetUrl,
                'file_access_request',
                (int) $row['rec_id']
            );
        } catch (Throwable $e) {
            error_log('[FilingAccessRequestController] Notifikasi keputusan gagal: '.$e->getMessage());
        }
    }

    /**
     * Penerima notifikasi permintaan grup: pemilik + anggota perorangan
     * ber-role manager. Pengelola lewat divisi tetap melihat di halaman
     * daftar (reverse-lookup divisi->user sengaja tidak dibangun di sini).
     *
     * @return int[]
     */
    private function groupManagerRecipients(PDO $pdo, int $groupId): array
    {
        $ids = [];

        try {
            $stmt = $pdo->prepare('SELECT created_by FROM sys_filegrp WHERE rec_id = ? LIMIT 1');
            $stmt->execute([$groupId]);
            $owner = $stmt->fetchColumn();
            if ($owner !== false && $owner !== null) {
                $ids[(int) $owner] = true;
            }

            $stmt = $pdo->prepare("
                SELECT user_id FROM sys_filegrp_member
                WHERE group_id = ? AND member_type = 'user' AND role = 'manager'
            ");
            $stmt->execute([$groupId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
                $ids[(int) $uid] = true;
            }
        } catch (Throwable $e) {
            // Tabel grup belum siap: tanpa penerima.
        }

        return array_keys($ids);
    }

    private function userName(PDO $pdo, int $userId): string
    {
        if ($userId <= 0) {
            return 'Seorang pengguna';
        }

        try {
            $stmt = $pdo->prepare("
                SELECT COALESCE(NULLIF(account_nm, ''), CONCAT('User #', rec_id))
                FROM sysitc_users WHERE rec_id = ? LIMIT 1
            ");
            $stmt->execute([$userId]);
            $name = $stmt->fetchColumn();

            return $name !== false && $name !== null && $name !== '' ? (string) $name : 'Seorang pengguna';
        } catch (Throwable $e) {
            return 'Seorang pengguna';
        }
    }

    /**
     * Gerbang token untuk pembuatan permintaan: format, ada, tidak dicabut,
     * belum kedaluwarsa, kuota belum habis. Penolakan dikembalikan null dan
     * pemanggil mengalihkan ke halaman link (yang menampilkan alasannya).
     */
    private function resolveShare(FilingLinkService $linkService, PDO $pdo, string $token): ?array
    {
        if (! $linkService->isTokenFormat($token)) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM file_share_link WHERE share_code_hash = ? LIMIT 1');
        $stmt->execute([$linkService->hashToken($token)]);
        $share = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (! $share || $linkService->isRevoked($share) || $linkService->isExpired($share) || $linkService->isQuotaReached($share)) {
            return null;
        }

        return $share;
    }

    private function fileIsActive(?array $file): bool
    {
        return $file !== null
            && ($file['status'] ?? 'active') === 'active'
            && empty($file['deleted_at']);
    }

    private function audit(PDO $pdo, int $filingId, int $userId, string $action, string $notes): void
    {
        try {
            $stmt = $pdo->prepare('
                INSERT INTO sys_filing_audit (filing_id, user_id, action, ip_address, user_agent, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $filingId,
                $userId,
                $action,
                (string) request()->ip(),
                substr((string) request()->userAgent(), 0, 500),
                $notes,
            ]);
        } catch (Throwable $e) {
            error_log('[FilingAccessRequestController] Audit gagal: '.$e->getMessage());
        }
    }
}
