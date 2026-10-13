<?php

namespace App\Services\FilingSystem;

use ArrayAccess;

/**
 * Aturan himpunan share per-file (file_shareto).
 *
 * Modal izin/bagikan mengatur berbagi per-file; keanggotaan grup (share_cat=5)
 * diatur di halaman grup. Keduanya tidak saling menimpa: baris grup yang sudah
 * ada dipertahankan apa adanya melewati proses simpan modal.
 */
class FilingShareRules
{
    public const GROUP_CAT = 5;

    /**
     * Gabungkan aturan dari modal dengan baris grup yang sudah ada.
     *
     * - Baris cat 5 dari $submitted dibuang (modal tidak mengelolanya).
     * - Baris cat 5 dari $existing dipertahankan (ditambahkan bila belum ada).
     * - Baris non-grup dari $existing tidak dihidupkan kembali.
     *
     * Membaca hanya share_cat + othercode; bentuk baris (array/objek, kolom
     * ekstra) lain diabaikan.
     *
     * @param  iterable<int, mixed>  $submitted
     * @param  iterable<int, mixed>  $existing
     * @return array<int, array{share_cat: int, othercode: string}>
     */
    public static function mergePreservingGroupShares(iterable $submitted, iterable $existing, int $groupCat = self::GROUP_CAT): array
    {
        $out = [];
        $seen = [];

        $add = function (int $cat, string $value) use (&$out, &$seen): void {
            if ($value === '') {
                return;
            }
            $key = $cat.'|'.$value;
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $out[] = ['share_cat' => $cat, 'othercode' => $value];
        };

        foreach ($submitted as $rule) {
            $cat = (int) self::read($rule, 'share_cat');
            if ($cat === $groupCat) {
                continue;
            }
            $add($cat, self::valueOf($rule));
        }

        foreach ($existing as $rule) {
            if ((int) self::read($rule, 'share_cat') !== $groupCat) {
                continue;
            }
            $add($groupCat, self::valueOf($rule));
        }

        return $out;
    }

    private static function valueOf(mixed $row): string
    {
        foreach (['othercode', 'value', 'access_value'] as $key) {
            $value = self::read($row, $key);
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return '';
    }

    private static function read(mixed $row, string $key): mixed
    {
        if (is_array($row)) {
            return $row[$key] ?? null;
        }
        if ($row instanceof ArrayAccess) {
            return $row[$key] ?? null;
        }
        if (is_object($row)) {
            return $row->{$key} ?? null;
        }

        return null;
    }
}
