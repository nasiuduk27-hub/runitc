<?php

namespace App\Services\FilingSystem;

use ZipArchive;

class ZipInspectionService
{
    private const MAX_LISTED_FILES = 100;

    private const MAX_SCAN_FILES = 1000;

    private array $blockedExtensions = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
        'exe', 'sh', 'bat', 'cmd', 'js', 'jar', 'com', 'scr', 'msi', 'dll', 'vbs', 'ps1',
    ];

    private array $typeMapping = [
        'pdf' => 'document',
        'doc' => 'document',
        'docx' => 'document',
        'odt' => 'document',
        'xls' => 'spreadsheet',
        'xlsx' => 'spreadsheet',
        'csv' => 'spreadsheet',
        'ods' => 'spreadsheet',
        'txt' => 'text',
        'md' => 'text',
        'rtf' => 'text',
        'jpg' => 'image',
        'jpeg' => 'image',
        'png' => 'image',
        'gif' => 'image',
        'webp' => 'image',
        'ppt' => 'presentation',
        'pptx' => 'presentation',
        'odp' => 'presentation',
        'zip' => 'archive',
        'rar' => 'archive',
        '7z' => 'archive',
    ];

    public function isDangerousExtension(string $extension): bool
    {
        return in_array(strtolower($extension), $this->blockedExtensions, true);
    }

    public function hasPathTraversal(string $filename): bool
    {
        // Traversal patterns: ../, ..\, start with /, start with \, or absolute paths like C:\
        if (strpos($filename, '../') !== false) {
            return true;
        }
        if (strpos($filename, '..\\') !== false) {
            return true;
        }
        if (strpos($filename, '/') === 0) {
            return true;
        }
        if (strpos($filename, '\\') === 0) {
            return true;
        }
        if (preg_match('/^[a-zA-Z]:\\\\/', $filename)) {
            return true;
        }

        return false;
    }

    public function detectFileTypeFromExtensions(array $extensions): string
    {
        if (empty($extensions)) {
            return 'mixed';
        }
        $counts = array_count_values($extensions);
        arsort($counts);
        $mostFrequent = key($counts);

        return $this->typeMapping[$mostFrequent] ?? 'other';
    }

    public function summarizeExtensions(array $files): array
    {
        $exts = [];
        foreach ($files as $f) {
            if (! empty($f['extension'])) {
                $e = strtolower($f['extension']);
                $exts[$e] = ($exts[$e] ?? 0) + 1;
            }
        }
        arsort($exts);

        return $exts;
    }

    public function inspect(string $zipPath): array
    {
        if (! file_exists($zipPath) || ! is_readable($zipPath)) {
            return [
                'success' => false,
                'message' => 'File fisik ZIP tidak dapat dibaca dari temporary storage.',
                'warnings' => ['missing_physical_file'],
            ];
        }

        $zip = new ZipArchive;
        $res = $zip->open($zipPath);

        if ($res !== true) {
            return [
                'success' => false,
                'message' => 'ZIP cannot be opened. File may be corrupted or encrypted.',
                'warnings' => ['invalid_zip'],
            ];
        }

        $fileCount = $zip->numFiles;
        $totalCompressed = 0;
        $totalUncompressed = 0;
        $files = [];
        $globalWarnings = [];
        $rawExtensions = [];

        // Check if zip has too many files to scan
        $scanLimit = min($fileCount, self::MAX_SCAN_FILES);
        if ($fileCount > self::MAX_SCAN_FILES) {
            $globalWarnings[] = 'Jumlah file melebihi batas scan aman ('.self::MAX_SCAN_FILES.'). Sebagian file mungkin tidak terdeteksi.';
        }

        for ($i = 0; $i < $scanLimit; $i++) {
            $stat = $zip->statIndex($i);

            // Skip directory entries usually ending with /
            if (substr($stat['name'], -1) === '/') {
                continue;
            }

            $name = $stat['name'];
            $compSize = $stat['comp_size'];
            $uncompSize = $stat['size'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            $totalCompressed += $compSize;
            $totalUncompressed += $uncompSize;
            if ($ext) {
                $rawExtensions[] = $ext;
            }

            $fileWarnings = [];

            if ($this->hasPathTraversal($name)) {
                $fileWarnings[] = 'Path Traversal';
                if (! in_array('Path Traversal Detected', $globalWarnings)) {
                    $globalWarnings[] = 'Path Traversal Detected';
                }
            }

            if ($this->isDangerousExtension($ext)) {
                $fileWarnings[] = 'Dangerous Extension';
                if (! in_array('Dangerous Extension Detected', $globalWarnings)) {
                    $globalWarnings[] = 'Dangerous Extension Detected';
                }
            }

            // Record only up to MAX_LISTED_FILES
            if (count($files) < self::MAX_LISTED_FILES) {
                $files[] = [
                    'name' => $name,
                    'extension' => $ext,
                    'compressed_size' => $compSize,
                    'uncompressed_size' => $uncompSize,
                    'warnings' => $fileWarnings,
                ];
            }
        }

        $zip->close();

        return [
            'success' => true,
            'message' => 'ZIP inspected successfully.',
            'file_count' => $fileCount,
            'listed_count' => count($files),
            'total_compressed_size' => $totalCompressed,
            'total_uncompressed_size' => $totalUncompressed,
            'detected_file_type' => $this->detectFileTypeFromExtensions($rawExtensions),
            'extensions' => $this->summarizeExtensions($files),
            'warnings' => $globalWarnings,
            'files' => $files,
        ];
    }
}
