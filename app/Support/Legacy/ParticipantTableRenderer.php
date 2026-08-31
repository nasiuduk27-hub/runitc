<?php

namespace App\Support\Legacy;

class ParticipantTableRenderer
{
    public static function render(array $participants, bool $isFinished, int $totalTiming): string
    {
        ob_start();

        if (empty($participants)) {
            echo '<tr id="emptyRow"><td colspan="8" class="px-6 py-10 text-center text-gray-400 text-sm italic">Belum ada peserta yang masuk ujian.</td></tr>';

            return ob_get_clean();
        }

        foreach ($participants as $p) {
            $statusRaw = strtolower(trim((string) ($p['statrec'] ?? $p['status'] ?? '')));
            $statusTextRaw = strtolower(trim((string) ($p['status_text'] ?? $p['q_status'] ?? '')));

            $isFinishedStatus = $isFinished || self::isFinishedStatus($statusRaw, $statusTextRaw);
            $isNotStartedStatus = self::isNotStartedStatus($statusRaw, $statusTextRaw);

            $displayProgressPct = (int) ($p['progress_pct'] ?? 0);
            $displayAnsFilled = (int) ($p['ans_filled'] ?? 0);
            $displayAnsTotal = (int) ($p['ans_total'] ?? 0);

            if ($statusRaw === '7' && (int) ($p['ke_suspend'] ?? 0) !== 1 && $displayAnsTotal > 0) {
                $displayAnsFilled = $displayAnsTotal;
                $displayProgressPct = 100;
            }

            $remainingSeconds = max(0, (int) ($p['remaining_seconds'] ?? 0));
            $isTimerActive = ($remainingSeconds > 0 && ! $isFinishedStatus && ! $isNotStartedStatus);
            $timerText = self::getTimerText($remainingSeconds, $isFinishedStatus, $isNotStartedStatus);

            $sessionLabel = self::getSessionLabel($p);
            $photoUrl = self::getParticipantPhotoUrl($p);
            ?>
            <tr class="participant-row"
                data-rec-id="<?= htmlspecialchars($p['rec_id'] ?? '') ?>"
                data-auth-id="<?= htmlspecialchars($p['id'] ?? '') ?>"
                data-name="<?= htmlspecialchars($p['name'] ?? '') ?>"
                data-status="<?= htmlspecialchars($statusRaw) ?>"
                data-statrec="<?= htmlspecialchars($statusRaw) ?>"
                data-status-text="<?= htmlspecialchars($p['status_text'] ?? $p['q_status'] ?? '') ?>"
                data-ke-suspend="<?= htmlspecialchars($p['ke_suspend'] ?? 0) ?>"
                data-participant-json="<?= htmlspecialchars(json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>"
                data-photo-url="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>">
                
                <td class="px-4 py-3 text-center"><?= ! empty($p['is_online']) ? '🟢' : '⚪' ?></td>

                <td class="px-4 py-4">
                    <button type="button" onclick="openParticipantDetailModal(this)" class="text-left group">
                        <?php if (empty(trim($p['name'] ?? ''))) { ?>
                            <div class="text-base font-semibold text-gray-800 uppercase group-hover:text-brand-primary group-hover:underline"><?= htmlspecialchars($p['id'] ?? '') ?></div>
                            <div class="text-[10px] text-blue-500 mt-1 opacity-0 group-hover:opacity-100 transition">Klik untuk lihat detail</div>
                        <?php } else { ?>
                            <div class="text-base font-semibold text-gray-800 uppercase group-hover:text-brand-primary group-hover:underline"><?= htmlspecialchars($p['name'] ?? '') ?></div>
                            <div class="text-base text-gray-500 mt-0.5 font-mono group-hover:text-brand-primary"><?= htmlspecialchars($p['id'] ?? '') ?></div>
                            <div class="text-[10px] text-blue-500 mt-1 opacity-0 group-hover:opacity-100 transition">Klik untuk lihat detail</div>
                        <?php } ?>
                    </button>
                </td>

                <td class="px-4 py-4 text-center">Part <?= htmlspecialchars($p['partno'] ?? '') ?></td>
                <td class="px-4 py-4 text-center"><span class="<?= htmlspecialchars($p['q_color'] ?? '') ?>"><?= htmlspecialchars($p['q_status'] ?? '') ?></span></td>

                <td class="px-4 py-4">
                    <div class="flex justify-between text-sm mb-1.5">
                        <span class="font-bold text-gray-700"><?= max(0, min(100, $displayProgressPct)) ?>%</span>
                        <span class="text-gray-500">Soal: <b><?= $displayAnsFilled ?></b>/<?= $displayAnsTotal ?></span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-4 border border-gray-200">
                        <div class="bg-brand-primary h-3 rounded-full" style="width: <?= max(0, min(100, $displayProgressPct)) ?>%"></div>
                    </div>
                </td>

                <td class="px-4 py-3 text-center">
                    <span class="timer-countdown timer-wrapper inline-flex items-center justify-center rounded-md bg-blue-50 text-blue-700 font-semibold"
                          data-active="<?= $isTimerActive ? 'true' : 'false' ?>"
                          data-finished="<?= $isFinishedStatus ? 'true' : 'false' ?>"
                          data-not-started="<?= $isNotStartedStatus ? 'true' : 'false' ?>"
                          data-remaining-seconds="<?= $remainingSeconds ?>"
                          data-session="<?= htmlspecialchars($sessionLabel) ?>">
                        <?php if ($isTimerActive && ! in_array($timerText, ['Selesai', 'Belum Mulai', '-'])) { ?>
                            <span class="timer-session-label <?= $sessionLabel === 'R' ? 'reading' : 'listening' ?>"><?= htmlspecialchars($sessionLabel) ?></span>
                        <?php } ?>
                        <span class="timer-value"><?= htmlspecialchars($timerText) ?></span>
                    </span>
                </td>

                <td class="px-4 py-4 text-center"><?= StatusHelper::getStatusBadge($statusRaw, $p['ke_suspend'] ?? '') ?></td>

                <td class="px-4 py-4 text-center">
                    <div class="flex justify-center space-x-1.5">
                        <button onclick="controlTimer('play','<?= htmlspecialchars($p['id'] ?? '') ?>')" class="text-green-600 bg-green-50 h-9 w-9 text-sm rounded"><i class="fas fa-play"></i></button>
                        <button onclick="controlTimer('pause','<?= htmlspecialchars($p['id'] ?? '') ?>')" class="text-yellow-600 bg-yellow-50 h-9 w-9 text-sm rounded"><i class="fas fa-pause"></i></button>
                        <button onclick="controlTimer('stop','<?= htmlspecialchars($p['id'] ?? '') ?>')" class="text-red-500 bg-red-50 h-9 w-9 text-sm rounded"><i class="fas fa-stop"></i></button>
                    </div>
                </td>
            </tr>
            <?php
        }

        return ob_get_clean();
    }

    private static function getTimerText(int $seconds, bool $isFinishedStatus, bool $isNotStartedStatus): string
    {
        if ($isFinishedStatus) {
            return 'Selesai';
        }

        if ($isNotStartedStatus || $seconds <= 0) {
            return 'Belum Mulai';
        }

        return self::formatRemainingTime($seconds);
    }

    private static function formatRemainingTime(int $seconds): string
    {
        $seconds = max(0, $seconds);

        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }

    private static function isFinishedStatus(string $status, string $statusText = ''): bool
    {
        return in_array($status, ['7', '8', '9', 'c'], true)
        || str_contains($statusText, 'selesai')
        || str_contains($statusText, 'finished')
        || str_contains($statusText, 'terminate');
    }

    private static function isNotStartedStatus(string $status, string $statusText = ''): bool
    {
        return in_array($status, ['0', '1', '2', '3', ''], true)
        || str_contains($statusText, 'belum')
        || str_contains($statusText, 'readiness')
        || str_contains($statusText, 'ready to enter');
    }

    private static function getSessionLabel(array $p): string
    {
        /*
         * Prioritas utama: pakai field dari query kalau ada.
         * Contoh alias query yang bisa dipakai:
         * CASE WHEN partno <= 4 THEN 'L' ELSE 'R' END AS timer_part
         */
        $rawSession = strtoupper(trim((string) (
            $p['timer_part'] ?? $p['session_part'] ?? $p['current_session'] ?? $p['part'] ?? ''
        )));

        if ($rawSession === 'L' || str_contains($rawSession, 'LISTENING')) {
            return 'L';
        }

        if ($rawSession === 'R' || str_contains($rawSession, 'READING')) {
            return 'R';
        }

        $partNo = (int) ($p['partno'] ?? 0);

        if ($partNo >= 1 && $partNo <= 4) {
            return 'L';
        }

        if ($partNo >= 5 && $partNo <= 7) {
            return 'R';
        }

        return 'L';
    }

    private static function getParticipantPhotoUrl(array $p): string
    {
        $defaultPhoto = defined('BASE_URL')
            ? BASE_URL.'/assets/personal/nopicture.png'
            : 'assets/personal/nopicture.png';

        if (! empty($p['photo_url']) && preg_match('/^https?:\/\//i', $p['photo_url'])) {
            return trim($p['photo_url']);
        }

        /*
          * Ekstrak NISN dari data peserta.
          */
        $nisn = trim((string) ($p['idno'] ?? $p['nisn'] ?? $p['id_number'] ?? ''));
        $nisn = preg_replace('/[^A-Za-z0-9_-]/', '', $nisn);

        /*
          * PRIORITAS 1: Public HTTP URL langsung (browser handle 404 sendiri)
          */
        if ($nisn !== '' && defined('PARTICIPANT_PHOTO_PUBLIC_URL')) {
            return rtrim(PARTICIPANT_PHOTO_PUBLIC_URL, '/').'/nisn/'.rawurlencode($nisn).'.jpg';
        }

        /*
          * PRIORITAS 2: Proxy participant_photo.php (fallback)
          */
        $candidates = [];

        if ($nisn !== '') {
            $candidates[] = $nisn;
        }

        $recId = trim((string) ($p['rec_id'] ?? ''));
        $recId = preg_replace('/[^A-Za-z0-9_-]/', '', $recId);
        if ($recId !== '' && $recId !== $nisn) {
            $candidates[] = $recId;
        }

        $authId = trim((string) ($p['id'] ?? $p['authorize'] ?? ''));
        $authId = preg_replace('/[^A-Za-z0-9_-]/', '', $authId);
        if ($authId !== '' && $authId !== '-' && $authId !== $nisn && $authId !== $recId) {
            $candidates[] = $authId;
        }

        if (! empty($candidates) && defined('BASE_URL')) {
            $proxyUrl = rtrim(BASE_URL, '/').'/modules/cbt_ops/test_watching/participant_photo';
            $params = 'nisn='.rawurlencode($candidates[0]);
            for ($i = 1, $n = count($candidates); $i < $n; $i++) {
                $params .= '&id[]='.rawurlencode($candidates[$i]);
            }

            return $proxyUrl.'?'.$params;
        }

        /*
          * PRIORITAS 3: File lokal
          */
        $participantId = $p['id'] ?? $p['authorize'] ?? $p['auth_id'] ?? $p['std_id'] ?? $p['noid'] ?? '';
        $participantId = trim((string) $participantId);

        if ($participantId !== '' && defined('BASE_PATH') && defined('BASE_URL')) {
            $photoFolders = [
                '/assets/participants/',
                '/assets/photos/',
                '/assets/student_photos/',
                '/assets/personal/',
            ];

            $extensions = ['jpg', 'jpeg', 'png', 'gif'];

            foreach ($photoFolders as $folder) {
                foreach ($extensions as $ext) {
                    $relativePath = $folder.$participantId.'.'.$ext;
                    $fullPath = BASE_PATH.$relativePath;

                    if (file_exists($fullPath)) {
                        return rtrim(BASE_URL, '/').$relativePath.'?v='.filemtime($fullPath);
                    }
                }
            }
        }

        return $defaultPhoto;
    }
}
