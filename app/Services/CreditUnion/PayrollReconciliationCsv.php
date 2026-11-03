<?php

namespace App\Services\CreditUnion;

use Generator;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class PayrollReconciliationCsv
{
    private const MEMBER_HEADER = ['periode', 'perusahaan', 'id_anggota', 'nama', 'simpanan', 'pokok', 'bunga_biaya', 'lunas_sebelum', 'payroll', 'posting', 'catatan'];

    private const BANK_HEADER = ['periode', 'perusahaan', 'referensi', 'nominal', 'bukti'];

    public const MAX_ROWS = 5000;

    /** Yield only records for this export, in the input-column order of the Excel template. */
    public function rows(UploadedFile $file, string $field, string $period, string $company): Generator
    {
        $isMember = $field === 'anggota';
        $expected = $isMember ? self::MEMBER_HEADER : self::BANK_HEADER;
        $stream = fopen($file->getPathname(), 'rb');
        $seen = [];
        $totals = [];
        $fail = static function (string $reason) use ($field): never {
            throw ValidationException::withMessages([$field => "CSV {$field}: {$reason}"]);
        };

        try {
            $header = fgetcsv($stream, null, ';', '"', '');
            if ($header !== false) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
            }
            if ($header !== $expected) {
                $fail('judul atau urutan kolom berbeda. Gunakan contoh CSV UTF-8 dengan pemisah titik koma.');
            }

            $record = 1;
            while (($row = fgetcsv($stream, null, ';', '"', '')) !== false) {
                $record++;
                if ($row === [null]) {
                    continue;
                }
                if (count($row) !== count($expected) || ! CreditUnionPeriod::isValid($row[0]) || ! preg_match('/^[a-z0-9]{3}$/iD', $row[1])) {
                    $fail("periksa kolom, periode, dan kode perusahaan pada rekaman {$record}.");
                }
                if ($row[0] !== $period || strcasecmp($row[1], $company) !== 0) {
                    continue;
                }
                $id = strtoupper($row[2]);
                if (! preg_match('~^[A-Z0-9][A-Z0-9._/-]{0,79}$~D', $id) || isset($seen[$id])) {
                    $fail("ID atau referensi tidak valid atau berulang pada rekaman {$record}.");
                }
                if (count($seen) >= self::MAX_ROWS) {
                    $fail('jumlah rekaman terpilih melebihi '.self::MAX_ROWS.' baris per berkas/sheet. Berkas belum diproses.');
                }
                $seen[$id] = true;
                $textColumns = $isMember ? [3, 10] : [4];
                foreach ($textColumns as $col) {
                    if (! mb_check_encoding($row[$col], 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $row[$col]) || mb_strlen($row[$col]) > 32767) {
                        $fail("teks tidak valid pada rekaman {$record}.");
                    }
                }
                if (trim($row[$isMember ? 3 : 4]) === '') {
                    $fail("nama anggota atau rujukan bukti wajib diisi pada rekaman {$record}.");
                }
                foreach ($isMember ? range(4, 9) : [3] as $col) {
                    if (! preg_match($isMember ? '/^\d{1,14}$/D' : '/^-?\d{1,14}$/D', $row[$col])) {
                        $fail("kolom {$expected[$col]} pada rekaman {$record} harus berisi rupiah bulat tanpa pemisah.");
                    }
                    $row[$col] = (int) $row[$col];
                }
                $net = $isMember ? $row[4] + $row[5] + $row[6] - $row[7] : abs($row[3]);
                if ($net < 0) {
                    $fail("pelunasan melebihi tagihan pada rekaman {$record}.");
                }
                foreach ($isMember ? [$net, $row[8], $row[9]] : [$net] as $i => $amount) {
                    $totals[$i] = ($totals[$i] ?? 0) + $amount;
                    if ($totals[$i] > 999999999999999) {
                        $fail('total melebihi presisi rupiah bulat Excel.');
                    }
                }
                $row[2] = $id;
                yield array_slice($row, 2);
            }
            if ($seen === []) {
                $fail('tidak ada data untuk periode dan perusahaan yang dipilih.');
            }
        } finally {
            fclose($stream);
        }
    }
}
