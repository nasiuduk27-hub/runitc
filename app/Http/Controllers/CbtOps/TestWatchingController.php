<?php

namespace App\Http\Controllers\CbtOps;

use App\Http\Controllers\Controller;
use App\Support\Legacy\FilingSystem;
use App\Support\Legacy\FtpStorage;
use App\Support\Legacy\MonitoringController;
use App\Support\Legacy\Nisn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class TestWatchingController extends Controller
{
    public function monitoring(Request $request): JsonResponse|View|RedirectResponse
    {
        return $this->renderMonitoring($request);
    }

    public function monitoringHybrid(Request $request): JsonResponse|View|RedirectResponse
    {
        $request->query->set('monitoring_mode', 'hybrid');
        $_GET['monitoring_mode'] = 'hybrid';

        return $this->renderMonitoring($request);
    }

    private function renderMonitoring(Request $request): JsonResponse|View|RedirectResponse
    {
        $this->syncLegacyRequestSuperglobals($request);

        $legacyController = new MonitoringController(
            DB::connection('mysql')->getPdo(),
            DB::connection('run')->getPdo(),
            DB::connection('war')->getPdo(),
            $this->ftpConfig(),
        );

        $pageData = $legacyController->handle();

        $currentMonitoringMode = strtolower(trim((string) ($pageData['monitoring_mode'] ?? ($pageData['test_info']['monitoring_mode'] ?? $request->query('monitoring_mode', 'online')))));
        $currentMonitoringMode = $currentMonitoringMode === 'hybrid' ? 'hybrid' : 'online';

        if ($request->boolean('ajax_timer_sync')) {
            return $this->monitoringTimerSync($request, $pageData);
        }

        $redirect = $this->monitoringRedirectForAssignedRoom($request, $pageData, $currentMonitoringMode);
        if ($redirect !== null) {
            return $redirect;
        }

        $isInRoom = ! empty($pageData['is_in_room']);
        $fatalError = (string) ($pageData['fatal_error'] ?? '');
        $isCleanRoom = $isInRoom && $fatalError === '';

        $spvPhotoUrl = url('/assets/personal/nopicture.png');
        $spv = is_array($pageData['spv'] ?? null) ? $pageData['spv'] : [];
        if (! empty($spv['id'])) {
            foreach (['png', 'jpg', 'jpeg', 'gif'] as $ext) {
                $relative = 'assets/personal/user_'.$spv['id'].'.'.$ext;
                if (file_exists(base_path($relative))) {
                    $spvPhotoUrl = url('/'.$relative).'?v='.filemtime(base_path($relative));
                    break;
                }
            }
        }

        return view('cbt-ops.test-watching.monitoring', $pageData + [
            'date' => $request->query('date', ''),
            'admin' => $request->query('admin', ''),
            'user_id' => $request->session()->get('user_id'),
            'user_name' => $request->session()->get('user_name', $request->session()->get('account_nm', 'User')),
            'currentMonitoringMode' => $currentMonitoringMode,
            'is_hybrid_mode' => $currentMonitoringMode === 'hybrid',
            'current_admin_no_for_decrypt' => $pageData['current_admin_no'] ?? '',
            'spv_photo_url' => $spvPhotoUrl,
            'isCleanRoom' => $isCleanRoom,
            'monitoringModeFromAdminData' => fn (array $adminData): string => $this->monitoringModeFromAdminData($adminData),
        ]);
    }

    public function participantPhoto(Request $request): Response|BinaryFileResponse
    {
        $ids = $this->participantPhotoIds($request);
        if ($ids === []) {
            return $this->defaultPhotoResponse();
        }

        $cacheDir = base_path('storage/uploads/participant_photos');
        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        $extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        foreach ($ids as $id) {
            foreach ($extensions as $extension) {
                $file = $cacheDir.'/'.$id.'.'.$extension;
                if (is_file($file) && filesize($file) > 0) {
                    return response()->file($file, [
                        'Content-Type' => $this->imageMime($extension),
                        'Cache-Control' => 'public, max-age=86400',
                    ]);
                }
            }
        }

        $fromPublic = $this->fetchPublicParticipantPhoto($ids, $extensions, $cacheDir);
        if ($fromPublic !== null) {
            return $fromPublic;
        }

        return $this->defaultPhotoResponse();
    }

    public function timerControl(Request $request): JsonResponse
    {
        if (! $request->isMethod('post')) {
            return response()->json(['success' => false, 'message' => 'Invalid request']);
        }

        $itcUserId = (int) $request->session()->get('user_id', 0);
        if ($itcUserId <= 0) {
            return response()->json(['success' => false, 'message' => 'Sesi login tidak valid'], 403);
        }

        $authorize = trim((string) $request->input('user_id', ''));
        if ($authorize === '') {
            return response()->json(['success' => false, 'message' => 'User tidak valid']);
        }

        $war = DB::connection('war')->getPdo();
        $stmt = $war->prepare('SELECT rec_id, admin_id, sub_adm_id, remindtm, statrec, ke_suspend FROM t3sTt4keR5 WHERE authorize = ?');
        $stmt->execute([$authorize]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'User tidak ditemukan']);
        }

        $access = $this->validateRoomAccess($itcUserId, (int) $user['admin_id'], (int) $user['sub_adm_id']);
        if ($access !== true) {
            return $access;
        }

        try {
            switch ($request->input('action')) {
                case 'play':
                    $stmt = $war->prepare("UPDATE t3sTt4keR5 SET statrec = '3', lupdt = NOW() WHERE authorize = ? AND statrec = '2'");
                    $stmt->execute([$authorize]);
                    break;

                case 'pause':
                    $stmt = $war->prepare('UPDATE t3sTt4keR5 SET ke_suspend = CASE WHEN ke_suspend = 1 THEN 0 ELSE 1 END, lupdt = NOW() WHERE authorize = ?');
                    $stmt->execute([$authorize]);
                    break;

                case 'stop':
                    $stmt = $war->prepare("UPDATE t3sTt4keR5 SET statrec = '7', ke_suspend = 1, lupdt = NOW() WHERE authorize = ?");
                    $stmt->execute([$authorize]);
                    break;

                default:
                    return response()->json(['success' => false, 'message' => 'Action tidak dikenal']);
            }

            $stmt = $war->prepare('SELECT authorize, statrec, ke_suspend, remindtm FROM t3sTt4keR5 WHERE authorize = ?');
            $stmt->execute([$authorize]);

            return response()->json(['success' => true, 'data' => $stmt->fetch(\PDO::FETCH_ASSOC) ?: []]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function timerControlRoom(Request $request): JsonResponse
    {
        if (! $request->isMethod('post')) {
            return response()->json(['success' => false, 'message' => 'Invalid request']);
        }

        $itcUserId = (int) $request->session()->get('user_id', 0);
        $adminId = (int) $request->input('admin_rec_id', 0);
        $subAdminId = (int) $request->input('sub_admin_id', 0);

        if ($itcUserId <= 0) {
            return response()->json(['success' => false, 'message' => 'Sesi login tidak valid'], 403);
        }
        if ($adminId <= 0 || $subAdminId <= 0) {
            return response()->json(['success' => false, 'message' => 'Data room tidak valid']);
        }

        $access = $this->validateRoomAccess($itcUserId, $adminId, $subAdminId);
        if ($access !== true) {
            return $access;
        }

        $war = DB::connection('war')->getPdo();

        try {
            switch (trim((string) $request->input('action', ''))) {
                case 'play':
                    $stmtCheck = $war->prepare("SELECT COUNT(*) AS total_peserta, SUM(CASE WHEN statrec = '2' THEN 1 ELSE 0 END) AS total_readiness FROM t3sTt4keR5 WHERE admin_id = ? AND sub_adm_id = ?");
                    $stmtCheck->execute([$adminId, $subAdminId]);
                    $roomStatus = $stmtCheck->fetch(\PDO::FETCH_ASSOC) ?: [];
                    $totalPeserta = (int) ($roomStatus['total_peserta'] ?? 0);
                    $totalReadiness = (int) ($roomStatus['total_readiness'] ?? 0);

                    if ($totalPeserta === 0) {
                        throw new \RuntimeException('Start All gagal: peserta tidak ditemukan.');
                    }
                    if ($totalReadiness !== $totalPeserta) {
                        throw new \RuntimeException("Start All gagal: semua peserta harus Readiness. Saat ini {$totalReadiness} dari {$totalPeserta} peserta Readiness.");
                    }

                    $stmt = $war->prepare("UPDATE t3sTt4keR5 SET statrec = '3', ke_suspend = 0, lupdt = NOW() WHERE admin_id = ? AND sub_adm_id = ? AND statrec = '2'");
                    $stmt->execute([$adminId, $subAdminId]);
                    break;

                case 'pause':
                    $stmt = $war->prepare("UPDATE t3sTt4keR5 SET ke_suspend = 1, lupdt = NOW() WHERE admin_id = ? AND sub_adm_id = ? AND statrec IN ('3', '4', '5', '6') AND ke_suspend != 1");
                    $stmt->execute([$adminId, $subAdminId]);
                    break;

                case 'resume':
                    $stmt = $war->prepare('UPDATE t3sTt4keR5 SET ke_suspend = 0, lupdt = NOW() WHERE admin_id = ? AND sub_adm_id = ? AND ke_suspend = 1');
                    $stmt->execute([$adminId, $subAdminId]);
                    break;

                default:
                    return response()->json(['success' => false, 'message' => 'Action tidak dikenal']);
            }

            return response()->json(['success' => true, 'affected_rows' => $stmt->rowCount()]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function outboundReceiver(Request $request): JsonResponse|BinaryFileResponse
    {
        if (! $request->isMethod('post')) {
            return response()->json(['success' => false, 'message' => 'Method tidak diizinkan.'], 405);
        }

        $token = trim((string) env('OUTBOUND_RECEIVER_TOKEN', 'annas123'));
        if ($token === '') {
            return response()->json(['success' => false, 'message' => 'OUTBOUND_RECEIVER_TOKEN belum dikonfigurasi.'], 503);
        }

        $bearer = trim((string) $request->bearerToken());
        if ($bearer === '' || ! hash_equals($token, $bearer)) {
            return response()->json(['success' => false, 'message' => 'Token tidak valid.'], 401);
        }

        $adminNo = $this->safeOutboundAdminNo((string) $request->input('admin_no', ''));
        if ($adminNo === '') {
            return response()->json(['success' => false, 'message' => 'admin_no tidak valid.'], 400);
        }

        $storageRoot = rtrim((string) env('OUTBOUND_STORAGE_ROOT', '/www/wwwroot/cbt.toeic.or.id/docs/CBT/OUTBOUND'), '\\/');
        if (! is_dir($storageRoot)) {
            return response()->json(['success' => false, 'message' => 'Folder OUTBOUND tidak ditemukan.'], 500);
        }

        $action = trim((string) $request->input('action', 'list'));
        if ($action === 'list') {
            return response()->json([
                'success' => true,
                'admin_no' => $adminNo,
                'files' => $this->outboundFiles($storageRoot, $adminNo),
            ]);
        }

        if ($action === 'download') {
            $fileName = basename(str_replace('\\', '/', (string) $request->input('file', '')));
            if (! $this->isValidOutboundFileName($adminNo, $fileName)) {
                return response()->json(['success' => false, 'message' => 'Nama file tidak valid.'], 400);
            }

            $path = $storageRoot.DIRECTORY_SEPARATOR.$fileName;
            if (! is_file($path) || ! is_readable($path)) {
                return response()->json(['success' => false, 'message' => 'File outbound tidak ditemukan.'], 404);
            }

            return response()->download($path, $fileName, [
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'must-revalidate',
                'Pragma' => 'public',
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Action tidak dikenal.'], 400);
    }

    public function crcReceiver(Request $request): JsonResponse|BinaryFileResponse
    {
        if (! $request->isMethod('post')) {
            return response()->json(['success' => false, 'message' => 'Method tidak diizinkan.'], 405);
        }

        $token = trim((string) env('CRC_UPLOAD_TOKEN', 'annas123'));
        if ($token === '') {
            return response()->json(['success' => false, 'message' => 'CRC_UPLOAD_TOKEN belum dikonfigurasi.'], 503);
        }

        $bearer = trim((string) $request->bearerToken());
        if ($bearer === '' || ! hash_equals($token, $bearer)) {
            return response()->json(['success' => false, 'message' => 'Token tidak valid.'], 401);
        }

        $type = trim((string) $request->input('type', 'crc'));
        if (! in_array($type, ['crc', 'crc_individual', 'berita_acara', 'filing', 'attendance'], true)) {
            return response()->json(['success' => false, 'message' => 'Tipe tidak dikenal. Gunakan "crc", "crc_individual", "berita_acara", "filing", atau "attendance".'], 400);
        }

        $adminNo = $this->safeReceiverName((string) $request->input('admin_no', ''));
        if ($adminNo === '') {
            return response()->json(['success' => false, 'message' => 'admin_no tidak valid setelah sanitasi.'], 400);
        }

        $action = trim((string) $request->input('action', 'upload'));
        $storageRoot = rtrim((string) env('CRC_STORAGE_ROOT', base_path('modules/cbt_ops/test_watching')), '\\/');
        $testDate = $this->receiverDate((string) ($request->input('test_date', $request->input('tanggal', ''))));
        if ($testDate === '' && $action === 'upload') {
            $testDate = date('Ymd');
        }

        if ($action === 'list') {
            return response()->json([
                'success' => true,
                'admin_no' => $adminNo,
                'files_by_category' => $this->receiverFilesByCategory($storageRoot, $adminNo),
            ]);
        }

        if ($action === 'download_zip') {
            return $this->downloadReceiverZip($storageRoot, $adminNo);
        }

        if ($type === 'crc_individual') {
            return $this->receiveIndividualCrc($request, $storageRoot, $adminNo, $testDate, $type);
        }

        $file = $request->file('file');
        if (! $file instanceof UploadedFile) {
            return response()->json(['success' => false, 'message' => 'File belum dikirim.'], 400);
        }
        if (! $file->isValid()) {
            return response()->json(['success' => false, 'message' => 'Upload file gagal. Error: '.$file->getError()], 400);
        }

        $fileName = $this->safeReceiverName($file->getClientOriginalName());
        if ($fileName === '') {
            return response()->json(['success' => false, 'message' => 'Nama file tidak valid setelah sanitasi.'], 400);
        }

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $validation = $this->validateReceiverFileType($type, $extension);
        if ($validation !== true) {
            return response()->json(['success' => false, 'message' => $validation], 400);
        }

        $fileSize = (int) $file->getSize();
        if ($fileSize <= 0) {
            return response()->json(['success' => false, 'message' => 'File kosong.'], 400);
        }

        $maxSize = $type === 'crc' ? 50 * 1024 * 1024 : 25 * 1024 * 1024;
        if ($fileSize > $maxSize) {
            return response()->json(['success' => false, 'message' => 'Ukuran file terlalu besar (max '.($maxSize / 1024 / 1024).'MB).'], 400);
        }

        $targetFileName = $type === 'crc' ? ($testDate !== '' ? $testDate : 'GrabCRC') : $fileName;
        $targetDir = $type === 'crc'
            ? $storageRoot.'/'.$adminNo.'/'.$targetFileName
            : $storageRoot.'/'.$adminNo.'/'.$testDate.'/Documents';

        if (! is_dir($targetDir) && ! @mkdir($targetDir, 0755, true) && ! is_dir($targetDir)) {
            return response()->json(['success' => false, 'message' => 'Gagal membuat folder: '.$targetDir], 500);
        }
        if (! is_writable($targetDir)) {
            return response()->json(['success' => false, 'message' => 'Folder tujuan tidak writable.'], 500);
        }

        if ($type === 'crc') {
            return $this->extractReceiverCrcZip($file, $targetDir, $adminNo, $targetFileName);
        }

        $targetPath = $targetDir.'/'.$targetFileName;
        $file->move($targetDir, $targetFileName);
        $savedSize = is_file($targetPath) ? (int) filesize($targetPath) : 0;
        $expectedSize = is_numeric($request->input('file_size')) ? (int) $request->input('file_size') : 0;
        if ($expectedSize > 0 && $savedSize !== $expectedSize) {
            @unlink($targetPath);

            return response()->json(['success' => false, 'message' => 'Ukuran file tidak sesuai setelah simpan.'], 500);
        }

        $relativePath = $testDate.'/Documents/'.$targetFileName;

        return response()->json([
            'success' => true,
            'message' => $type === 'berita_acara' ? 'Berita Acara PDF berhasil disimpan.' : ($type === 'attendance' ? 'File absensi final berhasil disimpan.' : 'File Filing System berhasil disimpan.'),
            'type' => $type,
            'admin_no' => $adminNo,
            'file_name' => $targetFileName,
            'file_size' => $savedSize,
            'target_path' => $targetPath,
            'relative_path' => $relativePath,
            'remote_path' => $adminNo.'/'.$relativePath,
            'download_url' => $this->receiverPublicUrl($request, $adminNo, $relativePath),
        ]);
    }

    public function uploadCrc(Request $request): JsonResponse
    {
        if (! $request->isMethod('post')) {
            return response()->json(['success' => false, 'message' => 'Invalid request method', 'error' => true], 405);
        }

        $itcUserId = (int) $request->session()->get('user_id', 0);
        $adminCode = trim((string) $request->input('admin_code', ''));
        $adminRecId = (int) $request->input('admin_rec_id', 0);
        $subAdminId = (int) $request->input('sub_admin_id', 0);

        if ($adminCode === '') {
            return response()->json(['success' => false, 'message' => 'Admin code tidak ditemukan', 'error' => true], 400);
        }
        if ($itcUserId <= 0) {
            return response()->json(['success' => false, 'message' => 'Sesi login tidak valid', 'error' => true], 403);
        }
        if ($adminRecId <= 0 || $subAdminId <= 0) {
            return response()->json(['success' => false, 'message' => 'Data room monitoring tidak lengkap', 'error' => true], 400);
        }

        $access = $this->validateRoomAccess($itcUserId, $adminRecId, $subAdminId);
        if ($access !== true) {
            return $access;
        }

        $ftp = new FtpStorage($this->ftpConfig());
        $safeAdminNo = $ftp->safeFileName($adminCode);
        $date = trim((string) $request->input('date', date('Y-m-d')));
        $dateFolder = $this->crcDateFolder($date);
        $files = $this->crcUploadedFiles($request);

        if ($files === []) {
            return response()->json(['success' => false, 'message' => 'File CRC tidak dipilih', 'error' => true], 400);
        }
        if (count($files) > 500) {
            return response()->json(['success' => false, 'message' => 'Maksimal 500 file .CRC sekali upload.', 'error' => true], 400);
        }
        if (array_sum(array_map(fn (UploadedFile $file): int => (int) $file->getSize(), $files)) > 10485760) {
            return response()->json(['success' => false, 'message' => 'Total ukuran file CRC maksimal 10MB sekali upload.', 'error' => true], 400);
        }

        $validFiles = [];
        $errors = [];
        foreach ($files as $file) {
            $originalName = basename($file->getClientOriginalName());
            if (! $file->isValid()) {
                $errors[] = ['file_name' => $originalName, 'message' => 'Upload error'];

                continue;
            }
            if (strtolower($file->getClientOriginalExtension()) !== 'crc') {
                $errors[] = ['file_name' => $originalName, 'message' => 'Format file harus .CRC'];

                continue;
            }
            if ((int) $file->getSize() <= 0 || (int) $file->getSize() > 1048576) {
                $errors[] = ['file_name' => $originalName, 'message' => 'File kosong atau lebih dari 1MB'];

                continue;
            }

            $validFiles[] = [
                'file' => $file,
                'safe_name' => $this->safeCrcName($ftp, $originalName),
            ];
        }

        $uploaded = [];
        $httpUploadUrl = trim((string) env('CRC_HTTP_UPLOAD_URL', ''));
        if ($validFiles !== [] && $httpUploadUrl !== '') {
            try {
                $result = $this->uploadCrcViaHttpReceiver($validFiles, $safeAdminNo, $dateFolder, $httpUploadUrl);
                $uploaded = $result['files'] ?? [];
                foreach (($result['errors'] ?? []) as $error) {
                    $errors[] = $error;
                }
            } catch (\Throwable $e) {
                foreach ($validFiles as $item) {
                    $errors[] = ['file_name' => $item['safe_name'], 'message' => $e->getMessage()];
                }
            }
        } elseif ($validFiles !== []) {
            foreach ($validFiles as $item) {
                $remotePath = $ftp->normalizePath($safeAdminNo.'/'.$dateFolder.'/CRC/'.$item['safe_name']);
                try {
                    $ftp->upload($item['file']->getRealPath(), $remotePath);
                    $uploaded[] = [
                        'file_name' => $item['safe_name'],
                        'file_type' => 'crc',
                        'file_category' => 'crc_individual',
                        'file_path' => $remotePath,
                        'relative_path' => $dateFolder.'/CRC/'.$item['safe_name'],
                    ];
                } catch (\Throwable $e) {
                    $errors[] = ['file_name' => $item['safe_name'], 'message' => $e->getMessage()];
                }
            }
            $ftp->close();
        }

        if ($uploaded !== []) {
            $spvName = $this->spvName($itcUserId);
            (new FilingSystem(DB::connection('mysql')->getPdo(), DB::connection('run')->getPdo(), $this->ftpConfig()))->touchFilingActivity([
                'admin_no' => $safeAdminNo,
                'admin_rec_id' => $adminRecId,
                'sub_admin_id' => $subAdminId,
                'tanggal' => $date,
                'keterangan' => 'Upload CRC Manual',
                'input_by' => $request->session()->get('account_nm') ?? $request->session()->get('user_name', 'System'),
                'spv_name' => $spvName ?: ($request->session()->get('account_nm') ?? 'System'),
            ]);
        }

        return response()->json([
            'success' => $uploaded !== [],
            'message' => count($uploaded).' file CRC berhasil diupload'.($errors !== [] ? ', '.count($errors).' gagal.' : '.'),
            'uploaded_count' => count($uploaded),
            'failed_count' => count($errors),
            'files' => $uploaded,
            'errors' => $errors,
        ], $uploaded === [] ? 400 : 200);
    }

    public function generateCrc(Request $request): JsonResponse|BinaryFileResponse
    {
        if (! $request->isMethod('post')) {
            return response()->json(['success' => false, 'message' => 'Akses tidak valid.', 'error' => true], 405);
        }
        if (! class_exists(ZipArchive::class)) {
            return response()->json(['success' => false, 'message' => 'Extension ZipArchive belum aktif di server.', 'error' => true], 500);
        }

        $adminCode = trim((string) $request->input('admin_code', ''));
        $adminRecId = (int) $request->input('admin_rec_id', 0);
        $subAdminId = (int) $request->input('sub_admin_id', 0);
        $testType = trim((string) $request->input('test_type', ''));
        $testDate = trim((string) $request->input('test_date', ''));
        $selectedTakers = array_values(array_filter((array) $request->input('selected_takers', []), 'is_numeric'));
        $selectedTakers = array_map('intval', $selectedTakers);
        $itcUserId = (int) $request->session()->get('user_id', 0);

        if ($adminCode === '' || $testType === '') {
            return response()->json(['success' => false, 'message' => 'Data ujian tidak lengkap.', 'error' => true], 400);
        }
        if ($itcUserId <= 0) {
            return response()->json(['success' => false, 'message' => 'Sesi login tidak valid.', 'error' => true], 403);
        }
        if ($adminRecId <= 0 || $subAdminId <= 0) {
            return response()->json(['success' => false, 'message' => 'Data room monitoring tidak lengkap.', 'error' => true], 400);
        }
        if ($selectedTakers === []) {
            return response()->json(['success' => false, 'message' => 'Data peserta yang dipilih tidak valid.', 'error' => true], 400);
        }

        $access = $this->validateRoomAccess($itcUserId, $adminRecId, $subAdminId);
        if ($access !== true) {
            return $access;
        }

        $war = DB::connection('war')->getPdo();
        $testDateText = $this->crcTestDate($testDate);
        $keyLock = Nisn::shift(trim($adminCode), 3);
        $timerConfig = $this->crcTimerConfig($war, $testType);
        $takers = $this->crcTakers($war, $selectedTakers, $adminRecId, $subAdminId);

        if ($takers === []) {
            return response()->json(['success' => false, 'message' => 'Tidak ada data peserta yang ditemukan.', 'error' => true], 404);
        }
        if (count($takers) !== count(array_unique($selectedTakers))) {
            return response()->json(['success' => false, 'message' => 'Sebagian peserta yang dipilih tidak berada dalam room monitoring ini.', 'error' => true], 403);
        }

        $safeAdminCode = preg_replace('/[^A-Za-z0-9_\-]/', '', trim($adminCode));
        if ($safeAdminCode === '') {
            return response()->json(['success' => false, 'message' => 'Kode admin tidak valid untuk nama file.', 'error' => true], 400);
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'CRC_').'.zip';
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return response()->json(['success' => false, 'message' => 'Gagal membuat file ZIP pada sistem.', 'error' => true], 500);
        }

        $zip->addEmptyDir('CRC');
        $combinedCrc = '';
        foreach ($takers as $taker) {
            $crc = $this->buildCrcRecord($war, $taker, $timerConfig, $keyLock, $adminCode, $testDateText, $testType);
            if ($crc === null) {
                continue;
            }

            $combinedCrc .= $crc;
            $authorize = trim((string) ($taker['authorize'] ?? ''));
            $fileAdmin = substr(trim($adminCode), -3);
            $filename = preg_replace('/[^A-Za-z0-9_\-.]/', '', 'CBT'.$fileAdmin.$authorize.'.CRC');
            $zip->addFromString('CRC/'.$filename, $crc);
        }

        if ($combinedCrc === '') {
            $zip->close();
            @unlink($zipPath);

            return response()->json(['success' => false, 'message' => 'CRC gagal dibuat. Tidak ada peserta valid atau authorize kosong.', 'error' => true], 400);
        }

        $zip->addFromString('Rev-'.$safeAdminCode.'.CRC', $combinedCrc);
        $zip->close();

        if (! is_file($zipPath) || filesize($zipPath) <= 0) {
            @unlink($zipPath);

            return response()->json(['success' => false, 'message' => 'File ZIP gagal dibuat atau kosong.', 'error' => true], 500);
        }

        return response()->download($zipPath, 'CRC_'.$safeAdminCode.'.zip', [
            'Content-Type' => 'application/zip',
            'Content-Description' => 'File Transfer',
            'Content-Transfer-Encoding' => 'binary',
            'Expires' => '0',
            'Cache-Control' => 'must-revalidate',
            'Pragma' => 'public',
        ])->deleteFileAfterSend(true);
    }

    private function legacyUrl(string $file, array $query = []): string
    {
        $url = url('/modules/cbt_ops/test_watching/'.$file);

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    private function receiveIndividualCrc(Request $request, string $storageRoot, string $adminNo, string $testDate, string $type): JsonResponse
    {
        $files = $request->file('files', []);
        if ($files === []) {
            $files = $request->file('file', []);
        }
        $files = array_values(array_filter(is_array($files) ? $files : [$files], fn ($file): bool => $file instanceof UploadedFile));

        if ($files === []) {
            return response()->json(['success' => false, 'message' => 'File CRC belum dikirim.'], 400);
        }
        if (count($files) > 500) {
            return response()->json(['success' => false, 'message' => 'Maksimal 500 file .CRC sekali upload.'], 400);
        }
        if (array_sum(array_map(fn (UploadedFile $file): int => (int) $file->getSize(), $files)) > 10485760) {
            return response()->json(['success' => false, 'message' => 'Total ukuran file CRC maksimal 10MB sekali upload.'], 400);
        }

        $targetDir = $storageRoot.'/'.$adminNo.'/'.$testDate.'/CRC';
        if (! is_dir($targetDir) && ! @mkdir($targetDir, 0755, true) && ! is_dir($targetDir)) {
            return response()->json(['success' => false, 'message' => 'Gagal membuat folder: '.$targetDir], 500);
        }
        if (! is_writable($targetDir)) {
            return response()->json(['success' => false, 'message' => 'Folder tujuan tidak writable.'], 500);
        }

        $uploaded = [];
        $errors = [];
        foreach ($files as $file) {
            $originalName = basename($file->getClientOriginalName());
            $safeName = $this->safeReceiverName($originalName);

            if (! $file->isValid()) {
                $errors[] = ['file_name' => $originalName, 'message' => 'Upload gagal. Error: '.$file->getError()];

                continue;
            }
            if ($safeName === '' || strtolower(pathinfo($safeName, PATHINFO_EXTENSION)) !== 'crc') {
                $errors[] = ['file_name' => $originalName, 'message' => 'Format file harus .CRC.'];

                continue;
            }
            if ((int) $file->getSize() <= 0 || (int) $file->getSize() > 1048576) {
                $errors[] = ['file_name' => $originalName, 'message' => 'File kosong atau lebih dari 1MB.'];

                continue;
            }

            $file->move($targetDir, $safeName);
            $targetPath = $targetDir.'/'.$safeName;
            $relativePath = $testDate.'/CRC/'.$safeName;
            $uploaded[] = $this->receiverFilePayload(request(), $adminNo, $relativePath, 'crc_individual', $targetPath);
        }

        return response()->json([
            'success' => $uploaded !== [],
            'message' => count($uploaded).' file CRC individu berhasil disimpan'.($errors !== [] ? ', '.count($errors).' gagal.' : '.'),
            'type' => $type,
            'admin_no' => $adminNo,
            'uploaded_count' => count($uploaded),
            'failed_count' => count($errors),
            'files' => $uploaded,
            'errors' => $errors,
        ], $uploaded === [] ? 400 : 200);
    }

    private function extractReceiverCrcZip(UploadedFile $file, string $targetDir, string $adminNo, string $fileName): JsonResponse
    {
        if (! class_exists(ZipArchive::class)) {
            return response()->json(['success' => false, 'message' => 'Extension ZipArchive belum aktif di server receiver.'], 500);
        }

        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath()) !== true) {
            return response()->json(['success' => false, 'message' => 'File ZIP CRC tidak bisa dibuka.'], 400);
        }

        $extractedFiles = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = ltrim(str_replace('\\', '/', (string) $zip->getNameIndex($i)), '/');
            if ($entry === '' || str_contains($entry, '../') || str_starts_with($entry, '..')) {
                $zip->close();

                return response()->json(['success' => false, 'message' => 'ZIP berisi path tidak aman.'], 400);
            }
            if (str_ends_with($entry, '/')) {
                continue;
            }
            if (! preg_match('#^(Rev-[A-Za-z0-9_.-]+\.CRC|CRC/[A-Za-z0-9_.-]+\.CRC)$#i', $entry)) {
                $zip->close();

                return response()->json(['success' => false, 'message' => 'ZIP berisi file tidak sesuai format CRC: '.$entry], 400);
            }

            $contents = $zip->getFromIndex($i);
            if ($contents === false || $contents === '') {
                $zip->close();

                return response()->json(['success' => false, 'message' => 'File CRC dalam ZIP kosong atau gagal dibaca: '.$entry], 400);
            }

            $destination = $targetDir.'/'.$entry;
            $destinationDir = dirname($destination);
            if (! is_dir($destinationDir) && ! @mkdir($destinationDir, 0755, true) && ! is_dir($destinationDir)) {
                $zip->close();

                return response()->json(['success' => false, 'message' => 'Gagal membuat folder: '.$destinationDir], 500);
            }
            if (@file_put_contents($destination, $contents, LOCK_EX) === false) {
                $zip->close();

                return response()->json(['success' => false, 'message' => 'Gagal menyimpan file CRC: '.$entry], 500);
            }

            $extractedFiles[] = $entry;
        }

        $zip->close();
        if ($extractedFiles === []) {
            return response()->json(['success' => false, 'message' => 'ZIP tidak berisi file CRC.'], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'CRC berhasil diekstrak ke folder tanggal ujian.',
            'type' => 'crc',
            'admin_no' => $adminNo,
            'file_name' => $fileName,
            'file_count' => count($extractedFiles),
            'files' => $extractedFiles,
            'target_path' => $targetDir,
        ]);
    }

    private function downloadReceiverZip(string $storageRoot, string $adminNo): JsonResponse|BinaryFileResponse
    {
        if (! class_exists(ZipArchive::class)) {
            return response()->json(['success' => false, 'message' => 'Extension ZipArchive belum aktif di server receiver.'], 500);
        }

        $adminDir = $storageRoot.'/'.$adminNo;
        if (! is_dir($adminDir)) {
            return response()->json(['success' => false, 'message' => 'Folder admin tidak ditemukan.'], 404);
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'admin_crc_').'.zip';
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return response()->json(['success' => false, 'message' => 'Gagal membuat ZIP download.'], 500);
        }

        $fileCount = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($adminDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $absolutePath = $file->getPathname();
            $relativePath = $this->receiverSafeRelativePath(substr($absolutePath, strlen($adminDir) + 1));
            $zip->addFile($absolutePath, $relativePath);
            $fileCount++;
        }

        $zip->close();
        if ($fileCount === 0 || ! is_file($zipPath) || filesize($zipPath) <= 0) {
            @unlink($zipPath);

            return response()->json(['success' => false, 'message' => 'Folder admin tidak berisi file.'], 404);
        }

        return response()->download($zipPath, $adminNo.'.zip', [
            'Content-Type' => 'application/zip',
            'Content-Description' => 'File Transfer',
            'Content-Transfer-Encoding' => 'binary',
            'Cache-Control' => 'must-revalidate',
            'Pragma' => 'public',
        ])->deleteFileAfterSend(true);
    }

    private function receiverFilesByCategory(string $storageRoot, string $adminNo): array
    {
        $adminDir = $storageRoot.'/'.$adminNo;
        $filesByCategory = ['crc_individual' => [], 'crc_gabungan' => [], 'berita_acara' => [], 'dokumen_support' => []];
        if (! is_dir($adminDir)) {
            return $filesByCategory;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($adminDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $absolutePath = $file->getPathname();
            $relativePath = $this->receiverSafeRelativePath(substr($absolutePath, strlen($adminDir) + 1));
            $fileName = basename($relativePath);

            if (preg_match('#^(GrabCRC|[0-9]{8})/CRC/[^/]+\.CRC$#i', $relativePath)) {
                $filesByCategory['crc_individual'][] = $this->receiverFilePayload(request(), $adminNo, $relativePath, 'crc_individual', $absolutePath);
            } elseif (preg_match('#^(GrabCRC|[0-9]{8})/Rev-[^/]+\.CRC$#i', $relativePath)) {
                $filesByCategory['crc_gabungan'][] = $this->receiverFilePayload(request(), $adminNo, $relativePath, 'crc_gabungan', $absolutePath);
            } elseif (preg_match('#^[0-9]{8}/Documents/[^/]*BeritaAcara[^/]*\.pdf$#i', $relativePath) || (stripos($fileName, 'BeritaAcara') !== false && strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'pdf')) {
                $filesByCategory['berita_acara'][] = $this->receiverFilePayload(request(), $adminNo, $relativePath, 'berita_acara', $absolutePath);
            } elseif (preg_match('#^[0-9]{8}/Documents/[^/]+\.(xlsx|pdf|doc|docx|jpg|jpeg|png|zip|rar)$#i', $relativePath) || preg_match('#^Absensi/[^/]+\.(xlsx|pdf)$#i', $relativePath) || strpos($relativePath, '/') === false) {
                $filesByCategory['dokumen_support'][] = $this->receiverFilePayload(request(), $adminNo, $relativePath, 'dokumen_support', $absolutePath);
            }
        }

        return $filesByCategory;
    }

    private function validateReceiverFileType(string $type, string $extension): true|string
    {
        if ($type === 'crc' && $extension !== 'zip') {
            return 'Format file CRC harus .ZIP.';
        }
        if ($type === 'berita_acara' && $extension !== 'pdf') {
            return 'Format file Berita Acara harus .PDF.';
        }
        if ($type === 'attendance' && ! in_array($extension, ['xlsx', 'pdf'], true)) {
            return 'Format file absensi harus .XLSX atau .PDF.';
        }
        if ($type === 'filing' && ($extension === '' || in_array($extension, ['php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'com', 'scr', 'sh', 'bash', 'cgi', 'pl', 'py', 'js', 'html', 'htm', 'htaccess'], true))) {
            return 'Tipe file tidak diizinkan.';
        }

        return true;
    }

    private function receiverFilePayload(Request $request, string $adminNo, string $relativePath, string $category, string $absolutePath): array
    {
        $fileName = basename($relativePath);
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        return [
            'file_id' => null,
            'file_name' => $fileName,
            'file_type' => $extension,
            'file_category' => $category,
            'file_path' => $adminNo.'/'.$this->receiverSafeRelativePath($relativePath),
            'relative_path' => $this->receiverSafeRelativePath($relativePath),
            'uploaded_at' => is_file($absolutePath) ? date('Y-m-d H:i:s', (int) filemtime($absolutePath)) : '',
            'size' => is_file($absolutePath) ? (int) filesize($absolutePath) : 0,
            'download_url' => $this->receiverPublicUrl($request, $adminNo, $relativePath),
        ];
    }

    private function receiverPublicUrl(Request $request, string $adminNo, string $relativePath): string
    {
        $baseUrl = rtrim((string) env('CRC_PUBLIC_BASE_URL', ''), '/');
        if ($baseUrl === '') {
            $baseUrl = rtrim(dirname($request->url()), '/');
        }

        $path = $adminNo.'/'.$this->receiverSafeRelativePath($relativePath);

        return $baseUrl.'/'.implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    private function receiverSafeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path) ?? '';

        return ltrim($path, '/');
    }

    private function receiverDate(string $date): string
    {
        $digits = preg_replace('/[^0-9]/', '', $date) ?? '';
        if ($digits === '') {
            return '';
        }

        $parsed = strlen($digits) === 8 ? \DateTime::createFromFormat('Ymd', $digits) : \DateTime::createFromFormat('Ymd', date('Ymd', strtotime($date)));

        return $parsed instanceof \DateTime ? $parsed->format('Ymd') : '';
    }

    private function safeReceiverName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/\s+/', '_', $name) ?? '';
        $name = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $name) ?? '';

        return trim($name, '._-');
    }

    private function outboundFiles(string $storageRoot, string $adminNo): array
    {
        $files = [];
        foreach (scandir($storageRoot) ?: [] as $fileName) {
            if (! $this->isValidOutboundFileName($adminNo, $fileName)) {
                continue;
            }

            $path = $storageRoot.DIRECTORY_SEPARATOR.$fileName;
            if (! is_file($path)) {
                continue;
            }

            $files[] = [
                'file_id' => null,
                'file_name' => $fileName,
                'file_type' => 'zip',
                'file_category' => 'outbound',
                'uploaded_at' => date('Y-m-d H:i:s', (int) filemtime($path)),
                'size' => (int) filesize($path),
                'download_url' => $this->outboundPublicUrl($fileName),
            ];
        }

        usort($files, static fn (array $a, array $b): int => strcasecmp($a['file_name'], $b['file_name']));

        return $files;
    }

    private function crcUploadedFiles(Request $request): array
    {
        $files = $request->file('crc_files', []);
        if ($files === []) {
            $files = $request->file('crc_file', []);
        }

        return array_values(array_filter(is_array($files) ? $files : [$files], fn ($file): bool => $file instanceof UploadedFile));
    }

    private function crcTestDate(string $testDate): string
    {
        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $format) {
            $date = \DateTime::createFromFormat($format, $testDate);
            if ($date instanceof \DateTime) {
                return $date->format('Ymd');
            }
        }

        return date('Ymd');
    }

    private function crcTimerConfig(\PDO $war, string $testType): array
    {
        $stmt = $war->prepare('SELECT lrtype, nofansw, resetno FROM cbt_tbltimer WHERE testcd = ? ORDER BY lrtype');
        $stmt->execute([$testType]);
        $config = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $config[$row['lrtype']] = $row;
        }

        return $config;
    }

    private function crcTakers(\PDO $war, array $selectedTakers, int $adminRecId, int $subAdminId): array
    {
        $placeholders = implode(',', array_fill(0, count($selectedTakers), '?'));
        $stmt = $war->prepare("SELECT * FROM t3sTt4keR5 WHERE rec_id IN ({$placeholders}) AND admin_id = ? AND sub_adm_id = ?");
        $stmt->execute(array_merge($selectedTakers, [$adminRecId, $subAdminId]));

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function buildCrcRecord(\PDO $war, array $taker, array $timerConfig, string $keyLock, string $adminCode, string $testDateText, string $testType): ?string
    {
        $authorize = trim((string) ($taker['authorize'] ?? ''));
        if ($authorize === '') {
            return null;
        }

        $stmt = $war->prepare('SELECT lrtype, partno, nosoalori, shfansw FROM t3sT4n5wers WHERE ttaker_id = ? ORDER BY lrtype, partno, CAST(nosoalori AS UNSIGNED)');
        $stmt->execute([(int) $taker['rec_id']]);
        $answersByType = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $answer) {
            $answersByType[$answer['lrtype']][] = $answer;
        }

        $encryptedAnswers = '';
        $params = '';
        $typeCount = 0;
        $answerIndex = 0;
        foreach ($answersByType as $lrtype => $answers) {
            $typeCount++;
            $answerTotal = isset($timerConfig[$lrtype]) ? (int) $timerConfig[$lrtype]['nofansw'] : 0;
            $resetNo = isset($timerConfig[$lrtype]) ? (int) $timerConfig[$lrtype]['resetno'] : 0;
            $params .= $lrtype.substr((string) (1000 + $answerTotal), -3);
            if ($resetNo === 1) {
                $answerIndex = 0;
            }

            $answerText = '';
            foreach ($answers as $answer) {
                $answerIndex++;
                $questionNo = (int) $answer['nosoalori'];
                $choice = substr((string) $answer['shfansw'], 0, 1);
                if ($questionNo === $answerIndex) {
                    $answerText .= $choice;
                } elseif ($answerIndex < $questionNo) {
                    $answerText .= str_repeat(' ', $questionNo - $answerIndex - 1).$choice;
                    $answerIndex = $questionNo;
                }
            }

            $answerText = strlen($answerText) < $answerTotal
                ? $answerText.str_repeat(' ', $answerTotal - strlen($answerText))
                : substr($answerText, 0, $answerTotal);
            $encryptedAnswers .= Nisn::encrypt($answerText, $keyLock);
        }

        $encryptedAnswers = str_pad(substr($encryptedAnswers, 0, 200), 200, ' ');
        $params = str_pad(substr($typeCount.$params, 0, 13), 13, ' ');

        return ''
            .$this->fixedWidth($adminCode, 6)
            .$this->fixedWidth(Nisn::encrypt($authorize, $keyLock), 10)
            .$this->fixedWidth(Nisn::encrypt($testDateText, $keyLock), 8)
            .$this->fixedWidth($taker['idno'] ?? '', 20)
            .$this->fixedWidth($taker['regnm'] ?? '', 40)
            .$this->fixedWidth($this->fixedWidth((string) ($taker['dob'] ?? ''), 8), 8)
            .$this->fixedWidth($taker['countcd'] ?? '', 3)
            .$this->fixedWidth($taker['langcode'] ?? '', 3)
            .$this->fixedWidth($taker['sexmf'] ?? '', 1)
            .$this->fixedWidth(Nisn::encrypt($taker['questioner'] ?? '', $keyLock), 36)
            .$this->fixedWidth($taker['custom1'] ?? '', 3)
            .$this->fixedWidth($taker['custom2'] ?? '', 3)
            .$this->fixedWidth($taker['custom3'] ?? '', 3)
            .$encryptedAnswers
            .$this->fixedWidth($taker['groupcd'] ?? '', 5)
            .$this->fixedWidth($taker['email'] ?? '', 50)
            .$this->fixedWidth(! empty(trim((string) ($taker['macno'] ?? ''))) ? $taker['macno'] : trim((string) ($taker['email'] ?? '')), 17)
            .$this->fixedWidth(! empty(trim((string) ($taker['cmpnm'] ?? ''))) ? $taker['cmpnm'] : trim((string) ($taker['email'] ?? '')), 20)
            .$this->fixedWidth(Nisn::encrypt($params, $keyLock), 13)
            .substr($testType, 0, 1)."\r\n";
    }

    private function fixedWidth($value, int $length): string
    {
        return str_pad(substr((string) ($value ?? ''), 0, $length), $length, ' ', STR_PAD_RIGHT);
    }

    private function syncLegacyRequestSuperglobals(Request $request): void
    {
        $_SERVER['REQUEST_METHOD'] = strtoupper($request->method());
        $_SERVER['REQUEST_URI'] = $request->getRequestUri();
        $_GET = $request->query();
        $_POST = $request->isMethod('get') ? [] : $request->request->all();
        $_REQUEST = array_merge($_GET, $_POST);
    }

    private function monitoringTimerSync(Request $request, array $pageData): JsonResponse
    {
        $adminKeys = [];

        if ($request->filled('admin')) {
            $adminKeys[] = (string) $request->query('admin');
        }

        if ($adminKeys === [] && $request->filled('admin_rec_id') && $request->filled('sub_admin_id')) {
            $adminKeys[] = preg_replace('/[^0-9]/', '', (string) $request->query('admin_rec_id')).'|'.preg_replace('/[^0-9]/', '', (string) $request->query('sub_admin_id'));
        }

        $accessiblePairs = [];
        foreach (($pageData['filtered_admins'] ?? []) as $accessibleKey => $accessibleAdmin) {
            $accessiblePair = $this->monitoringDecodeAdminKeyToPair((string) $accessibleKey);
            if ($accessiblePair !== null) {
                $accessiblePairs[$accessiblePair[0].'|'.$accessiblePair[1]] = true;
            }
        }

        $pairs = [];
        foreach (array_unique($adminKeys) as $adminKey) {
            $pair = $this->monitoringDecodeAdminKeyToPair((string) $adminKey);
            if ($pair !== null && isset($accessiblePairs[$pair[0].'|'.$pair[1]])) {
                $pairs[] = $pair;
            }
        }

        if ($pairs === []) {
            return response()->json(['success' => false, 'message' => 'Admin ID tidak valid atau tidak ditugaskan ke akun ini']);
        }

        try {
            $items = [];
            $pdoWar = DB::connection('war')->getPdo();

            foreach ($pairs as [$adminId, $subId]) {
                $stmt = $pdoWar->prepare('SELECT authorize, statrec, ke_suspend, remindtm FROM t3sTt4keR5 WHERE admin_id = ? AND sub_adm_id = ?');
                $stmt->execute([$adminId, $subId]);

                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $remainingSeconds = $this->monitoringRemindtmToSeconds($row['remindtm'] ?? null);
                    $statrec = (string) ($row['statrec'] ?? '');
                    $keSuspend = (int) ($row['ke_suspend'] ?? 0);
                    $isFinished = $statrec === '7';
                    $isActive = ! $isFinished && $keSuspend !== 1 && in_array($statrec, ['3', '4', '5', '6'], true) && $remainingSeconds !== null && $remainingSeconds > 0;

                    $items[(string) $row['authorize']] = [
                        'authorize' => (string) $row['authorize'],
                        'statrec' => $statrec,
                        'ke_suspend' => $keSuspend,
                        'remindtm' => $row['remindtm'],
                        'remaining_seconds' => $remainingSeconds,
                        'active' => $isActive,
                        'finished' => $isFinished,
                        'status_text' => $this->monitoringStatusTextFromRow($statrec, $keSuspend),
                    ];
                }
            }

            return response()->json(['success' => true, 'items' => array_values($items)]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function monitoringRedirectForAssignedRoom(Request $request, array $pageData, string $currentMonitoringMode): ?RedirectResponse
    {
        if (! empty($pageData['is_in_room']) || ! $request->filled('date') || ! $request->filled('admin') || empty($pageData['filtered_admins']) || ! is_array($pageData['filtered_admins'])) {
            return null;
        }

        $requestedDate = date('Y-m-d', strtotime((string) $request->query('date')));
        $requestedAdmin = trim((string) $request->query('admin'));
        $requestedBatchQty = $request->query('batch_qty') !== null ? (int) $request->query('batch_qty') : null;
        $requestedBatchNo = trim((string) $request->query('batch_no', ''));
        $candidateKey = null;
        $fallbackKey = null;

        foreach ($pageData['filtered_admins'] as $key => $data) {
            $itemDate = ! empty($data['date']) ? date('Y-m-d', strtotime((string) $data['date'])) : '';
            $itemAdminNo = trim((string) ($data['admin_no'] ?? ''));
            $itemQty = isset($data['qty']) ? (int) $data['qty'] : null;
            $itemBatchNo = trim((string) ($data['batch_no'] ?? ''));

            if ((string) $key === $requestedAdmin) {
                $candidateKey = (string) $key;
                break;
            }

            if ($itemDate === $requestedDate && $itemAdminNo === $requestedAdmin) {
                $fallbackKey ??= (string) $key;

                if ($requestedBatchNo !== '' && $itemBatchNo === $requestedBatchNo) {
                    $candidateKey = (string) $key;
                    break;
                }

                if ($requestedBatchNo === '' && $requestedBatchQty !== null && $itemQty === $requestedBatchQty) {
                    $candidateKey = (string) $key;
                    break;
                }
            }
        }

        if ($candidateKey === null && $requestedBatchNo === '') {
            $candidateKey = $fallbackKey;
        }

        if ($candidateKey === null) {
            return null;
        }

        $route = $currentMonitoringMode === 'hybrid' ? 'cbt-ops.test-watching.monitoring-hybrid' : 'cbt-ops.test-watching.monitoring';

        return redirect()->route($route, [
            'date' => $requestedDate,
            'admin' => $candidateKey,
            'monitoring_mode' => $currentMonitoringMode,
        ]);
    }

    private function monitoringModeFromAdminData(array $adminData): string
    {
        if (array_key_exists('conn_type', $adminData)) {
            return (int) $adminData['conn_type'] === 2 ? 'hybrid' : 'online';
        }

        $mode = strtolower(trim((string) ($adminData['monitoring_mode'] ?? '')));

        return $mode === 'hybrid' ? 'hybrid' : 'online';
    }

    private function monitoringDecodeAdminKeyToPair(string $key): ?array
    {
        $key = trim($key);
        if ($key === '' || ! str_contains($key, '|')) {
            return null;
        }

        [$left, $subId] = explode('|', $key, 2);
        if (str_contains($left, '_')) {
            $parts = explode('_', $left);
            $left = end($parts);
        }

        $adminId = preg_replace('/[^0-9]/', '', (string) $left);
        $subId = preg_replace('/[^0-9]/', '', (string) $subId);

        if ($adminId === '' || $subId === '') {
            return null;
        }

        return [$adminId, $subId];
    }

    private function monitoringRemindtmToSeconds($value): ?int
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^(\d{1,3}):(\d{1,2})(?::(\d{1,2}))?$/', $raw, $m)) {
            if (isset($m[3]) && $m[3] !== '') {
                return ((int) $m[1] * 3600) + ((int) $m[2] * 60) + (int) $m[3];
            }

            return ((int) $m[1] * 60) + (int) $m[2];
        }

        if (is_numeric($raw)) {
            return max(0, (int) floor((float) $raw));
        }

        return null;
    }

    private function monitoringStatusTextFromRow($statrec, int $keSuspend = 0): string
    {
        $stat = trim((string) $statrec);

        if ($stat === '7' && $keSuspend === 1) {
            return 'Terminate';
        }

        if ($keSuspend === 1 && in_array($stat, ['3', '4', '5', '6'], true)) {
            return 'Suspend';
        }

        return match ($stat) {
            '0' => 'Ready to Enter',
            '1' => 'Ready to Test',
            '2' => 'Readiness Test',
            '3' => 'Questionnaire',
            '4' => 'Regulation',
            '5', '6' => 'On Test',
            '7' => 'End of Test',
            '8' => 'Submit Score',
            '9' => 'Completed',
            default => $stat === '' ? 'Unknown' : $stat.' - Unknown',
        };
    }

    private function crcDateFolder(string $date): string
    {
        $timestamp = strtotime($date);

        return $timestamp ? date('Ymd', $timestamp) : date('Ymd');
    }

    private function safeCrcName(\FtpStorage $ftp, string $name): string
    {
        $base = $ftp->safeFileName(pathinfo(basename($name), PATHINFO_FILENAME));

        return ($base !== '' ? $base : 'CRC_'.date('His')).'.CRC';
    }

    private function uploadCrcViaHttpReceiver(array $files, string $adminNo, string $dateFolder, string $uploadUrl): array
    {
        if (! function_exists('curl_init')) {
            throw new \RuntimeException('cURL tidak tersedia untuk upload HTTP receiver.');
        }

        $postFields = [
            'type' => 'crc_individual',
            'admin_no' => $adminNo,
            'test_date' => $dateFolder,
            'tanggal' => $dateFolder,
        ];
        foreach ($files as $index => $item) {
            $postFields['files['.$index.']'] = new \CURLFile($item['file']->getRealPath(), 'application/octet-stream', $item['safe_name']);
        }

        $headers = ['Accept: application/json'];
        $token = trim((string) env('CRC_HTTP_UPLOAD_TOKEN', ''));
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer '.$token;
        }

        $curl = curl_init($uploadUrl);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => max(30, (int) env('CRC_HTTP_UPLOAD_TIMEOUT', 120)),
            CURLOPT_FAILONERROR => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $response = curl_exec($curl);
        $errno = curl_errno($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($errno !== 0) {
            throw new \RuntimeException('cURL error upload HTTP receiver: '.$error);
        }

        $result = json_decode((string) $response, true);
        if ($status < 200 || $status >= 300 || ! is_array($result) || empty($result['success'])) {
            $message = is_array($result) ? ($result['message'] ?? 'Upload receiver gagal.') : substr(strip_tags((string) $response), 0, 200);
            throw new \RuntimeException('Server HTTP receiver error (HTTP '.$status.'): '.$message);
        }

        return $result;
    }

    private function spvName(int $itcUserId): ?string
    {
        $stmt = DB::connection('run')->getPdo()->prepare('SELECT spv_name FROM tad_supervisor WHERE itc_usr_id = ? LIMIT 1');
        $stmt->execute([$itcUserId]);
        $name = $stmt->fetchColumn();

        return $name ? (string) $name : null;
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
            'upload_timeout' => (int) env('FTP_UPLOAD_TIMEOUT', min(55, (int) env('FTP_TIMEOUT', 60))),
            'upload_direct_curl' => filter_var(env('FTP_UPLOAD_DIRECT_CURL', true), FILTER_VALIDATE_BOOLEAN),
            'upload_direct_curl_min_bytes' => (int) env('FTP_UPLOAD_DIRECT_CURL_MIN_BYTES', 0),
            'passive_mode' => filter_var(env('FTP_PASSIVE_MODE', true), FILTER_VALIDATE_BOOLEAN),
            'auto_detect_mode' => filter_var(env('FTP_AUTO_DETECT_MODE', false), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function safeOutboundAdminNo(string $adminNo): string
    {
        $adminNo = basename($adminNo);
        $adminNo = preg_replace('/\s+/', '_', $adminNo) ?? '';
        $adminNo = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $adminNo) ?? '';

        return trim($adminNo, '._-');
    }

    private function isValidOutboundFileName(string $adminNo, string $fileName): bool
    {
        $fileName = basename(str_replace('\\', '/', $fileName));
        if ($adminNo === '' || $fileName === '') {
            return false;
        }

        return preg_match('/^'.preg_quote($adminNo, '/').'-.*-DATA\.ZIP$/i', $fileName) === 1;
    }

    private function outboundPublicUrl(string $fileName): string
    {
        $baseUrl = rtrim((string) env('OUTBOUND_PUBLIC_BASE_URL', 'https://cbt.toeic.or.id/docs/CBT/OUTBOUND'), '/');

        return $baseUrl.'/'.rawurlencode($fileName);
    }

    private function participantPhotoIds(Request $request): array
    {
        $ids = [];
        $nisn = preg_replace('/[^A-Za-z0-9_-]/', '', trim((string) $request->query('nisn', '')));
        if ($nisn !== '') {
            $ids[] = $nisn;
        }

        foreach ((array) $request->query('id', []) as $rawId) {
            $id = preg_replace('/[^A-Za-z0-9_-]/', '', trim((string) $rawId));
            if ($id !== '' && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function validateRoomAccess(int $itcUserId, int $adminId, int $subAdminId): true|JsonResponse
    {
        $run = DB::connection('run')->getPdo();
        $stmt = $run->prepare('SELECT rec_id FROM tad_supervisor WHERE itc_usr_id = ? LIMIT 1');
        $stmt->execute([$itcUserId]);
        $spvRecId = (int) $stmt->fetchColumn();

        if ($spvRecId <= 0) {
            return response()->json(['success' => false, 'message' => 'Akses SPV tidak ditemukan'], 403);
        }

        $war = DB::connection('war')->getPdo();
        $stmt = $war->prepare('SELECT COUNT(*) FROM t3sT5ub4dm1n WHERE rec_id = ? AND admin_id = ? AND spv_recid = ?');
        $stmt->execute([$subAdminId, $adminId, $spvRecId]);

        if ((int) $stmt->fetchColumn() <= 0) {
            return response()->json(['success' => false, 'message' => 'Anda tidak ditugaskan untuk room monitoring ini'], 403);
        }

        return true;
    }

    private function defaultPhotoResponse(): Response|BinaryFileResponse
    {
        $defaultPhoto = base_path('assets/personal/nopicture.png');
        if (is_file($defaultPhoto)) {
            return response()->file($defaultPhoto, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'public, max-age=3600',
            ]);
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="200" height="200" fill="#f3f4f6"/><circle cx="100" cy="80" r="30" fill="#d1d5db"/><path d="M40 180 Q100 130 160 180" fill="#d1d5db"/></svg>';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function fetchPublicParticipantPhoto(array $ids, array $extensions, string $cacheDir): ?Response
    {
        $publicBase = rtrim((string) env('PARTICIPANT_PHOTO_PUBLIC_URL', ''), '/');
        if ($publicBase === '') {
            return null;
        }

        $publicBase .= '/nisn/';
        foreach ($ids as $id) {
            foreach ($extensions as $extension) {
                $url = $publicBase.rawurlencode($id).'.'.$extension;
                $image = $this->fetchRemoteImage($url);
                if ($image === null) {
                    continue;
                }

                @file_put_contents($cacheDir.'/'.$id.'.'.$extension, $image);

                return response($image, 200, [
                    'Content-Type' => $this->imageMime($extension),
                    'Cache-Control' => 'public, max-age=86400',
                ]);
            }
        }

        return null;
    }

    private function fetchRemoteImage(string $url): ?string
    {
        if (! function_exists('curl_init')) {
            return null;
        }

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return $status === 200 && is_string($body) && $body !== '' ? $body : null;
    }

    private function imageMime(string $extension): string
    {
        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }
}
