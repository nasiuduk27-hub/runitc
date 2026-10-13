<?php

namespace App\Repositories\FilingSystem;

use App\Services\FilingSystem\FilingPermissionService;
use PDO;

class FileGroup
{
    private PDO $db;

    public function __construct(PDO $pdoRun)
    {
        $this->db = $pdoRun;
    }

    /**
     * Daftar grup yang boleh dilihat user (pemilik + anggota user + anggota divisi),
     * urut aktivitas file terbaru. Mengembalikan grup + penanda "masih ada lagi".
     *
     * @return array{groups: array<int, array>, more: bool, total: int}
     */
    public function listForUser(int $userId, int $limit = 10): array
    {
        $roles = (new FilingPermissionService($this->db))->resolveGroupMembership($userId);
        if (empty($roles)) {
            return ['groups' => [], 'more' => false, 'total' => 0];
        }

        $ids = array_map('intval', array_keys($roles));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "
            SELECT g.rec_id, g.group_name, g.description, g.created_by, g.created_at, g.updated_at,
                   (SELECT COUNT(*) FROM file_system f
                      INNER JOIN file_shareto st ON st.filesys_id = f.rec_id
                           AND st.share_cat = 5 AND st.othercode = CAST(g.rec_id AS CHAR)
                     WHERE f.status = 'active') AS file_count,
                   (SELECT COALESCE(MAX(f.lupdt), g.created_at) FROM file_system f
                      INNER JOIN file_shareto st ON st.filesys_id = f.rec_id
                           AND st.share_cat = 5 AND st.othercode = CAST(g.rec_id AS CHAR)) AS last_activity
            FROM sys_filegrp g
            WHERE g.rec_id IN ($placeholders) AND g.archived_at IS NULL AND g.status = 'active'
            ORDER BY last_activity DESC, g.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $groups = [];
        foreach ($rows as $row) {
            $row['role'] = $roles[(int) $row['rec_id']] ?? 'viewer';
            $groups[] = $row;
        }

        $total = count($groups);
        $more = $total > $limit;
        if ($more) {
            $groups = array_slice($groups, 0, $limit);
        }

        return ['groups' => $groups, 'more' => $more, 'total' => $total];
    }

    public function getGroup(int $groupId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM sys_filegrp WHERE rec_id = ? LIMIT 1');
        $stmt->execute([$groupId]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $group) {
            return null;
        }

        $count = $this->db->prepare("
            SELECT COUNT(*) FROM file_system f
            INNER JOIN file_shareto st ON st.filesys_id = f.rec_id
                 AND st.share_cat = 5 AND st.othercode = ?
            WHERE f.status = 'active'
        ");
        $count->execute([(string) $groupId]);
        $group['file_count'] = (int) $count->fetchColumn();

        return $group;
    }

    public function roleFor(int $groupId, int $userId): ?string
    {
        return (new FilingPermissionService($this->db))->resolveGroupMembership($userId)[$groupId] ?? null;
    }

    public function isMember(int $groupId, int $userId): bool
    {
        return $this->roleFor($groupId, $userId) !== null;
    }

    public function createGroup(int $userId, string $name, string $description): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO sys_filegrp (group_name, description, created_by, status, created_at)
            VALUES (?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([$name, $description, $userId]);

        return (int) $this->db->lastInsertId();
    }

    public function updateGroup(int $groupId, string $name, string $description): void
    {
        $stmt = $this->db->prepare('
            UPDATE sys_filegrp SET group_name = ?, description = ?, updated_at = NOW()
            WHERE rec_id = ?
        ');
        $stmt->execute([$name, $description, $groupId]);
    }

    public function archiveGroup(int $groupId): void
    {
        $stmt = $this->db->prepare('UPDATE sys_filegrp SET archived_at = NOW(), updated_at = NOW() WHERE rec_id = ?');
        $stmt->execute([$groupId]);
    }

    /**
     * Tambah/ubah anggota. Idempoten via unique key (group_id, member_type, member_value).
     */
    public function addMember(int $groupId, string $type, string $value, string $role, int $addedBy): void
    {
        $type = in_array($type, ['user', 'division'], true) ? $type : 'user';
        $role = in_array($role, ['manager', 'contributor', 'viewer'], true) ? $role : 'contributor';
        $userId = $type === 'user' ? (int) $value : 0;

        if ($value === '' || ($type === 'user' && $userId <= 0)) {
            return;
        }

        $stmt = $this->db->prepare('
            INSERT INTO sys_filegrp_member (group_id, user_id, member_type, member_value, role, added_by, added_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE role = VALUES(role), added_by = VALUES(added_by)
        ');
        $stmt->execute([$groupId, $userId, $type, (string) $value, $role, $addedBy]);
    }

    public function removeMember(int $groupId, int $memberId): void
    {
        $stmt = $this->db->prepare('DELETE FROM sys_filegrp_member WHERE rec_id = ? AND group_id = ?');
        $stmt->execute([$memberId, $groupId]);
    }

    public function updateMemberRole(int $groupId, int $memberId, string $role): void
    {
        $role = in_array($role, ['manager', 'contributor', 'viewer'], true) ? $role : 'contributor';
        $stmt = $this->db->prepare('UPDATE sys_filegrp_member SET role = ? WHERE rec_id = ? AND group_id = ?');
        $stmt->execute([$role, $memberId, $groupId]);
    }

    /**
     * Keluar dari grup: hapus keanggotaan perorangan user. Keanggotaan divisi tidak bisa "keluar"
     * (mengikuti divisi secara dinamis).
     */
    public function leaveGroup(int $groupId, int $userId): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM sys_filegrp_member
            WHERE group_id = ? AND member_type = 'user' AND member_value = ?
        ");
        $stmt->execute([$groupId, (string) $userId]);
    }

    public function getMembers(int $groupId): array
    {
        $stmt = $this->db->prepare("
            SELECT m.rec_id, m.member_type, m.member_value, m.role, m.added_at, m.user_id,
                   COALESCE(NULLIF(u.account_nm, ''), l.account_id, CONCAT('User #', m.user_id)) AS user_name
            FROM sys_filegrp_member m
            LEFT JOIN sysitc_users u ON m.member_type = 'user' AND u.rec_id = m.user_id
            LEFT JOIN sysitc_login l ON l.rec_id = u.login_rec_id
            WHERE m.group_id = ?
            ORDER BY FIELD(m.role, 'manager', 'contributor', 'viewer'), m.added_at ASC
        ");
        $stmt->execute([$groupId]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $divisions = $this->divisionLabels();
        foreach ($members as &$member) {
            if ($member['member_type'] === 'division') {
                $code = (string) $member['member_value'];
                $member['label'] = $code.($divisions[$code] ? ' - '.$divisions[$code] : '');
            } else {
                $member['label'] = (string) $member['user_name'];
            }
        }
        unset($member);

        return $members;
    }

    public function moveFileToGroup(int $groupId, int $filingId, int $userId): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO file_shareto (filesys_id, share_cat, othercode, created_by, create_dt)
            SELECT ?, 5, ?, ?, NOW() FROM DUAL
            WHERE NOT EXISTS (
                SELECT 1 FROM file_shareto
                WHERE filesys_id = ? AND share_cat = 5 AND othercode = ?
            )
        ');
        $stmt->execute([$filingId, (string) $groupId, $userId, $filingId, (string) $groupId]);
    }

    public function removeFileFromGroup(int $groupId, int $filingId): void
    {
        $stmt = $this->db->prepare('DELETE FROM file_shareto WHERE filesys_id = ? AND share_cat = 5 AND othercode = ?');
        $stmt->execute([$filingId, (string) $groupId]);
    }

    /** @return array<int, array{value:string,label:string}> */
    public function userOptions(): array
    {
        $stmt = $this->db->query("
            SELECT u.rec_id AS value,
                   COALESCE(NULLIF(u.account_nm, ''), l.account_id, CONCAT('User #', u.rec_id)) AS label
            FROM sysitc_users u
            LEFT JOIN sysitc_login l ON l.rec_id = u.login_rec_id
            WHERE u.status = 1
            ORDER BY label ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array{value:string,label:string}> */
    public function divisionOptions(): array
    {
        $stmt = $this->db->query("
            SELECT code AS value, CONCAT(code, ' - ', descr) AS label
            FROM sys_msttable
            WHERE tbl_code = '55' AND statrec = 1
            ORDER BY code ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,string> code => descr */
    private function divisionLabels(): array
    {
        $labels = [];
        try {
            foreach ($this->db->query("
                SELECT code, descr FROM sys_msttable WHERE tbl_code = '55' AND statrec = 1
            ")->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $labels[(string) $row['code']] = (string) $row['descr'];
            }
        } catch (\Throwable $e) {
            $labels = [];
        }

        return $labels;
    }
}
