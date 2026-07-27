<?php
// File: modules/cbt_ops/filing_system/services/ShareCodeService.php

class ShareCodeService
{
    /**
     * Generate format: RFS-XXXX-XXXX
     */
    public function generateShareCode(): string
    {
        $part1 = strtoupper(bin2hex(random_bytes(2)));
        $part2 = strtoupper(bin2hex(random_bytes(2)));
        return 'RFS-' . $part1 . '-' . $part2;
    }

    /**
     * Hash menggunakan SHA256
     */
    public function hashShareCode(string $code): string
    {
        return hash('sha256', $code);
    }

    /**
     * Mask code, contoh: RFS-****-X91P
     */
    public function maskShareCode(string $code): string
    {
        $parts = explode('-', $code);
        if (count($parts) === 3) {
            return $parts[0] . '-****-' . $parts[2];
        }
        return '****';
    }

    /**
     * Validasi format share code
     */
    public function validateShareCodeFormat(string $code): bool
    {
        return (bool) preg_match('/^RFS-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $code);
    }
}
