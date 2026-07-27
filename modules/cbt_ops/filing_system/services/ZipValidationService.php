<?php
// File: modules/cbt_ops/filing_system/services/ZipValidationService.php

class ZipValidationService
{
    private array $blockedExtensions = ['php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'sh', 'bash', 'cgi', 'pl', 'py', 'js', 'html', 'htm', 'htaccess'];
    
    /**
     * Mapping ekstensi ke kategori detected_file_type (Sesuai PRD 7.2)
     */
    private array $typeMapping = [
        'pdf'  => 'document',
        'doc'  => 'document',
        'docx' => 'document',
        'odt'  => 'document',
        'xls'  => 'spreadsheet',
        'xlsx' => 'spreadsheet',
        'csv'  => 'spreadsheet',
        'ods'  => 'spreadsheet',
        'txt'  => 'text',
        'md'   => 'text',
        'rtf'  => 'text',
        'jpg'  => 'image',
        'jpeg' => 'image',
        'png'  => 'image',
        'gif'  => 'image',
        'webp' => 'image',
        'ppt'  => 'presentation',
        'pptx' => 'presentation',
        'odp'  => 'presentation',
        'zip'  => 'archive',
        'rar'  => 'archive',
        '7z'   => 'archive',
    ];

    /**
     * Validasi utama file ZIP
     * 
     * @param string $filePath Path lokal file yang diupload
     * @param string $originalName Nama asli file
     * @return array
     */
    public function validate(string $filePath, string $originalName): array
    {
        // 1. Validasi Ekstensi Luar
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            return $this->error("Hanya file dengan ekstensi .zip yang diizinkan.");
        }

        // 2. Validasi MIME Type
        if (!is_file($filePath) || !is_readable($filePath)) {
            return $this->error("File tidak terbaca atau hilang.");
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($filePath);
        $allowedMimes = ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip', 'application/x-compressed'];
        
        if (!in_array($mime, $allowedMimes)) {
            return $this->error("MIME type tidak valid ($mime). Pastikan file adalah ZIP murni.");
        }

        // 3. Validasi Isi ZIP via ZipArchive
        $zip = new ZipArchive();
        $res = $zip->open($filePath);
        
        if ($res !== true) {
            return $this->error("Gagal membuka file ZIP. File mungkin korup atau bukan ZIP valid.");
        }

        $fileCount = $zip->numFiles;
        $totalSize = 0;
        $extensions = [];
        
        for ($i = 0; $i < $fileCount; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat['name'];
            
            // A. Check Path Traversal
            if (strpos($name, '../') !== false || strpos($name, '..\\') !== false) {
                $zip->close();
                return $this->error("Keamanan Terdeteksi: Path traversal (../) ditemukan di dalam ZIP.");
            }

            // B. Check Blocked Extensions
            $innerExt = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($innerExt, $this->blockedExtensions)) {
                $zip->close();
                return $this->error("Keamanan Terdeteksi: File berbahaya (.$innerExt) ditemukan di dalam ZIP.");
            }

            // C. Accumulate metrics
            $totalSize += $stat['size'];
            if ($innerExt !== '') {
                $extensions[] = $innerExt;
            }
        }

        $detectedType = $this->determineDetectedType($extensions);
        $zip->close();

        return [
            'success' => true,
            'message' => 'Validasi berhasil.',
            'file_count' => $fileCount,
            'total_uncompressed_size' => $totalSize,
            'detected_file_type' => $detectedType
        ];
    }

    private function determineDetectedType(array $extensions): string
    {
        if (empty($extensions)) return 'mixed';
        
        // Hitung frekuensi ekstensi
        $counts = array_count_values($extensions);
        arsort($counts);
        $mostFrequent = key($counts);

        return $this->typeMapping[$mostFrequent] ?? 'other';
    }

    private function error(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'file_count' => 0,
            'total_uncompressed_size' => 0,
            'detected_file_type' => null
        ];
    }
}
