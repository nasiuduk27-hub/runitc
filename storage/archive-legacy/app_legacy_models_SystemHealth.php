<?php

class SystemHealth
{
    private PDO $pdo;

    private PDO $pdoBot;

    private PDO $pdoRun;

    private PDO $pdoWar;

    private array $ftpConfig;

    public function __construct(PDO $pdo, PDO $pdoBot, PDO $pdoRun, PDO $pdoWar, array $ftpConfig)
    {
        $this->pdo = $pdo;
        $this->pdoBot = $pdoBot;
        $this->pdoRun = $pdoRun;
        $this->pdoWar = $pdoWar;
        $this->ftpConfig = $ftpConfig;
    }

    public function checkAll(): array
    {
        return [
            'databases' => $this->checkAllDatabases(),
            'ftp' => $this->checkFTP(),
            'storage' => $this->checkStorage(),
            'logs' => $this->getRecentErrors(5),
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    public function checkAllDatabases(): array
    {
        return [
            'main_db' => $this->checkDatabaseConnection('Main DB', $this->pdo),
            'bot_db' => $this->checkDatabaseConnection('Bot DB', $this->pdoBot),
            'run_db' => $this->checkDatabaseConnection('Run DB', $this->pdoRun),
            'war_db' => $this->checkDatabaseConnection('War DB', $this->pdoWar),
        ];
    }

    public function checkDatabaseConnection(string $name, PDO $pdo): array
    {
        $start = microtime(true);
        try {
            $pdo->query('SELECT 1');
            $elapsed = (microtime(true) - $start) * 1000;
            if ($elapsed < 100) {
                $status = 'ok';
            } elseif ($elapsed < 500) {
                $status = 'warning';
            } else {
                $status = 'critical';
            }

            return ['name' => $name, 'status' => $status, 'response_time' => round($elapsed, 2), 'error' => null];
        } catch (Throwable $e) {
            return ['name' => $name, 'status' => 'failed', 'response_time' => null, 'error' => $e->getMessage()];
        }
    }

    public function checkFTP(): array
    {
        try {
            $conn = ftp_connect($this->ftpConfig['host'] ?? '', (int) ($this->ftpConfig['port'] ?? 21), 5);
            if (! $conn) {
                throw new Exception('FTP connection failed');
            }
            $login = ftp_login($conn, $this->ftpConfig['user'] ?? '', $this->ftpConfig['pass'] ?? '');
            if (! $login) {
                throw new Exception('FTP login failed');
            }
            ftp_pasv($conn, true);

            $fileCount = 0;
            $rootPath = $this->ftpConfig['path'] ?? '/';
            try {
                $list = ftp_nlist($conn, $rootPath);
                if ($list) {
                    $fileCount = count($list);
                }
            } catch (Throwable $e) {
            }

            ftp_close($conn);

            return ['status' => 'ok', 'file_count' => $fileCount, 'error' => null];
        } catch (Throwable $e) {
            return ['status' => 'failed', 'file_count' => 0, 'error' => $e->getMessage()];
        }
    }

    public function checkStorage(): array
    {
        $available = disk_free_space(BASE_PATH);
        $total = disk_total_space(BASE_PATH);

        return [
            'available_space' => $available ? round($available / 1048576, 1) : 0,
            'total_space' => $total ? round($total / 1048576, 1) : 0,
            'used_space' => $total && $available ? round(($total - $available) / 1048576, 1) : 0,
            'writable' => is_writable(BASE_PATH),
            'temp_usage' => $this->getDirSize(sys_get_temp_dir()),
        ];
    }

    public function getRecentErrors(int $limit = 10): array
    {
        $errors = [];
        $logFile = ini_get('error_log');
        if ($logFile && file_exists($logFile)) {
            $lines = file($logFile);
            $lines = array_reverse($lines);
            $count = 0;
            foreach ($lines as $line) {
                $errors[] = $line;
                $count++;
                if ($count >= $limit) {
                    break;
                }
            }
        }

        return $errors;
    }

    public function getPerformanceMetrics(): array
    {
        return [
            'memory_usage' => round(memory_get_usage(true) / 1048576, 1),
            'peak_memory' => round(memory_get_peak_usage(true) / 1048576, 1),
            'php_version' => PHP_VERSION,
        ];
    }

    private function getDirSize(string $dir): string
    {
        $size = 0;
        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (Throwable $e) {
        }

        return $size > 1048576 ? round($size / 1048576, 1).' MB' : round($size / 1024, 1).' KB';
    }
}
