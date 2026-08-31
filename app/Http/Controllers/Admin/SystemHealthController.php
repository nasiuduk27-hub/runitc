<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class SystemHealthController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($this->isSuperadmin($request), 403);

        return view('admin.system-health.index', [
            'health' => $this->checkAll(),
            'perf' => $this->getPerformanceMetrics(),
        ]);
    }

    public function api(Request $request): JsonResponse
    {
        abort_unless($this->isSuperadmin($request), 403);

        return response()->json(match ($request->query('action')) {
            'check_all' => $this->checkAll(),
            'test_db' => $this->testDatabase((string) $request->query('db', '')),
            'test_ftp' => $this->checkFtp(),
            default => ['error' => 'Unknown action'],
        });
    }

    public function legacy(Request $request): View|JsonResponse
    {
        return $request->query->has('api') ? $this->api($request) : $this->index($request);
    }

    private function checkAll(): array
    {
        return [
            'databases' => $this->checkDatabases(),
            'ftp' => $this->checkFtp(),
            'storage' => $this->checkStorage(),
            'logs' => $this->getRecentErrors(5),
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ];
    }

    private function checkDatabases(): array
    {
        return [
            'main_db' => $this->testDatabase('mysql', 'Main DB'),
            'bot_db' => $this->testDatabase('bot', 'Bot DB'),
            'run_db' => $this->testDatabase('run', 'Run DB'),
            'war_db' => $this->testDatabase('war', 'War DB'),
        ];
    }

    private function testDatabase(string $connection, ?string $name = null): array
    {
        $labels = ['mysql' => 'Main DB', 'bot' => 'Bot DB', 'run' => 'Run DB', 'war' => 'War DB', 'main_db' => 'Main DB', 'bot_db' => 'Bot DB', 'run_db' => 'Run DB', 'war_db' => 'War DB'];
        $connections = ['main_db' => 'mysql', 'bot_db' => 'bot', 'run_db' => 'run', 'war_db' => 'war'];
        $connection = $connections[$connection] ?? $connection;
        $start = microtime(true);

        try {
            DB::connection($connection)->select('SELECT 1');
            $elapsed = round((microtime(true) - $start) * 1000, 2);
            $status = $elapsed < 100 ? 'ok' : ($elapsed < 500 ? 'warning' : 'critical');

            return ['name' => $name ?: ($labels[$connection] ?? ucfirst($connection)), 'status' => $status, 'response_time' => $elapsed, 'error' => null];
        } catch (Throwable $e) {
            return ['name' => $name ?: ($labels[$connection] ?? ucfirst($connection)), 'status' => 'failed', 'response_time' => null, 'error' => $e->getMessage()];
        }
    }

    private function checkFtp(): array
    {
        $host = (string) env('FTP_HOST', '');
        if ($host === '') {
            return ['status' => 'failed', 'file_count' => 0, 'error' => 'FTP host is not configured'];
        }

        try {
            $conn = @ftp_connect($host, (int) env('FTP_PORT', 21), 5);
            if (! $conn) {
                throw new \RuntimeException('FTP connection failed');
            }

            if (! @ftp_login($conn, (string) env('FTP_USER', ''), (string) env('FTP_PASS', ''))) {
                @ftp_close($conn);
                throw new \RuntimeException('FTP login failed');
            }

            @ftp_pasv($conn, true);
            $list = @ftp_nlist($conn, (string) env('FTP_PATH', '/')) ?: [];
            @ftp_close($conn);

            return ['status' => 'ok', 'file_count' => count($list), 'error' => null];
        } catch (Throwable $e) {
            return ['status' => 'failed', 'file_count' => 0, 'error' => $e->getMessage()];
        }
    }

    private function checkStorage(): array
    {
        $path = base_path();
        $available = @disk_free_space($path);
        $total = @disk_total_space($path);

        return [
            'available_space' => $available ? round($available / 1048576, 1) : 0,
            'total_space' => $total ? round($total / 1048576, 1) : 0,
            'used_space' => $total && $available ? round(($total - $available) / 1048576, 1) : 0,
            'writable' => is_writable(storage_path()),
            'temp_usage' => $this->getDirSize(sys_get_temp_dir()),
        ];
    }

    private function getRecentErrors(int $limit): array
    {
        $logFile = storage_path('logs/laravel.log');
        if (! file_exists($logFile)) {
            $logFile = ini_get('error_log') ?: '';
        }
        if ($logFile === '' || ! file_exists($logFile)) {
            return [];
        }

        $lines = @file($logFile) ?: [];

        return array_slice(array_reverse($lines), 0, $limit);
    }

    private function getPerformanceMetrics(): array
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
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (Throwable) {
            return '0 KB';
        }

        return $size > 1048576 ? round($size / 1048576, 1).' MB' : round($size / 1024, 1).' KB';
    }

    private function isSuperadmin(Request $request): bool
    {
        $userId = (int) $request->session()->get('user_id', 0);
        if ($userId <= 0) {
            return false;
        }

        return DB::connection('run')
            ->table('sysitc_usracc as ua')
            ->join('sysitc_grpacc as g', function ($join): void {
                $join->on('g.grpaccess', '=', 'ua.access_code')->on('g.grpacc', '=', 'ua.access_account');
            })
            ->where('ua.user_rec_id', $userId)
            ->where('g.grpaccess', '03')
            ->where('g.grpacc', '999')
            ->where('g.grpdesc', 'like', '%SUPER%ADMIN%')
            ->exists();
    }
}
