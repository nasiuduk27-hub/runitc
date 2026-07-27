<?php

if (!function_exists('logAudit')) {
    function logAudit(
        PDO $pdoRun,
        string $action,
        string $targetType,
        ?int $targetId = null,
        array $metadata = [],
        ?int $actorUserId = null
    ): void {
        if ($actorUserId === null) {
            $actorUserId = (int) ($_SESSION['user_id'] ?? 0);
        }
        $allowSystemActor = str_starts_with($action, 'LOGIN_FAILED');
        if ($actorUserId <= 0 && !$allowSystemActor) {
            return;
        }

        try {
            $audit = new AuditLog($pdoRun);
            $audit->log($actorUserId, $action, $targetType, $targetId, $metadata);
        } catch (Throwable $e) {
            error_log('AuditLog failed: ' . $e->getMessage());
        }
    }
}
