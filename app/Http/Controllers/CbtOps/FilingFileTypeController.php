<?php

namespace App\Http\Controllers\CbtOps;

use App\Http\Controllers\Controller;
use App\Services\FilingSystem\FilingPermissionService;
use App\Support\FilingSystem\FilingFileType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PDO;

class FilingFileTypeController extends Controller
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = DB::connection('run')->getPdo();
    }

    private function requireAdmin(): void
    {
        $userId = (int) auth_user_id();
        if ($userId <= 0) {
            abort(401, 'Silakan login terlebih dahulu.');
        }

        $permService = new FilingPermissionService($this->pdo);
        if (! $permService->isAdmin($userId)) {
            abort(403, 'Akses khusus Admin Filing System.');
        }
    }

    public function index(): View
    {
        $this->requireAdmin();

        $stmt = $this->pdo->prepare("
            SELECT rec_id, code, descr, urutan, statrec, lupdt
            FROM sys_msttable
            WHERE tbl_code = ?
            ORDER BY urutan ASC, descr ASC
        ");
        $stmt->execute([FilingFileType::TBL_CODE]);
        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return view('filing-system.admin.file-types', [
            'types' => $types,
            'isFilingAdmin' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireAdmin();

        $code = strtoupper(trim((string) $request->input('code', '')));
        $descr = strtoupper(trim((string) $request->input('descr', '')));
        $urutan = (int) $request->input('urutan', 0);

        if ($code === '' || strlen($code) > 5 || ! preg_match('/^[A-Z0-9]+$/', $code)) {
            return redirect()->back()->with('error_msg', 'Kode tipe wajib diisi (maksimal 5 karakter alfanumerik tanpa spasi).');
        }

        if ($descr === '' || strlen($descr) > 50) {
            return redirect()->back()->with('error_msg', 'Nama tipe file wajib diisi (maksimal 50 karakter).');
        }

        $check = $this->pdo->prepare('SELECT COUNT(*) FROM sys_msttable WHERE tbl_code = ? AND code = ?');
        $check->execute([FilingFileType::TBL_CODE, $code]);
        if ((int) $check->fetchColumn() > 0) {
            return redirect()->back()->with('error_msg', "Kode tipe '{$code}' sudah digunakan.");
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO sys_msttable (tbl_code, code, urutan, descr, notes, AddiNotes, statrec)
            VALUES (?, ?, ?, ?, '', '', 1)
        ");
        $stmt->execute([FilingFileType::TBL_CODE, $code, $urutan, $descr]);

        return redirect()->route('filing-system.admin.file-types.index')->with('success_msg', "Tipe file '{$descr}' berhasil ditambahkan.");
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->requireAdmin();

        $descr = strtoupper(trim((string) $request->input('descr', '')));
        $urutan = (int) $request->input('urutan', 0);

        if ($descr === '' || strlen($descr) > 50) {
            return redirect()->back()->with('error_msg', 'Nama tipe file wajib diisi (maksimal 50 karakter).');
        }

        $check = $this->pdo->prepare('SELECT code FROM sys_msttable WHERE rec_id = ? AND tbl_code = ?');
        $check->execute([$id, FilingFileType::TBL_CODE]);
        $row = $check->fetch(PDO::FETCH_ASSOC);

        if (! $row) {
            return redirect()->back()->with('error_msg', 'Tipe file tidak ditemukan.');
        }

        $stmt = $this->pdo->prepare("
            UPDATE sys_msttable
            SET descr = ?, urutan = ?
            WHERE rec_id = ? AND tbl_code = ?
        ");
        $stmt->execute([$descr, $urutan, $id, FilingFileType::TBL_CODE]);

        return redirect()->route('filing-system.admin.file-types.index')->with('success_msg', 'Tipe file berhasil diperbarui.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $this->requireAdmin();

        $check = $this->pdo->prepare('SELECT statrec, descr FROM sys_msttable WHERE rec_id = ? AND tbl_code = ?');
        $check->execute([$id, FilingFileType::TBL_CODE]);
        $row = $check->fetch(PDO::FETCH_ASSOC);

        if (! $row) {
            return redirect()->back()->with('error_msg', 'Tipe file tidak ditemukan.');
        }

        $newStat = (int) $row['statrec'] === 1 ? 0 : 1;
        $stmt = $this->pdo->prepare('UPDATE sys_msttable SET statrec = ? WHERE rec_id = ? AND tbl_code = ?');
        $stmt->execute([$newStat, $id, FilingFileType::TBL_CODE]);

        $statusLabel = $newStat === 1 ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->route('filing-system.admin.file-types.index')->with('success_msg', "Tipe file '{$row['descr']}' berhasil {$statusLabel}.");
    }
}
