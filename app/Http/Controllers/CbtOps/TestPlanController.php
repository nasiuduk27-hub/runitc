<?php

namespace App\Http\Controllers\CbtOps;

use App\Http\Controllers\Controller;
use App\Models\System\SysKota;
use App\Models\System\SysMsttable;
use App\Models\System\SysProvinsi;
use App\Support\Legacy\TadAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestPlanController extends Controller
{
    public function index(Request $request)
    {

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'type' => (string) $request->query('type', ''),
            'status' => (string) $request->query('status', '1'),
            'page' => max(1, (int) $request->query('page', 1)),
        ];

        $limit = 5;
        $offset = ($filters['page'] - 1) * $limit;
        $where = [];
        $params = [];

        if ($filters['search'] !== '') {
            $where[] = '(ts.spv_name LIKE ? OR ts.spv_alias LIKE ?)';
            $params[] = '%'.$filters['search'].'%';
            $params[] = '%'.$filters['search'].'%';
        }

        if ($filters['type'] !== '') {
            $where[] = 'ts.captain = ?';
            $params[] = $filters['type'] === 'CAP' ? 1 : 0;
        }

        if ($filters['status'] !== '' && $filters['status'] !== 'ALL') {
            $where[] = 'ts.status = ?';
            $params[] = $filters['status'];
        }

        $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';
        $totalRows = 0;
        $items = [];
        $error = null;

        try {
            $pdo = DB::connection('run')->getPdo();
            $count = $pdo->prepare("SELECT COUNT(ts.rec_id) FROM tad_supervisor ts {$whereSql}");
            $count->execute($params);
            $totalRows = (int) $count->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT ts.*,
                    tr.bank_code AS def_bank_code,
                    tr.bank_acc_no AS def_bank_no,
                    tr.bank_acc_name AS def_bank_name
                FROM tad_supervisor ts
                LEFT JOIN tad_rekening tr ON ts.rec_id = tr.tad_id AND tr.is_default = 1
                {$whereSql}
                ORDER BY ts.status DESC, ts.captain DESC, ts.spv_name ASC
                LIMIT {$limit} OFFSET {$offset}
            ");
            $stmt->execute($params);
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $this->hydrateCities($pdo, $items);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('cbt-ops.test-plan.index', [
            'filters' => $filters,
            'items' => $items,
            'totalRows' => $totalRows,
            'totalPages' => (int) ceil($totalRows / $limit),
            'limit' => $limit,
            'error' => $error,
            'canManageSupervisor' => TadAccess::canManageSupervisor(
                DB::connection('run')->getPdo(),
                (int) auth_user_id()
            ),
        ]);
    }

    public function create(Request $request)
    {
        $this->prepareRequest($request);
        $this->authorizeManage($request);

        return view('cbt-ops.test-plan.form', $this->formData($request, null, []));
    }

    public function store(Request $request)
    {
        $this->prepareRequest($request);
        $this->authorizeManage($request);

        try {
            $id = $this->saveTad($request);

            return redirect()->route('cbt-ops.test-plan.index')->with('success_msg', 'Data TAD berhasil disimpan. ID: '.$id);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error_msg', $e->getMessage());
        }
    }

    public function edit(Request $request, int $id)
    {
        $this->prepareRequest($request);
        $this->authorizeManage($request);

        $tad = $this->getTad($id);
        abort_unless($tad, 404);

        return view('cbt-ops.test-plan.form', $this->formData($request, $tad, $this->getBanks($id)));
    }

    public function update(Request $request, int $id)
    {
        $this->prepareRequest($request);
        $this->authorizeManage($request);

        try {
            $this->saveTad($request, $id);

            return redirect()->route('cbt-ops.test-plan.index')->with('success_msg', 'Data TAD berhasil diperbarui.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error_msg', $e->getMessage());
        }
    }

    public function destroy(Request $request, int $id)
    {
        $this->prepareRequest($request);
        $this->authorizeManage($request);

        $pdo = DB::connection('run')->getPdo();

        try {
            $pdo->beginTransaction();
            $tad = $this->getTad($id);
            if (! $tad) {
                throw new \RuntimeException('Data TAD/SPV tidak ditemukan.');
            }

            $pdo->prepare('DELETE FROM tad_rekening WHERE tad_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM tad_supervisor WHERE rec_id = ?')->execute([$id]);
            $this->removeTadSpvRole($pdo, (int) ($tad['itc_usr_id'] ?? 0));
            $pdo->commit();

            $this->logAudit('TEST_PLAN_DELETE', 'tad_supervisor', $id, [
                'deleted_by_user_id' => (int) auth_user_id(),
                'tad_name' => $tad['spv_name'] ?? null,
                'type' => ! empty($tad['captain']) ? 'CAP' : 'SPV',
            ]);

            return redirect()->route('cbt-ops.test-plan.index')->with('success_msg', 'Data TAD berhasil dihapus.');
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return redirect()->route('cbt-ops.test-plan.index')->with('error_msg', 'Gagal menghapus data TAD/SPV: '.$e->getMessage());
        }
    }

    private function hydrateCities(\PDO $pdo, array &$items): void
    {
        $cityIds = array_values(array_unique(array_filter(array_map('intval', array_column($items, 'city_id')))));
        if ($cityIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($cityIds), '?'));
        $stmt = $pdo->prepare("SELECT rec_id, nama FROM sys_kota WHERE rec_id IN ({$placeholders})");
        $stmt->execute($cityIds);
        $cityMap = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        foreach ($items as &$item) {
            $item['kota_nama'] = $cityMap[(int) ($item['city_id'] ?? 0)] ?? null;
        }
    }

    private function prepareRequest(Request $request): void {}

    private function authorizeManage(Request $request): void
    {
        abort_unless(TadAccess::canManageSupervisor(DB::connection('run')->getPdo(), (int) auth_user_id()), 403);
    }

    private function formData(Request $request, ?array $tad, array $banks): array
    {
        return [
            'tad' => $tad,
            'banks' => $banks,
            'itcUsers' => $this->getItcUsers((int) ($tad['itc_usr_id'] ?? 0)),
            'cities' => SysKota::query()->select('rec_id', 'nama')->orderBy('nama')->get(),
            'provinces' => SysProvinsi::query()->select('rec_id', 'nama')->orderBy('nama')->get(),
            'bankList' => SysMsttable::query()->where('tbl_code', '51')->where('statrec', 1)->orderBy('descr')->select('code', 'descr')->get(),
        ];
    }

    private function saveTad(Request $request, ?int $id = null): int
    {
        $pdo = DB::connection('run')->getPdo();
        $existing = $id ? $this->getTad($id) : null;
        if ($id && ! $existing) {
            throw new \RuntimeException('Data TAD/SPV tidak ditemukan.');
        }

        $itcUserId = (int) $request->input('itc_user_id');
        if ($itcUserId <= 0) {
            throw new \RuntimeException('Akun ITC / Rec ID User wajib dipilih.');
        }

        $stmtDuplicate = $pdo->prepare('SELECT rec_id FROM tad_supervisor WHERE itc_usr_id = ? AND rec_id <> ? LIMIT 1');
        $stmtDuplicate->execute([$itcUserId, $id ?: 0]);
        if ($stmtDuplicate->fetchColumn()) {
            throw new \RuntimeException('Akun ITC ini sudah terdaftar sebagai TAD/SPV. Edit atau hapus data yang sudah ada terlebih dahulu.');
        }

        TadAccess::ensureUserHasSpvRole($pdo, $itcUserId);
        $selectedUser = $this->getItcUser($itcUserId);
        if (! $selectedUser) {
            throw new \RuntimeException('Akun ITC yang dipilih tidak ditemukan atau tidak aktif.');
        }

        $name = strtoupper(trim((string) ($selectedUser['account_nm'] ?? '')));
        $alias = strtoupper(trim((string) ($selectedUser['alias_nm'] ?? '')));
        $gender = match (strtoupper((string) ($selectedUser['sexmf'] ?? ''))) {
            'F', 'P' => 'P',
            default => 'L',
        };
        $email = strtolower(trim((string) ($selectedUser['email_id'] ?? '')));
        $phone = trim((string) ($selectedUser['whatsapp'] ?? ''));
        $address = trim((string) ($selectedUser['address'] ?? ''));
        $cityId = $selectedUser['kotakabupaten'] ?: $request->input('city_id');
        $provCd = $selectedUser['prov_cd'] ?: $request->input('prov_cd');

        if ($name === '' || $phone === '') {
            throw new \RuntimeException('Nama dan Nomor Telepon wajib tersedia di data akun ITC.');
        }

        $photoPath = $existing['photo_path'] ?? $this->getItcUserPhotoPath($itcUserId);
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            if (! in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg', 'png'], true)) {
                throw new \RuntimeException('Format foto tidak valid. Gunakan JPG atau PNG.');
            }
            if ($file->getSize() > 2 * 1024 * 1024) {
                throw new \RuntimeException('Ukuran foto maksimal 2MB.');
            }

            $uploadDir = base_path('storage/photos/tad');
            if (! is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $newFileName = bin2hex(random_bytes(10)).'.'.strtolower($file->getClientOriginalExtension());
            $file->move($uploadDir, $newFileName);
            $photoPath = 'storage/photos/tad/'.$newFileName;
        }

        $captain = $request->input('type', 'SPV') === 'CAP' ? 1 : 0;
        $status = (int) $request->input('status', 1);
        $spvCategory = strtoupper(trim((string) $request->input('spv_category')));
        if ($spvCategory !== '' && ! in_array($spvCategory, ['REMOTE', 'ONSITE', 'TCA_SSW'], true)) {
            throw new \RuntimeException('Kategori tidak valid. Pilih REMOTE, ONSITE, atau TCA_SSW.');
        }
        $level = (int) $request->input('lvl_spv', 1);
        $skills = trim((string) $request->input('skills_notes', ''));

        $pdo->beginTransaction();
        try {
            if ($id) {
                $pdo->prepare('UPDATE tad_supervisor SET itc_usr_id = ?, spv_name = ?, spv_alias = ?, gender = ?, email = ?, phone = ?, address = ?, city_id = ?, spv_category = ?, prov_cd = ?, lvl_spv = ?, skills_notes = ?, captain = ?, status = ?, photo_path = ?, lupd = NOW() WHERE rec_id = ?')
                    ->execute([$itcUserId, $name, $alias, $gender, $email, $phone, $address, $cityId, $spvCategory, $provCd, $level, $skills, $captain, $status, $photoPath, $id]);
                $tadId = $id;
                $auditAction = 'TEST_PLAN_UPDATE';
                $pdo->prepare('DELETE FROM tad_rekening WHERE tad_id = ?')->execute([$tadId]);
            } else {
                $pdo->prepare('INSERT INTO tad_supervisor (itc_usr_id, spv_name, spv_alias, gender, email, phone, address, city_id, spv_category, prov_cd, lvl_spv, skills_notes, captain, status, photo_path, entdt, lupd) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())')
                    ->execute([$itcUserId, $name, $alias, $gender, $email, $phone, $address, $cityId, $spvCategory, $provCd, $level, $skills, $captain, $status, $photoPath]);
                $tadId = (int) $pdo->lastInsertId();
                $auditAction = 'TEST_PLAN_CREATE';
            }

            $defaultBank = $this->saveBanks($pdo, $tadId, $request);
            if ($defaultBank) {
                $this->syncItcUserDefaultBank($pdo, $itcUserId, $defaultBank[0], $defaultBank[1], $defaultBank[2]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $this->logAudit($auditAction, 'tad_supervisor', $tadId, [
            'itc_user_id' => $itcUserId,
            'tad_name' => $name,
            'type' => $captain ? 'CAP' : 'SPV',
            'status' => $status,
        ]);

        return $tadId;
    }

    private function saveBanks(\PDO $pdo, int $tadId, Request $request): ?array
    {
        $codes = (array) $request->input('bank_code', []);
        $nos = (array) $request->input('bank_acc_no', []);
        $names = (array) $request->input('bank_acc_name', []);
        $default = (int) $request->input('is_default_bank', 0);
        $defaultBank = null;
        $stmt = $pdo->prepare('INSERT INTO tad_rekening (tad_id, bank_code, bank_acc_no, bank_acc_name, is_default) VALUES (?, ?, ?, ?, ?)');

        foreach ($codes as $index => $code) {
            $code = trim((string) $code);
            $no = trim((string) ($nos[$index] ?? ''));
            $name = strtoupper(trim((string) ($names[$index] ?? '')));
            if ($code === '' || $no === '') {
                continue;
            }

            $isDefault = $index === $default ? 1 : 0;
            $stmt->execute([$tadId, $code, $no, $name, $isDefault]);
            if ($isDefault) {
                $defaultBank = [$code, $no, $name];
            }
        }

        return $defaultBank;
    }

    private function getTad(int $id): ?array
    {
        $row = DB::connection('run')->table('tad_supervisor')->where('rec_id', $id)->first();

        return $row ? (array) $row : null;
    }

    private function getBanks(int $id): array
    {
        return DB::connection('run')->table('tad_rekening')
            ->where('tad_id', $id)
            ->orderByDesc('is_default')
            ->orderBy('rec_id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function getItcUser(int $id): ?array
    {
        $users = $this->getItcUsers($id);
        foreach ($users as $user) {
            if ((int) $user['rec_id'] === $id) {
                return $user;
            }
        }

        return null;
    }

    private function getItcUsers(int $includeUserId = 0): array
    {
        $pdo = DB::connection('run')->getPdo();
        $stmt = $pdo->prepare("SELECT u.rec_id, u.account_nm, u.alias_nm, u.sexmf, u.whatsapp, u.address, u.kotakabupaten, u.prov_cd, l.account_id, COALESCE(m.email, l.email_id) AS email_id, b.bnkcd AS bank_code, b.accno AS bank_acc_no, b.accnm AS bank_acc_name FROM sysitc_users u JOIN sysitc_login l ON l.rec_id = u.login_rec_id LEFT JOIN sysitc_usermail m ON m.user_recid = u.rec_id AND m.asdefault = 1 LEFT JOIN sysitc_userbank b ON b.user_recid = u.rec_id AND b.asdefault = 1 LEFT JOIN sysitc_usracc ua ON ua.user_rec_id = u.rec_id LEFT JOIN sysitc_grpacc g ON g.grpaccess = ua.access_code AND g.grpacc = ua.access_account WHERE u.status = 1 AND ((? > 0 AND u.rec_id = ?) OR NOT EXISTS (SELECT 1 FROM tad_supervisor ts_used WHERE ts_used.itc_usr_id = u.rec_id)) GROUP BY u.rec_id, u.account_nm, u.alias_nm, u.sexmf, u.whatsapp, u.address, u.kotakabupaten, u.prov_cd, l.account_id, m.email, l.email_id, b.bnkcd, b.accno, b.accnm HAVING u.rec_id = ? OR SUM(CASE WHEN ua.user_rec_id IS NOT NULL THEN 1 ELSE 0 END) = 0 OR SUM(CASE WHEN UPPER(g.grpdesc) LIKE '%TAD%ADMIN%' OR UPPER(g.grpdesc) LIKE '%TAD%STAFF%' OR UPPER(g.grpdesc) LIKE '%TAD%SPV%' OR UPPER(g.grpdesc) = 'SUPER ADMIN' THEN 1 ELSE 0 END) > 0 ORDER BY u.account_nm ASC");
        $stmt->execute([$includeUserId, $includeUserId, $includeUserId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function removeTadSpvRole(\PDO $pdo, int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $stmtRole = $pdo->query("
            SELECT grpaccess, grpacc
            FROM sysitc_grpacc
            WHERE UPPER(grpdesc) LIKE '%TAD%SPV%'
            ORDER BY CASE WHEN UPPER(grpdesc) LIKE '%TAD%SPV%TEST%' THEN 0 ELSE 1 END, rec_id ASC
            LIMIT 1
        ");
        $role = $stmtRole->fetch(\PDO::FETCH_ASSOC);
        if (! $role) {
            return;
        }

        $stmt = $pdo->prepare('DELETE FROM sysitc_usracc WHERE user_rec_id = ? AND access_code = ? AND access_account = ?');
        $stmt->execute([$userId, $role['grpaccess'], $role['grpacc']]);
    }

    private function getItcUserPhotoPath(int $userId): ?string
    {
        foreach (['png', 'jpg', 'jpeg', 'gif'] as $ext) {
            $relative = 'assets/personal/user_'.$userId.'.'.$ext;
            if (is_file(base_path($relative))) {
                return $relative;
            }
        }

        return null;
    }

    private function syncItcUserDefaultBank(\PDO $pdo, int $userId, string $bankCode, string $accountNo, string $accountName): void
    {
        if ($userId <= 0 || $bankCode === '' || $accountNo === '') {
            return;
        }

        $stmt = $pdo->prepare('SELECT rec_id FROM sysitc_userbank WHERE user_recid = ? AND asdefault = 1 LIMIT 1');
        $stmt->execute([$userId]);
        $bankId = $stmt->fetchColumn();

        if ($bankId) {
            $pdo->prepare('UPDATE sysitc_userbank SET bnkcd = ?, accnm = ?, accno = ? WHERE rec_id = ?')->execute([$bankCode, $accountName, $accountNo, $bankId]);

            return;
        }

        $pdo->prepare('INSERT INTO sysitc_userbank (user_recid, bnkcd, accnm, accno, asdefault) VALUES (?, ?, ?, ?, 1)')->execute([$userId, $bankCode, $accountName, $accountNo]);
    }

    private function logAudit(string $action, string $targetType, int $targetId, array $metadata): void
    {
        try {
            DB::connection('run')->table('sys_audit_log')->insert([
                'actor_user_id' => auth_user_id(),
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'metadata_json' => json_encode($metadata),
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 255),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
