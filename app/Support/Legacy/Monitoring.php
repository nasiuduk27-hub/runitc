<?php

namespace App\Support\Legacy;

class Monitoring
{
    private \PDO $pdo;

    private \PDO $pdoRun;

    private \PDO $pdoWar;

    public function __construct(\PDO $pdo, \PDO $pdoRun, \PDO $pdoWar)
    {
        $this->pdo = $pdo;
        $this->pdoRun = $pdoRun;
        $this->pdoWar = $pdoWar;
    }

    public function getSupervisorByUserId($itcId): ?array
    {
        $stmt = $this->pdoRun->prepare('
            SELECT rec_id, itc_usr_id AS itc_user_id, spv_name, spv_alias, phone, photo_path
            FROM tad_supervisor
            WHERE itc_usr_id = ?
            LIMIT 1
        ');

        $stmt->execute([$itcId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function isSupervisorAssignedToRoom($spvRecId, $adminId, $subAdminId): bool
    {
        $stmt = $this->pdoWar->prepare('
            SELECT COUNT(*)
            FROM t3sT5ub4dm1n
            WHERE spv_recid = ?
              AND admin_id = ?
              AND rec_id = ?
        ');

        $stmt->execute([$spvRecId, $adminId, $subAdminId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function getActiveDates($spvRecId = null): array
    {
        [$yearStart, $nextYearStart] = $this->currentYearRange();

        if ($spvRecId !== null) {
            $stmt = $this->pdoWar->prepare('
                SELECT DISTINCT LEFT(a.testdt, 10)
                FROM t3sT5ub4dm1n s
                INNER JOIN t3sTAdm1n a ON s.admin_id = a.rec_id
                WHERE s.spv_recid = ?
                AND a.testdt IS NOT NULL
                AND a.testdt >= ?
                AND a.testdt < ?
            ');
            $stmt->execute([$spvRecId, $yearStart, $nextYearStart]);
        } else {
            $stmt = $this->pdoWar->prepare('
                SELECT DISTINCT LEFT(a.testdt, 10)
                FROM t3sT5ub4dm1n s
                INNER JOIN t3sTAdm1n a ON s.admin_id = a.rec_id
                WHERE a.testdt IS NOT NULL
                AND a.testdt >= ?
                AND a.testdt < ?
            ');
            $stmt->execute([$yearStart, $nextYearStart]);
        }

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function currentYearRange(): array
    {
        $year = (int) date('Y');

        return [$year.'-01-01', ($year + 1).'-01-01'];
    }

    public function getAdminsByDate($spvRecId = null, string $selectedDate = ''): array
    {
        return $this->getAdminsByDates($spvRecId, [$selectedDate]);
    }

    public function getAdminsByDates($spvRecId = null, array $selectedDates = []): array
    {
        $selectedDates = array_values(array_unique(array_filter($selectedDates, static function ($date) {
            return is_string($date) && preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/', $date);
        })));

        if (empty($selectedDates)) {
            return [];
        }

        [$yearStart, $nextYearStart] = $this->currentYearRange();
        $datePlaceholders = implode(',', array_fill(0, count($selectedDates), '?'));

        if ($spvRecId !== null) {
            $stmt = $this->pdoWar->prepare("
                SELECT 
                    s.rec_id AS sub_admin_id,
                    s.batch_no,
                    s.authorize_amt,
                    a.rec_id AS admin_rec_id,
                    a.admin_no,
                    a.conn_type,
                    a.testdt,
                    a.testcd,
                    a.client_id
                FROM t3sT5ub4dm1n s
                INNER JOIN t3sTAdm1n a ON s.admin_id = a.rec_id
                WHERE s.spv_recid = ?
                AND DATE(a.testdt) IN ({$datePlaceholders})
                AND a.testdt >= ?
                AND a.testdt < ?
            ");
            $stmt->execute(array_merge([$spvRecId], $selectedDates, [$yearStart, $nextYearStart]));
        } else {
            $stmt = $this->pdoWar->prepare("
                SELECT 
                    s.rec_id AS sub_admin_id,
                    s.batch_no,
                    s.authorize_amt,
                    a.rec_id AS admin_rec_id,
                    a.admin_no,
                    a.conn_type,
                    a.testdt,
                    a.testcd,
                    a.client_id
                FROM t3sT5ub4dm1n s
                INNER JOIN t3sTAdm1n a ON s.admin_id = a.rec_id
                WHERE DATE(a.testdt) IN ({$datePlaceholders})
                AND a.testdt >= ?
                AND a.testdt < ?
            ");
            $stmt->execute(array_merge($selectedDates, [$yearStart, $nextYearStart]));
        }
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $clientNames = $this->getClientNames(array_column($rows, 'client_id'));

        $filteredAdmins = [];

        foreach ($rows as $row) {
            $clientId = $row['client_id'] ?? null;
            $clientName = $clientNames[(string) $clientId] ?? '-';

            $key = $row['admin_rec_id'].'|'.$row['sub_admin_id'];

            $filteredAdmins[$key] = [
                'admin_rec_id' => $row['admin_rec_id'],
                'admin_no' => $row['admin_no'],
                'conn_type' => (int) ($row['conn_type'] ?? 1),
                'monitoring_mode' => (int) ($row['conn_type'] ?? 1) === 2 ? 'hybrid' : 'online',
                'sub_admin_id' => $row['sub_admin_id'],
                'batch_no' => $row['batch_no'] ?? '',
                'date' => $row['testdt'],
                'type' => $row['testcd'],
                'client_nm' => $clientName,
                'qty' => $row['authorize_amt'],
            ];
        }

        return $filteredAdmins;
    }

    private function getClientNames(array $clientIds): array
    {
        $clientIds = array_values(array_unique(array_filter($clientIds, static fn ($id) => $id !== null && $id !== '')));

        if (empty($clientIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT rec_id, clientnm
            FROM sys_mstclient
            WHERE rec_id IN ({$placeholders})
        ");
        $stmt->execute($clientIds);

        $clientNames = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $clientNames[(string) $row['rec_id']] = $row['clientnm'] ?: '-';
        }

        return $clientNames;
    }

    public function getClientName($clientId): string
    {
        if (empty($clientId)) {
            return '-';
        }

        $stmt = $this->pdo->prepare('
            SELECT clientnm 
            FROM sys_mstclient 
            WHERE rec_id = ?
            LIMIT 1
        ');

        $stmt->execute([$clientId]);

        return $stmt->fetchColumn() ?: '-';
    }

    public function getTotalTiming(?string $testCode): int
    {
        if (empty($testCode)) {
            return 0;
        }

        $stmt = $this->pdoWar->prepare('
            SELECT timing
            FROM cbt_tbltimer
            WHERE testcd = ?
            LIMIT 1
        ');

        $stmt->execute([$testCode]);

        return (int) $stmt->fetchColumn();
    }

    public function getParticipants($adminId, $subAdminId, string $adminNo, ?string $testCode): array
    {
        $totalTiming = $this->getTotalTiming($testCode);
        $psyskunci = Nisn::shift(trim($adminNo), 3);

        $stmt = $this->pdoWar->prepare("
            SELECT
                t.rec_id,
                t.authorize,
                t.authorcryp,
                t.regnm,
                t.idno,
                t.dob,
                t.sexmf,
                t.email,
                t.hpno,
                t.countcd,
                t.langcode,
                t.groupcd,
                t.custom1,
                t.custom2,
                t.custom3,
                t.questioner,
                t.seqno,
                t.remindtm,
                t.statrec,
                t.lupdt,
                t.partno,
                t.ke_suspend,
                t.start_time,
                t.end_time,
                COALESCE(ans.total_soal, 0) AS total_soal,
                COALESCE(ans.ans_filled, 0) AS ans_filled
            FROM t3sTt4keR5 t
            LEFT JOIN (
                SELECT
                    ttaker_id,
                    COUNT(rec_id) AS total_soal,
                    SUM(CASE
                        WHEN answeruser IS NOT NULL
                         AND TRIM(answeruser) != ''
                         AND TRIM(answeruser) != '0'
                        THEN 1 ELSE 0
                    END) AS ans_filled
                FROM t3sT4n5wers
                GROUP BY ttaker_id
            ) ans ON ans.ttaker_id = t.rec_id
            WHERE t.admin_id = ?
            AND t.sub_adm_id = ?
        ");

        $stmt->execute([$adminId, $subAdminId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $participants = [];

        foreach ($rows as $row) {
            $participants[] = $this->formatParticipant($row, $psyskunci, $totalTiming);
        }

        return [
            'participants' => $participants,
            'total_timing' => $totalTiming,
        ];
    }

    public function getAttendanceParticipantsByAdmin($adminId): array
    {
        $stmt = $this->pdoWar->prepare('
            SELECT authorize, regnm, idno, dob, hpno, groupcd, custom1, custom2, custom3, statrec, seqno
            FROM t3sTt4keR5
            WHERE admin_id = ?
            ORDER BY sub_adm_id ASC, seqno ASC, rec_id ASC
        ');

        $stmt->execute([$adminId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function formatParticipant(array $row, string $psyskunci, int $totalTiming): array
    {
        $namaAsli = Nisn::decrypt(trim($row['regnm'] ?? ''), $psyskunci);
        $status = (string) trim($row['statrec']);
        $questioner = trim($row['questioner'] ?? '');

        [$qStatus, $qColor] = match (true) {
            empty($questioner) => ['N/A', 'text-gray-400 italic'],
            str_contains($questioner, ' ') || str_contains($questioner, '0') => ['Terisi', 'text-yellow-600 bg-yellow-50 border border-yellow-200 px-2 py-0.5 rounded'],
            default => ['✓ Selesai', 'text-green-600 bg-green-50 border border-green-200 px-2 py-0.5 rounded shadow-sm'],
        };

        $answered = (int) $row['ans_filled'];
        $totalQuestion = (int) ($row['total_soal'] ?: ($row['mstqno'] ?? 0));
        $progress = $totalQuestion > 0 ? min(100, round(($answered / $totalQuestion) * 100)) : 0;

        $isOnline = in_array($status, ['1', '2', '3', '4', '5', '6', 'a', 'A'], true) || (time() - strtotime($row['lupdt'] ?? '2000-01-01')) <= 180;

        $remainingTime = ! empty($row['remindtm']) ? trim($row['remindtm']) : '';
        if (in_array($status, ['7', '8', '9', 'c', 'C'], true)) {
            $remainingTime = 'Selesai';
        } elseif (empty($remainingTime) || $remainingTime === '00:00:00' || $remainingTime === '0') {
            $remainingTime = $totalTiming > 0 ? sprintf('%02d:%02d:00', floor($totalTiming / 60), $totalTiming % 60) : '00:00:00';
        }

        return [
            'rec_id' => $row['rec_id'],
            'id' => $row['authorize'] ?? '-',
            'name' => $namaAsli,
            'is_online' => $isOnline,
            'q_status' => $qStatus,
            'q_color' => $qColor,
            'progress_pct' => $progress,
            'ans_filled' => $answered,
            'ans_total' => $totalQuestion,
            'sisa_waktu' => $remainingTime,
            'status' => $status,
            'statrec' => $status,
            'status_text' => StatusHelper::getStatusText($status),
            'partno' => ! empty($row['partno']) ? trim($row['partno']) : '1',
            'ke_suspend' => (int) $row['ke_suspend'],
            'timer_active' => in_array($status, ['5', '6'], true) && (int) $row['ke_suspend'] === 0 ? 'true' : 'false',

            // Detail peserta untuk modal
            'regnm' => $namaAsli,
            'idno' => Nisn::decrypt(trim($row['idno'] ?? ''), $psyskunci),
            'nisn' => Nisn::decrypt(trim($row['idno'] ?? ''), $psyskunci),
            'dob' => Nisn::decrypt(trim($row['dob'] ?? ''), $psyskunci),
            'sexmf' => Nisn::decrypt(trim($row['sexmf'] ?? ''), $psyskunci),
            'email' => Nisn::decrypt(trim($row['email'] ?? ''), $psyskunci),
            'hpno' => Nisn::decrypt(trim($row['hpno'] ?? ''), $psyskunci),
            'countcd' => Nisn::decrypt(trim($row['countcd'] ?? ''), $psyskunci),
            'langcode' => Nisn::decrypt(trim($row['langcode'] ?? ''), $psyskunci),
            'groupcd' => Nisn::decrypt(trim($row['groupcd'] ?? ''), $psyskunci),
            'custom1' => Nisn::decrypt(trim($row['custom1'] ?? ''), $psyskunci),
            'custom2' => Nisn::decrypt(trim($row['custom2'] ?? ''), $psyskunci),
            'custom3' => Nisn::decrypt(trim($row['custom3'] ?? ''), $psyskunci),
            'start_time' => (! empty($row['start_time']) && $row['start_time'] !== '0000-00-00 00:00:00') ? trim($row['start_time']) : '-',
            'end_time' => (! empty($row['end_time']) && $row['end_time'] !== '0000-00-00 00:00:00') ? trim($row['end_time']) : '-',
        ];
    }
}
