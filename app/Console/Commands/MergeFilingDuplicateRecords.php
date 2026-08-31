<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

class MergeFilingDuplicateRecords extends Command
{
    protected $signature = 'filing:merge-duplicate-records
        {--dry-run : Hanya menampilkan rencana tanpa mengubah data}';

    protected $description = 'Merge duplicate runit_filing_system records (satu baris per nomor_admin)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $pdo = DB::connection('run')->getPdo();

        $groups = $pdo->query("
            SELECT nomor_admin, COUNT(*) AS c
            FROM runit_filing_system
            GROUP BY nomor_admin
            HAVING COUNT(*) > 1
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (empty($groups)) {
            $this->info('Tidak ada duplikat ditemukan di runit_filing_system.');

            return self::SUCCESS;
        }

        $this->line('Menemukan '.count($groups).' nomor admin dengan duplikat.');
        if ($dryRun) {
            $this->line('[DRY-RUN] Tidak ada data yang diubah.');
        }

        $stats = [
            'groups' => 0,
            'merged_files' => 0,
            'merged_issues' => 0,
            'deleted_rows' => 0,
            'skipped' => 0,
        ];

        foreach ($groups as $group) {
            $adminNo = (string) $group['nomor_admin'];

            $stmt = $pdo->prepare('SELECT * FROM runit_filing_system WHERE nomor_admin = ? ORDER BY rec_id ASC');
            $stmt->execute([$adminNo]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($rows) < 2) {
                continue;
            }

            $keep = $this->chooseKeepRow($pdo, $rows);
            if ($keep === null) {
                $stats['skipped']++;
                $this->warn('  SKIP '.$adminNo.': tidak ada baris yang layak dipertahankan.');

                continue;
            }

            $stats['groups']++;
            $this->line('  '.$adminNo.' : keep rec_id='.$keep['rec_id'].' ('.count($rows).' baris)');

            if ($dryRun) {
                continue;
            }

            $pdo->beginTransaction();

            try {
                $this->applyLatestDistribution($pdo, $rows, $keep);

                $removed = [];
                foreach ($rows as $row) {
                    if ((int) $row['rec_id'] !== (int) $keep['rec_id']) {
                        $removed[] = (int) $row['rec_id'];
                    }
                }

                $movedFiles = 0;
                $movedIssues = 0;
                foreach ($removed as $oldId) {
                    $movedFiles += $this->moveFiles($pdo, $oldId, (int) $keep['rec_id']);
                    $movedIssues += $this->moveIssues($pdo, $oldId, (int) $keep['rec_id']);
                }

                if (! empty($removed)) {
                    $placeholders = implode(',', array_fill(0, count($removed), '?'));
                    $pdo->prepare("DELETE FROM runit_filing_system WHERE rec_id IN ({$placeholders})")->execute($removed);
                }

                $pdo->commit();

                $stats['merged_files'] += $movedFiles;
                $stats['merged_issues'] += $movedIssues;
                $stats['deleted_rows'] += count($removed);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $stats['skipped']++;
                $this->error('  GAGAL '.$adminNo.': '.$e->getMessage());
            }
        }

        $this->newLine();
        $this->line('Ringkasan:');
        $this->line('- Kelompok diproses : '.$stats['groups']);
        $this->line('- Baris dihapus      : '.$stats['deleted_rows']);
        $this->line('- File dipindahkan   : '.$stats['merged_files']);
        $this->line('- Issue dipindahkan  : '.$stats['merged_issues']);
        $this->line('- Gagal / Skip       : '.$stats['skipped']);

        return self::SUCCESS;
    }

    private function chooseKeepRow(PDO $pdo, array $rows): ?array
    {
        foreach ($rows as $row) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM runit_filing_files WHERE filing_id = ?');
            $stmt->execute([(int) $row['rec_id']]);
            if ((int) $stmt->fetchColumn() > 0) {
                return $row;
            }
        }

        foreach ($rows as $row) {
            if (trim((string) ($row['sub_admin_id'] ?? '')) !== '') {
                return $row;
            }
        }

        usort($rows, static fn (array $a, array $b): int => (int) $b['rec_id'] <=> (int) $a['rec_id']);

        return $rows[0] ?? null;
    }

    private function applyLatestDistribution(PDO $pdo, array $rows, array &$keep): void
    {
        $hasSpvName = static fn (array $row): bool => in_array(trim((string) ($row['spv_name'] ?? '')), ['', '-'], true) === false;
        $hasSubAdmin = static fn (array $row): bool => trim((string) ($row['sub_admin_id'] ?? '')) !== '';

        $latest = null;
        foreach ($rows as $row) {
            $score = 0;
            if ($hasSubAdmin($row) && $hasSpvName($row)) {
                $score = 3;
            } elseif ($hasSpvName($row)) {
                $score = 2;
            } elseif ($hasSubAdmin($row)) {
                $score = 1;
            }

            if ($score === 0) {
                continue;
            }

            if ($latest === null
                || $score > $latest['score']
                || ($score === $latest['score'] && (int) $row['rec_id'] > (int) $latest['rec_id'])) {
                $latest = $row + ['score' => $score];
            }
        }

        if ($latest === null) {
            return;
        }

        $stmt = $pdo->prepare('
            UPDATE runit_filing_system
            SET sub_admin_id = ?, admin_id = ?, tanggal = ?, input_by = ?, spv_name = ?
            WHERE rec_id = ?
        ');
        $stmt->execute([
            (string) ($latest['sub_admin_id'] ?? $keep['sub_admin_id'] ?? ''),
            (int) ($latest['admin_id'] ?? $keep['admin_id'] ?? 0),
            (string) ($latest['tanggal'] ?? $keep['tanggal'] ?? date('Y-m-d')),
            (string) ($latest['input_by'] ?? $keep['input_by'] ?? 'System'),
            (string) ($latest['spv_name'] ?? $keep['spv_name'] ?? '-'),
            (int) $keep['rec_id'],
        ]);

        $keep['sub_admin_id'] = (string) ($latest['sub_admin_id'] ?? $keep['sub_admin_id'] ?? '');
        $keep['admin_id'] = (int) ($latest['admin_id'] ?? $keep['admin_id'] ?? 0);
        $keep['tanggal'] = (string) ($latest['tanggal'] ?? $keep['tanggal'] ?? '');
        $keep['input_by'] = (string) ($latest['input_by'] ?? $keep['input_by'] ?? '');
        $keep['spv_name'] = (string) ($latest['spv_name'] ?? $keep['spv_name'] ?? '-');
    }

    private function moveFiles(PDO $pdo, int $fromFilingId, int $toFilingId): int
    {
        $stmt = $pdo->prepare('
            SELECT file_id, file_name, file_path, file_type, file_category, uploaded_at
            FROM runit_filing_files
            WHERE filing_id = ?
        ');
        $stmt->execute([$fromFilingId]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($files)) {
            return 0;
        }

        $existing = $pdo->prepare('SELECT file_id FROM runit_filing_files WHERE filing_id = ? AND file_path = ? LIMIT 1');
        $insert = $pdo->prepare('
            INSERT INTO runit_filing_files (filing_id, file_name, file_path, file_type, file_category, uploaded_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $delete = $pdo->prepare('DELETE FROM runit_filing_files WHERE file_id = ?');

        $moved = 0;
        foreach ($files as $file) {
            $existing->execute([$toFilingId, $file['file_path']]);
            $dupFileId = $existing->fetchColumn();

            if ($dupFileId) {
                $delete->execute([(int) $file['file_id']]);

                continue;
            }

            $insert->execute([
                $toFilingId,
                $file['file_name'],
                $file['file_path'],
                $file['file_type'],
                $file['file_category'],
                $file['uploaded_at'],
            ]);
            $delete->execute([(int) $file['file_id']]);
            $moved++;
        }

        return $moved;
    }

    private function moveIssues(PDO $pdo, int $fromFilingId, int $toFilingId): int
    {
        $stmt = $pdo->prepare('UPDATE runit_filing_issues SET filing_id = ? WHERE filing_id = ?');
        $stmt->execute([$toFilingId, $fromFilingId]);

        return $stmt->rowCount();
    }
}