<?php

namespace App\Services\FilingSystem;

class FilingLinkService
{
    /** Link hanya pintu masuk; pembuka tetap harus punya hak. */
    public const MODE_RIGHTS = 1;

    /** Token adalah haknya; password opsional. */
    public const MODE_TOKEN = 2;

    /** Batas gagal password per session per link (throttle sederhana). */
    public const PASSWORD_MAX_ATTEMPTS = 10;

    /**
     * Token mentah: 32 byte CSPRNG, base64url tanpa padding (43 karakter).
     * Token mentah hanya ditampilkan sekali ke pembuatnya.
     */
    public function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * Hash penyimpanan sha256 hex (64 char), mengikuti pola share_code_hash RFS.
     * Token berentropi penuh, jadi sha256 cukup (bukan password_hash).
     */
    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Preview aman untuk daftar tautan (kolom share_code_preview maks 20 char).
     */
    public function previewToken(string $token): string
    {
        return 'LNK-****-'.substr($token, -4);
    }

    /**
     * Gerbang format di route: 43 karakter base64url.
     */
    public function isTokenFormat(string $token): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_-]{43}$/', $token);
    }

    /**
     * Mode link; nilai tak dikenal/missing diperlakukan mode 1 (paling aman).
     */
    public function modeOf(array $share): int
    {
        return ((int) ($share['link_mode'] ?? self::MODE_RIGHTS)) === self::MODE_TOKEN
            ? self::MODE_TOKEN
            : self::MODE_RIGHTS;
    }

    public function isExpired(array $share, ?int $now = null): bool
    {
        if (empty($share['expired_at'])) {
            return false;
        }

        return strtotime((string) $share['expired_at']) < ($now ?? time());
    }

    public function isQuotaReached(array $share): bool
    {
        $max = (int) ($share['max_access'] ?? 0);

        return $max > 0 && (int) ($share['access_count'] ?? 0) >= $max;
    }

    public function isRevoked(array $share): bool
    {
        return (int) ($share['is_active'] ?? 0) === 0 || ! empty($share['revoked_at']);
    }

    public function allowsDownload(array $share): bool
    {
        return (int) ($share['allow_download'] ?? 0) === 1;
    }

    public function passwordAttemptsKey(string $tokenHash): string
    {
        return 'link_pw_fail_'.$tokenHash;
    }

    public function isPasswordThrottled(int $failCount): bool
    {
        return $failCount >= self::PASSWORD_MAX_ATTEMPTS;
    }
}
