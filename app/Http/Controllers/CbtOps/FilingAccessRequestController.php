<?php

namespace App\Http\Controllers\CbtOps;

use App\Http\Controllers\Controller;
use App\Repositories\FilingSystem\FilingAccessRequest;
use App\Repositories\FilingSystem\FileGroup;
use App\Repositories\FilingSystem\FileSystemDrive;
use App\Services\FilingSystem\FilingLinkService;
use App\Services\FilingSystem\FilingPermissionService;
use App\Support\Legacy\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

/**
 * Meja masuk permintaan akses Filing System (Fase 3A).
 *
 * Dua permukaan meminta:
 * - Link token mode 1 (hanya hak, bukan mode token): pemegang link tanpa hak
 *   minta akses ke file; wajib login, form mengirim token (bukan id file).
 * - Halaman grup: bukan anggota grup aktif minta akses ke pengelola grup.
 *
 * Permukaan file langsung (preview/thumbnail/unduhan) sengaja TIDAK diberi
 * tombol minta akses: menampilkan nama file + pemilik di sana membuka oracle
 * baru bagi penebak id.
 * Keputusan (Setujui/Tolak) ada di batch 3B.
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
        if (! $requests->create(FilingAccessRequest::TYPE_FILE, $fileId, $userId, $message === '' ? null : $message)) {
            return redirect($linkUrl)->with('error_msg', 'Permintaan Anda untuk file ini masih menunggu keputusan.');
        }

        $this->audit($pdo, $fileId, $userId, 'access_request_create', 'Permintaan akses via link mode 1');

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
        if (! $requests->create(FilingAccessRequest::TYPE_GROUP, $group, $userId, $message === '' ? null : $message)) {
            return redirect($showUrl)->with('error_msg', 'Permintaan Anda untuk grup ini masih menunggu keputusan.');
        }

        AuditLog::logAudit($pdo, 'FILING_GROUP_ACCESS_REQUEST_CREATE', 'filing_group', $group, [
            'requester_id' => $userId,
        ], $userId);

        return redirect($showUrl)->with('success_msg', 'Permintaan akses terkirim ke pengelola grup.');
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
