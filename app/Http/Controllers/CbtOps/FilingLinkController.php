<?php

namespace App\Http\Controllers\CbtOps;

use App\Http\Controllers\Controller;
use App\Repositories\FilingSystem\FilingAccessRequest;
use App\Repositories\FilingSystem\FileSystemDrive;
use App\Services\FilingSystem\FilingLinkService;
use App\Services\FilingSystem\FilingPermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PDO;
use Throwable;

/**
 * Konsumen publik link token Filing System (Fase 2, tabel baru saja).
 *
 * Token mentah di URL -> hash sha256 -> baris file_share_link.
 * Semua penolakan token (tak dikenal/dicabut/kedaluwarsa/kuota) sengaja
 * digenerikkan anti-oracle. Halaman belum-punya-akses (mode 1) menampilkan
 * form Minta Akses (Fase 3A); hanya nama file + pemilik, tanpa isi/unduhan.
 */
class FilingLinkController extends Controller
{
    public function show(Request $request, string $token): View|Response
    {
        if (! FileSystemDrive::isEnabled()) {
            abort(404);
        }

        $linkService = new FilingLinkService;
        $pdo = DB::connection('run')->getPdo();

        [$share, $deny] = $this->resolveToken($linkService, $pdo, $token);
        if ($deny) {
            return $deny;
        }

        $drive = new FileSystemDrive($pdo);
        $file = $drive->getFileById((int) $share['filesys_id']);
        if (! $this->fileIsActive($file)) {
            return $this->unavailableView();
        }

        $hash = $linkService->hashToken($token);
        $mode = $linkService->modeOf($share);

        if ($mode === FilingLinkService::MODE_TOKEN) {
            if (! empty($share['password_hash']) && ! $request->session()->has('link_access_'.$hash)) {
                return $this->passwordView();
            }

            if (! $this->countAccessOnce($request, $pdo, $share, $hash)) {
                return $this->invalidView();
            }

            return $this->landingView($token, $drive, $file, $linkService->allowsDownload($share));
        }

        $userId = (int) auth_user_id();
        if ($userId <= 0) {
            $this->audit($pdo, (int) $share['filesys_id'], null, 'link_access_denied', 'Mode 1: belum login');

            return $this->noAccessView($pdo, $drive, $file);
        }

        $permissionService = new FilingPermissionService($pdo);
        $legacy = $drive->mapToLegacyShape($file);
        if (! $permissionService->canView($legacy, $userId)) {
            $this->audit($pdo, (int) $share['filesys_id'], $userId, 'link_access_denied', 'Mode 1: tanpa hak');

            return $this->noAccessView($pdo, $drive, $file);
        }

        if (! $this->countAccessOnce($request, $pdo, $share, $hash)) {
            return $this->invalidView();
        }

        $canDownload = $linkService->allowsDownload($share)
            && $permissionService->canDownload($legacy, $userId);

        return $this->landingView($token, $drive, $file, $canDownload);
    }

    public function password(Request $request, string $token): View|RedirectResponse|Response
    {
        if (! FileSystemDrive::isEnabled()) {
            abort(404);
        }

        $linkService = new FilingLinkService;
        $pdo = DB::connection('run')->getPdo();

        [$share, $deny] = $this->resolveToken($linkService, $pdo, $token);
        if ($deny) {
            return $deny;
        }

        if ($linkService->modeOf($share) !== FilingLinkService::MODE_TOKEN || empty($share['password_hash'])) {
            return $this->invalidView();
        }

        $drive = new FileSystemDrive($pdo);
        $file = $drive->getFileById((int) $share['filesys_id']);
        if (! $this->fileIsActive($file)) {
            return $this->unavailableView();
        }

        $hash = $linkService->hashToken($token);
        $failsKey = $linkService->passwordAttemptsKey($hash);
        $fails = (int) $request->session()->get($failsKey, 0);
        $userId = (int) auth_user_id();

        if ($linkService->isPasswordThrottled($fails)) {
            $this->audit($pdo, (int) $share['filesys_id'], $userId > 0 ? $userId : null, 'link_access_denied', 'Password throttled');

            return $this->passwordView('Password salah atau kosong.');
        }

        $input = (string) $request->input('password', '');
        if ($input === '' || ! password_verify($input, (string) $share['password_hash'])) {
            $request->session()->put($failsKey, $fails + 1);
            $this->audit($pdo, (int) $share['filesys_id'], $userId > 0 ? $userId : null, 'link_access_denied', 'Password salah');

            return $this->passwordView('Password salah atau kosong.');
        }

        $request->session()->forget($failsKey);

        if (! $this->countAccessOnce($request, $pdo, $share, $hash)) {
            return $this->invalidView();
        }

        return redirect()->route('filing-system.link.show', ['token' => $token]);
    }

    public function download(Request $request, string $token): Response|RedirectResponse
    {
        if (! FileSystemDrive::isEnabled()) {
            abort(404);
        }

        $linkService = new FilingLinkService;
        $pdo = DB::connection('run')->getPdo();

        [$share, $deny] = $this->resolveToken($linkService, $pdo, $token);
        if ($deny) {
            return $deny;
        }

        $drive = new FileSystemDrive($pdo);
        $file = $drive->getFileById((int) $share['filesys_id']);
        if (! $this->fileIsActive($file)) {
            return $this->unavailableView();
        }

        if (! $linkService->allowsDownload($share)) {
            return response('Tautan ini tidak mengizinkan unduhan.', 403);
        }

        $hash = $linkService->hashToken($token);

        // Wajib lewat halaman singgah (dan password untuk mode 2) sebelum unduh.
        if (! $request->session()->has('link_access_'.$hash)) {
            return redirect()->route('filing-system.link.show', ['token' => $token]);
        }

        if ($linkService->modeOf($share) === FilingLinkService::MODE_RIGHTS) {
            $userId = (int) auth_user_id();
            $permissionService = new FilingPermissionService($pdo);
            $legacy = $drive->mapToLegacyShape($file);

            if ($userId <= 0 || ! $permissionService->canDownload($legacy, $userId)) {
                return $this->noAccessView($pdo, $drive, $file);
            }
        }

        // Mesin unduh yang sama dengan RFS; metode ini memeriksa ulang share + file.
        $driveController = new FilingDriveController($pdo, $this->ftpConfig());
        $driveController->downloadNewViaShare((int) $share['filesys_id'], $hash);

        // downloadNewViaShare selalu exit saat sukses/gagal.
        return response('', 500);
    }

    /**
     * Gerbang token: format, ada, tidak dicabut, belum kedaluwarsa, kuota.
     *
     * @return array{0: array|null, 1: Response|null}
     */
    private function resolveToken(FilingLinkService $linkService, PDO $pdo, string $token): array
    {
        if (! $linkService->isTokenFormat($token)) {
            return [null, $this->invalidView()];
        }

        $stmt = $pdo->prepare('SELECT * FROM file_share_link WHERE share_code_hash = ? LIMIT 1');
        $stmt->execute([$linkService->hashToken($token)]);
        $share = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (! $share || $linkService->isRevoked($share)) {
            return [null, $this->invalidView()];
        }

        if ($linkService->isExpired($share)) {
            $this->audit($pdo, (int) $share['filesys_id'], null, 'link_access_denied', 'Link kedaluwarsa');

            return [null, $this->invalidView()];
        }

        if ($linkService->isQuotaReached($share)) {
            $this->audit($pdo, (int) $share['filesys_id'], null, 'link_access_denied', 'Kuota link habis');

            return [null, $this->invalidView()];
        }

        return [$share, null];
    }

    /**
     * Naikkan access_count SEKALI per session per link (bukan tiap refresh,
     * supaya kuota tidak terbakar oleh reload). UPDATE tergerbang kuota
     * menutup balapan dua pembukaan paralel.
     */
    private function countAccessOnce(Request $request, PDO $pdo, array $share, string $hash): bool
    {
        if ($request->session()->has('link_access_'.$hash)) {
            return true;
        }

        $stmt = $pdo->prepare('
            UPDATE file_share_link
            SET access_count = access_count + 1
            WHERE rec_id = ? AND (max_access = 0 OR access_count < max_access)
        ');
        $stmt->execute([(int) $share['rec_id']]);

        $userId = (int) auth_user_id();

        if ($stmt->rowCount() < 1) {
            $this->audit($pdo, (int) $share['filesys_id'], $userId > 0 ? $userId : null, 'link_access_denied', 'Kuota link habis (balapan)');

            return false;
        }

        $request->session()->put('link_access_'.$hash, true);
        $this->audit($pdo, (int) $share['filesys_id'], $userId > 0 ? $userId : null, 'link_access', 'Akses via link');

        return true;
    }

    private function fileIsActive(?array $file): bool
    {
        return $file !== null
            && ($file['status'] ?? 'active') === 'active'
            && empty($file['deleted_at']);
    }

    private function landingView(string $token, FileSystemDrive $drive, array $file, bool $canDownload): View
    {
        return view('filing-system.link.show', [
            'state' => 'ready',
            'fileName' => (string) ($file['file_name'] ?? 'File'),
            'fileSize' => $drive->formatFileSize((int) ($file['file_zip_size'] ?? 0)),
            'ownerName' => $this->ownerName($drive, $file),
            'canDownload' => $canDownload,
            'downloadUrl' => route('filing-system.link.download', ['token' => $token]),
        ]);
    }

    private function passwordView(?string $error = null): View
    {
        return view('filing-system.link.show', [
            'state' => 'password',
            'error' => $error,
        ]);
    }

    private function noAccessView(PDO $pdo, FileSystemDrive $drive, array $file): Response
    {
        return response()->view('filing-system.link.show', [
            'state' => 'no_access',
            'fileName' => (string) ($file['file_name'] ?? 'File'),
            'ownerName' => $this->ownerName($drive, $file),
            'requestState' => $this->requestState($pdo, $file),
        ], 403);
    }

    /**
     * Status form Minta Akses di halaman no_access:
     * guest (belum login), form, pending (sudah ada permintaan), blocked
     * (confidential: permintaan yang tak akan bisa disetujui tidak dijanjikan).
     */
    private function requestState(PDO $pdo, array $file): string
    {
        $userId = (int) auth_user_id();
        if ($userId <= 0) {
            return 'guest';
        }

        if (($file['security_level'] ?? 'normal') === 'confidential') {
            return 'blocked';
        }

        $requests = new FilingAccessRequest($pdo);
        if ($requests->hasPending(FilingAccessRequest::TYPE_FILE, (int) ($file['rec_id'] ?? 0), $userId)) {
            return 'pending';
        }

        return 'form';
    }

    private function unavailableView(): Response
    {
        return response()->view('filing-system.link.show', ['state' => 'unavailable'], 404);
    }

    private function invalidView(): Response
    {
        return response()->view('filing-system.link.show', ['state' => 'invalid'], 404);
    }

    private function ownerName(FileSystemDrive $drive, array $file): string
    {
        $owner = $drive->getOwnerInfo((int) ($file['userid'] ?? 0));

        if (! empty($owner['alias_nm'])) {
            return (string) $owner['alias_nm'];
        }

        return ! empty($owner['account_nm']) ? (string) $owner['account_nm'] : 'Pemilik';
    }

    private function audit(PDO $pdo, int $filingId, ?int $userId, string $action, string $notes): void
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
            error_log('[FilingLinkController] Audit gagal: '.$e->getMessage());
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
