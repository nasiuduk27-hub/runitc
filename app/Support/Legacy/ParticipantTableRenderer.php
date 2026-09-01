<?php

namespace App\Support\Legacy;

class ParticipantTableRenderer
{
    public static function getTimerText(int $seconds, bool $isFinishedStatus, bool $isNotStartedStatus): string
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

    public static function isFinishedStatus(string $status, string $statusText = ''): bool
    {
        return in_array($status, ['7', '8', '9', 'c'], true)
        || str_contains($statusText, 'selesai')
        || str_contains($statusText, 'finished')
        || str_contains($statusText, 'terminate');
    }

    public static function isNotStartedStatus(string $status, string $statusText = ''): bool
    {
        return in_array($status, ['0', '1', '2', '3', ''], true)
        || str_contains($statusText, 'belum')
        || str_contains($statusText, 'readiness')
        || str_contains($statusText, 'ready to enter');
    }

    public static function getSessionLabel(array $p): string
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

    public static function getParticipantPhotoUrl(array $p): string
    {
        $defaultPhoto = url('/assets/personal/nopicture.png');

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
        $photoPublicUrl = (string) config('runitc.participant_photo_public_url', '');
        if ($nisn !== '' && $photoPublicUrl !== '') {
            return rtrim($photoPublicUrl, '/').'/nisn/'.rawurlencode($nisn).'.jpg';
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

        if (! empty($candidates)) {
            $proxyUrl = url('/modules/cbt_ops/test_watching/participant_photo');
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

        if ($participantId !== '') {
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
                    $fullPath = base_path().$relativePath;

                    if (file_exists($fullPath)) {
                        return url($relativePath).'?v='.filemtime($fullPath);
                    }
                }
            }
        }

        return $defaultPhoto;
    }
}
